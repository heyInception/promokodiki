<?php
/**
 * Server-side Discounts query used when the AJAX filter plugin is unavailable.
 *
 * @package promokodiki
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the plugin-off Discounts query.
 *
 * @param string $sort Requested Discounts sort.
 * @return WP_Query
 */
function promokodiki_discounts_fallback_query( $sort ) {
	global $wpdb;

	$allowed_sort = array( 'popular', 'newest', 'discussed' );
	$sort         = sanitize_key( $sort );
	$sort         = in_array( $sort, $allowed_sort, true ) ? $sort : 'popular';
	$click_table  = $wpdb->prefix . 'promokodiki_click_stats';
	$weekly_start = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) );
	$use_weekly   = false;

	if ( 'popular' === $sort ) {
		// A retained plugin table is optional in fallback mode, so verify it before querying.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema discovery cannot use the object cache.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $click_table ) ) );
		if ( $click_table === $table_exists ) {
			$today = current_time( 'Y-m-d' );
			$sql   = "SELECT COUNT(DISTINCT stats.promocode_id)
				FROM {$click_table} stats
				INNER JOIN {$wpdb->posts} p ON p.ID = stats.promocode_id
				WHERE stats.click_date >= %s
				AND p.post_type = 'promocode'
				AND p.post_status = 'publish'
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} activity
					WHERE activity.post_id = p.ID
					AND activity.meta_key = '_promocode_is_active'
					AND activity.meta_value = 'no'
				)
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} expiry
					WHERE expiry.post_id = p.ID
					AND expiry.meta_key = '_promocode_expiry_date'
					AND expiry.meta_value <> ''
					AND expiry.meta_value < %s
				)";
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The optional table identifier is verified above; all values use placeholders.
			$use_weekly = 0 < (int) $wpdb->get_var( $wpdb->prepare( $sql, $weekly_start, $today ) );
		}
	}

	$clauses_filter = static function ( $clauses ) use ( $sort, $wpdb, $click_table, $weekly_start, $use_weekly ) {
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
				if ( $use_weekly ) {
					$weekly_start_sql = esc_sql( $weekly_start );
					$weekly_expression = "(SELECT SUM(paf_weekly.clicks) FROM {$click_table} paf_weekly
						WHERE paf_weekly.promocode_id = {$wpdb->posts}.ID
						AND paf_weekly.click_date >= '{$weekly_start_sql}')";
					$clauses['where'] .= " AND EXISTS (
						SELECT 1 FROM {$click_table} paf_weekly_eligible
						WHERE paf_weekly_eligible.promocode_id = {$wpdb->posts}.ID
						AND paf_weekly_eligible.click_date >= '{$weekly_start_sql}'
					)";
					$order = "COALESCE({$weekly_expression}, 0) DESC, {$wpdb->posts}.ID DESC";
				} else {
					$order = "COALESCE({$usage_expression}, 0) DESC, {$wpdb->posts}.ID DESC";
				}
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
