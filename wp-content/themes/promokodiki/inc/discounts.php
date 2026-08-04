<?php
/**
 * Server-side Discounts query used when the AJAX filter plugin is unavailable.
 *
 * @package promokodiki
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function promokodiki_discounts_fallback_query( string $sort ): WP_Query {
	global $wpdb;

	$allowed_sort = array( 'popular', 'newest', 'discussed' );
	$sort         = sanitize_key( $sort );
	$sort         = in_array( $sort, $allowed_sort, true ) ? $sort : 'popular';

	$clauses_filter = static function ( array $clauses ) use ( $sort, $wpdb ): array {
		$expiry_expression = "(SELECT MAX(paf_expiry.meta_value) FROM {$wpdb->postmeta} paf_expiry
			WHERE paf_expiry.post_id = {$wpdb->posts}.ID
			AND paf_expiry.meta_key = '_promocode_expiry_date')";
		$usage_expression = "(SELECT MAX(paf_usage.meta_value + 0) FROM {$wpdb->postmeta} paf_usage
			WHERE paf_usage.post_id = {$wpdb->posts}.ID
			AND paf_usage.meta_key = '_promocode_used_count')";
		$votes_expression = "COALESCE(
			(SELECT MAX(paf_votes.meta_value + 0) FROM {$wpdb->postmeta} paf_votes
			 WHERE paf_votes.post_id = {$wpdb->posts}.ID AND paf_votes.meta_key = '_promocode_votes_total'),
			COALESCE((SELECT MAX(paf_likes.meta_value + 0) FROM {$wpdb->postmeta} paf_likes
			 WHERE paf_likes.post_id = {$wpdb->posts}.ID AND paf_likes.meta_key = '_promocode_likes'), 0)
			+ COALESCE((SELECT MAX(paf_dislikes.meta_value + 0) FROM {$wpdb->postmeta} paf_dislikes
			 WHERE paf_dislikes.post_id = {$wpdb->posts}.ID AND paf_dislikes.meta_key = '_promocode_dislikes'), 0)
		)";
		$today = esc_sql( current_time( 'Y-m-d' ) );

		$clauses['where'] .= " AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} paf_activity
			WHERE paf_activity.post_id = {$wpdb->posts}.ID
			AND paf_activity.meta_key = '_promocode_is_active'
			AND paf_activity.meta_value = 'no'
		)";
		$clauses['where'] .= " AND ({$expiry_expression} IS NULL OR {$expiry_expression} = '' OR {$expiry_expression} >= '{$today}')";

		switch ( $sort ) {
			case 'newest':
				$order = "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
				break;
			case 'discussed':
				$order = "{$votes_expression} DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
				break;
			case 'popular':
			default:
				$order = "COALESCE({$usage_expression}, 0) DESC, {$wpdb->posts}.ID DESC";
		}

		$clauses['orderby'] = $order;
		return $clauses;
	};

	add_filter( 'posts_clauses', $clauses_filter, 20, 2 );
	try {
		return new WP_Query(
			array(
				'post_type'           => 'promocode',
				'post_status'         => 'publish',
				'posts_per_page'      => 6,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	} finally {
		remove_filter( 'posts_clauses', $clauses_filter, 20 );
	}
}
