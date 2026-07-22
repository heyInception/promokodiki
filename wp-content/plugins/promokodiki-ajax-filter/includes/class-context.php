<?php
/**
 * Context-aware filter options.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Context {
	private const CACHE_VERSION_OPTION = 'promokodiki_filter_context_cache_version';

	public static function resolve( string $type, int $object_id = 0 ): array|WP_Error {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, array( 'home', 'category', 'shop' ), true ) ) {
			return new WP_Error( 'invalid_filter_context', __( 'Unknown filter context.', 'promokodiki-ajax-filter' ) );
		}

		$cache_key = self::cache_key( $type, $object_id );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( 'category' === $type ) {
			$context = self::category_context( $object_id );
		} elseif ( 'shop' === $type ) {
			$context = self::shop_context( $object_id );
		} else {
			$context = self::home_context();
		}

		if ( ! is_wp_error( $context ) ) {
			set_transient( $cache_key, $context, HOUR_IN_SECONDS );
		}

		return $context;
	}

	public static function flush_cache(): void {
		$version = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
		update_option( self::CACHE_VERSION_OPTION, $version + 1, false );
	}

	private static function home_context(): array {
		$categories = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$brands = get_terms(
			array(
				'taxonomy'   => 'shops_category',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$categories = is_wp_error( $categories ) ? array() : $categories;
		$brands     = is_wp_error( $brands ) ? array() : $brands;

		return array(
			'type'                 => 'home',
			'object_id'            => 0,
			'category_options'     => self::term_options( $categories ),
			'brand_options'        => self::term_options( $brands ),
			'brand_taxonomy'       => 'shops_category',
			'allowed_category_ids' => array_map( 'intval', wp_list_pluck( $categories, 'term_id' ) ),
			'allowed_brand_ids'    => array_map( 'intval', wp_list_pluck( $brands, 'term_id' ) ),
		);
	}

	private static function category_context( int $object_id ): array|WP_Error {
		$current = get_term( $object_id, 'promocode_category' );
		if ( ! $current || is_wp_error( $current ) ) {
			return new WP_Error( 'invalid_filter_category', __( 'Filter category was not found.', 'promokodiki-ajax-filter' ) );
		}

		$options = array(
			array(
				'id'    => (int) $current->term_id,
				'label' => $current->name,
				'depth' => 0,
			),
		);
		self::append_category_children( (int) $current->term_id, 1, $options );

		return array(
			'type'                 => 'category',
			'object_id'            => (int) $current->term_id,
			'category_options'     => $options,
			'brand_options'        => array(),
			'brand_taxonomy'       => 'shops_category',
			'allowed_category_ids' => array_map( 'intval', wp_list_pluck( $options, 'id' ) ),
			'allowed_brand_ids'    => array(),
		);
	}

	private static function shop_context( int $object_id ): array|WP_Error {
		$current = get_term( $object_id, 'shops_category' );
		if ( ! $current || is_wp_error( $current ) ) {
			return new WP_Error( 'invalid_filter_shop', __( 'Filter shop was not found.', 'promokodiki-ajax-filter' ) );
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'promocode',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'shops_category',
						'field'    => 'term_id',
						'terms'    => array( (int) $current->term_id ),
					),
				),
				'meta_query'             => self::active_expiry_query(),
			)
		);

		$brands = $query->posts
			? wp_get_object_terms( $query->posts, 'shops_category', array( 'orderby' => 'name', 'order' => 'ASC' ) )
			: array();
		$brands = is_wp_error( $brands ) ? array() : $brands;
		usort(
			$brands,
			static fn( WP_Term $left, WP_Term $right ): int => strnatcasecmp( $left->name, $right->name )
		);

		return array(
			'type'                 => 'shop',
			'object_id'            => (int) $current->term_id,
			'category_options'     => array(),
			'brand_options'        => self::term_options( $brands ),
			'brand_taxonomy'       => 'shops_category',
			'allowed_category_ids' => array(),
			'allowed_brand_ids'    => array_map( 'intval', wp_list_pluck( $brands, 'term_id' ) ),
		);
	}

	private static function append_category_children( int $parent_id, int $depth, array &$options ): void {
		$children = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
				'parent'     => $parent_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $children ) ) {
			return;
		}

		foreach ( $children as $child ) {
			$options[] = array(
				'id'    => (int) $child->term_id,
				'label' => str_repeat( '— ', $depth ) . $child->name,
				'depth' => $depth,
			);
			self::append_category_children( (int) $child->term_id, $depth + 1, $options );
		}
	}

	private static function active_expiry_query(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => '_promocode_expiry_date',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_promocode_expiry_date',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => '_promocode_expiry_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}

	private static function term_options( array $terms ): array {
		return array_map(
			static fn( WP_Term $term ): array => array(
				'id'    => (int) $term->term_id,
				'label' => $term->name,
				'depth' => 0,
			),
			$terms
		);
	}

	private static function cache_key( string $type, int $object_id ): string {
		$version = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
		return 'paf_context_' . $version . '_' . $type . '_' . max( 0, $object_id );
	}
}
