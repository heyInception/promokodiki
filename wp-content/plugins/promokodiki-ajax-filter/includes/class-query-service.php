<?php
/**
 * Consistent filtered promocode queries.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Query_Service {
	public static function run( array $state, array $context, array $settings ): array|WP_Error {
		$validation = self::validate_selection( $state, $context );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$page   = max( 1, (int) ( $state['page'] ?? 1 ) );
		$limit  = 1 === $page ? (int) $settings['initial_count'] : (int) $settings['load_more_count'];
		$offset = 1 === $page ? 0 : (int) $settings['initial_count'] + ( ( $page - 2 ) * (int) $settings['load_more_count'] );
		$limit  = max( 1, min( 100, $limit ) );

		if ( ! empty( $state['popular'] ) ) {
			return self::run_weekly( $state, $settings, $page, $limit, $offset );
		}

		$args = array(
			'post_type'           => 'promocode',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit + 1,
			'offset'              => $offset,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		$tax_query = self::tax_query( $state, $context );
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$sort         = sanitize_key( (string) ( $state['sort'] ?? 'newest' ) );
		$show_expired = ! empty( $settings['show_expired'] );
		$filter       = self::clause_filter( $sort, $show_expired );

		add_filter( 'posts_clauses', $filter, 20, 2 );
		try {
			$query = new WP_Query( $args );
		} finally {
			remove_filter( 'posts_clauses', $filter, 20 );
		}

		$posts    = $query->posts;
		$has_more = count( $posts ) > $limit;
		if ( $has_more ) {
			array_pop( $posts );
		}

		return array(
			'posts'    => $posts,
			'page'     => $page,
			'has_more' => $has_more,
			'total'    => (int) $query->found_posts,
		);
	}

	private static function run_weekly( array $state, array $settings, int $page, int $limit, int $offset ): array {
		$days            = (int) $settings['popular_days'];
		$include_expired = ! empty( $settings['show_expired'] );
		$ranked_ids      = Promokodiki_Filter_Click_Stats::ranked_ids( $days, $limit + 1, $offset, $include_expired );
		$has_more       = count( $ranked_ids ) > $limit;
		if ( $has_more ) {
			array_pop( $ranked_ids );
		}

		if ( ! $ranked_ids ) {
			$posts = array();
		} else {
			$query = new WP_Query(
				array(
					'post_type'           => 'promocode',
					'post_status'         => 'publish',
					'post__in'            => $ranked_ids,
					'orderby'             => 'post__in',
					'posts_per_page'      => count( $ranked_ids ),
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);
			$posts = $query->posts;
		}

		return array(
			'posts'    => $posts,
			'page'     => $page,
			'has_more' => $has_more,
			'total'    => Promokodiki_Filter_Click_Stats::ranked_count( $days, $include_expired ),
		);
	}

	private static function validate_selection( array $state, array $context ): true|WP_Error {
		$category_id = (int) ( $state['category_id'] ?? 0 );
		$brand_id    = (int) ( $state['brand_id'] ?? 0 );
		if ( $category_id && ! in_array( $category_id, $context['allowed_category_ids'] ?? array(), true ) ) {
			return new WP_Error( 'disallowed_filter_category', __( 'This category is not available in the current context.', 'promokodiki-ajax-filter' ) );
		}
		if ( $brand_id && ! in_array( $brand_id, $context['allowed_brand_ids'] ?? array(), true ) ) {
			return new WP_Error( 'disallowed_filter_brand', __( 'This brand is not available in the current context.', 'promokodiki-ajax-filter' ) );
		}
		return true;
	}

	private static function tax_query( array $state, array $context ): array {
		$clauses = array();
		$type    = (string) $context['type'];

		if ( 'home' === $type && ! empty( $state['category_id'] ) ) {
			$clauses[] = self::term_clause( 'promocode_category', (int) $state['category_id'], true );
		}
		if ( 'category' === $type ) {
			$term_id   = ! empty( $state['category_id'] ) ? (int) $state['category_id'] : (int) $context['object_id'];
			$clauses[] = self::term_clause( 'promocode_category', $term_id, true );
		}
		if ( 'shop' === $type ) {
			$clauses[] = self::term_clause( 'shops_category', (int) $context['object_id'], false );
		}
		if ( ! empty( $state['brand_id'] ) ) {
			$brand_taxonomy = isset( $context['brand_taxonomy'] ) && taxonomy_exists( $context['brand_taxonomy'] )
				? $context['brand_taxonomy']
				: 'shops_category';
			$clauses[] = self::term_clause( $brand_taxonomy, (int) $state['brand_id'], false );
		}

		if ( count( $clauses ) > 1 ) {
			$clauses['relation'] = 'AND';
		}
		return $clauses;
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

	private static function clause_filter( string $sort, bool $show_expired ): Closure {
		return static function ( array $clauses ) use ( $sort, $show_expired ): array {
			global $wpdb;

			$expiry = "(SELECT MAX(paf_expiry.meta_value) FROM {$wpdb->postmeta} paf_expiry
				WHERE paf_expiry.post_id = {$wpdb->posts}.ID
				AND paf_expiry.meta_key = '_promocode_expiry_date')";
			$today  = esc_sql( current_time( 'Y-m-d' ) );
			$is_expired = "CASE WHEN {$expiry} IS NOT NULL AND {$expiry} <> '' AND {$expiry} < '{$today}' THEN 1 ELSE 0 END";

			if ( ! $show_expired ) {
				$clauses['where'] .= " AND ({$expiry} IS NULL OR {$expiry} = '' OR {$expiry} >= '{$today}')";
			}

			$order = array();
			if ( $show_expired ) {
				$order[] = $is_expired . ' ASC';
			}

			switch ( $sort ) {
				case 'oldest':
					$order[] = "{$wpdb->posts}.post_date ASC";
					$order[] = "{$wpdb->posts}.ID ASC";
					break;
				case 'popular':
					$usage = "(SELECT MAX(paf_usage.meta_value + 0) FROM {$wpdb->postmeta} paf_usage
						WHERE paf_usage.post_id = {$wpdb->posts}.ID
						AND paf_usage.meta_key = '_promocode_used_count')";
					$order[] = "COALESCE({$usage}, 0) DESC";
					$order[] = "{$wpdb->posts}.ID DESC";
					break;
				case 'expiring':
					$order[] = "CASE WHEN {$expiry} IS NULL OR {$expiry} = '' THEN 1 ELSE 0 END ASC";
					$order[] = $expiry . ' ASC';
					$order[] = "{$wpdb->posts}.ID DESC";
					break;
				case 'newest':
				default:
					$order[] = "{$wpdb->posts}.post_date DESC";
					$order[] = "{$wpdb->posts}.ID DESC";
					break;
			}

			$clauses['orderby'] = implode( ', ', $order );
			return $clauses;
		};
	}
}
