<?php
/**
 * Compatible filter options for the home context.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Option_Service {
	private const CACHE_VERSION_OPTION = 'promokodiki_filter_context_cache_version';

	public static function build( array $context, array $state ): array|WP_Error {
		if ( 'home' !== ( $context['type'] ?? '' ) ) {
			return array(
				'state'            => $state,
				'category_options' => $context['category_options'] ?? array(),
				'brand_options'    => $context['brand_options'] ?? array(),
			);
		}

		$category_id = max( 0, (int) ( $state['category_id'] ?? 0 ) );
		$brand_id    = max( 0, (int) ( $state['brand_id'] ?? 0 ) );
		$state['category_id'] = $category_id;
		$state['brand_id']    = $brand_id;

		if ( $category_id && $brand_id && ! self::pair_exists( $category_id, $brand_id ) ) {
			$brand_id           = 0;
			$state['brand_id'] = 0;
		}

		$cached = get_transient( self::cache_key( $category_id, $brand_id ) );
		if ( is_array( $cached ) ) {
			return array(
				'state'            => $state,
				'category_options' => $cached['category_options'],
				'brand_options'    => $cached['brand_options'],
			);
		}

		$options = array(
			'category_options' => self::categories_for_brand( $context, $brand_id ),
			'brand_options'    => self::brands_for_category( $context, $category_id ),
		);
		set_transient( self::cache_key( $category_id, $brand_id ), $options, HOUR_IN_SECONDS );

		return array(
			'state'            => $state,
			'category_options' => $options['category_options'],
			'brand_options'    => $options['brand_options'],
		);
	}

	private static function pair_exists( int $category_id, int $brand_id ): bool {
		return ! empty(
			self::active_promocode_ids(
				array(
					'relation' => 'AND',
					self::term_clause( 'promocode_category', $category_id, true ),
					self::term_clause( 'shops_category', $brand_id, false ),
				)
			)
		);
	}

	private static function categories_for_brand( array $context, int $brand_id ): array {
		$tax_query = $brand_id
			? array( self::term_clause( 'shops_category', $brand_id, false ) )
			: array();
		$terms = self::category_terms_with_ancestors(
			self::terms_for_promocodes( self::active_promocode_ids( $tax_query ), 'promocode_category' )
		);

		return self::allowed_options( $terms, $context['allowed_category_ids'] ?? array() );
	}

	private static function brands_for_category( array $context, int $category_id ): array {
		$tax_query = $category_id
			? array( self::term_clause( 'promocode_category', $category_id, true ) )
			: array();
		$terms = self::terms_for_promocodes( self::active_promocode_ids( $tax_query ), 'shops_category' );

		return self::allowed_options( $terms, $context['allowed_brand_ids'] ?? array() );
	}

	private static function active_promocode_ids( array $tax_query ): array {
		$args = array(
			'post_type'              => 'promocode',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => self::active_expiry_query(),
		);
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	private static function terms_for_promocodes( array $post_ids, string $taxonomy ): array {
		if ( ! $post_ids ) {
			return array();
		}

		$terms = wp_get_object_terms( $post_ids, $taxonomy );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	private static function category_terms_with_ancestors( array $terms ): array {
		$terms_by_id = array();
		foreach ( $terms as $term ) {
			$terms_by_id[ (int) $term->term_id ] = $term;
			foreach ( get_ancestors( (int) $term->term_id, 'promocode_category', 'taxonomy' ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'promocode_category' );
				if ( $ancestor instanceof WP_Term ) {
					$terms_by_id[ (int) $ancestor->term_id ] = $ancestor;
				}
			}
		}

		return array_values( $terms_by_id );
	}

	private static function allowed_options( array $terms, array $allowed_ids ): array {
		$allowed_ids = array_map( 'intval', $allowed_ids );
		$terms       = array_values(
			array_filter(
				$terms,
				static fn( WP_Term $term ): bool => in_array( (int) $term->term_id, $allowed_ids, true )
			)
		);
		usort(
			$terms,
			static fn( WP_Term $left, WP_Term $right ): int => strnatcasecmp( $left->name, $right->name )
		);

		return array_map(
			static fn( WP_Term $term ): array => array(
				'id'    => (int) $term->term_id,
				'label' => $term->name,
				'depth' => 0,
			),
			$terms
		);
	}

	private static function term_clause( string $taxonomy, int $term_id, bool $include_children ): array {
		return array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => $include_children,
			'operator'         => 'IN',
		);
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

	private static function cache_key( int $category_id, int $brand_id ): string {
		$version = (int) get_option( self::CACHE_VERSION_OPTION, 1 );
		return 'paf_options_' . $version . '_' . $category_id . '_' . $brand_id;
	}
}
