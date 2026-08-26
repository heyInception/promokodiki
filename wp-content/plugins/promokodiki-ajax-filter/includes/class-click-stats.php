<?php
/**
 * Daily click aggregation and weekly rankings.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists click totals and builds bounded popularity rankings.
 */
final class Promokodiki_Filter_Click_Stats {
	/**
	 * Meta key holding the count that existed before this plugin tracked clicks.
	 */
	private const BASELINE_META_KEY = '_promokodiki_filter_click_baseline';

	/**
	 * Increment the daily and lifetime counters for a published promocode.
	 *
	 * @param int $post_id Promocode post ID.
	 * @return int|WP_Error Updated total or failure.
	 * @throws RuntimeException When the atomic counter update fails.
	 */
	public static function increment( int $post_id ): int|WP_Error {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post || 'promocode' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'invalid_promocode', __( 'Only published promocodes can be counted.', 'promokodiki-ajax-filter' ) );
		}

		$baseline = get_post_meta( $post_id, self::BASELINE_META_KEY, true );
		if ( '' === $baseline ) {
			$existing = max( 0, (int) get_post_meta( $post_id, '_promocode_used_count', true ) );
			add_post_meta( $post_id, self::BASELINE_META_KEY, $existing, true );
			$baseline = get_post_meta( $post_id, self::BASELINE_META_KEY, true );
		}

		$table = $wpdb->prefix . 'promokodiki_click_stats';
		$date  = current_time( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic counters require the plugin-owned custom table; table identifiers cannot be value placeholders.
		$wpdb->query( 'START TRANSACTION' );
		try {
			$upserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (promocode_id, click_date, clicks)
					VALUES (%d, %s, 1)
					ON DUPLICATE KEY UPDATE clicks = clicks + 1",
					$post_id,
					$date
				)
			);
			if ( false === $upserted ) {
				throw new RuntimeException( $wpdb->last_error ? $wpdb->last_error : 'Click row could not be updated.' );
			}

			$plugin_clicks = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(clicks) FROM {$table} WHERE promocode_id = %d",
					$post_id
				)
			);
			$new_total     = max( 0, (int) $baseline ) + $plugin_clicks;
			update_post_meta( $post_id, '_promocode_used_count', $new_total );
			$wpdb->query( 'COMMIT' );

			return $new_total;
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'click_tracking_failed', $throwable->getMessage() );
		}
		// phpcs:enable
	}

	/**
	 * Return popularity-ranked active promocode IDs.
	 *
	 * @param int  $days            Rolling date window.
	 * @param int  $limit           Maximum results.
	 * @param int  $offset          Result offset.
	 * @param bool $include_expired Whether expired coupons remain eligible.
	 * @return array<int, int>
	 */
	public static function ranked_ids( int $days, int $limit, int $offset, bool $include_expired ): array {
		global $wpdb;

		$days   = max( 1, min( 31, $days ) );
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );
		$table  = $wpdb->prefix . 'promokodiki_click_stats';
		$start  = wp_date( 'Y-m-d', current_datetime()->getTimestamp() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		$today  = current_time( 'Y-m-d' );

		$expiry_sql = '';
		$params     = array( $start );
		if ( ! $include_expired ) {
			$expiry_sql = "AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} expiry
				WHERE expiry.post_id = p.ID
				AND expiry.meta_key = '_promocode_expiry_date'
				AND expiry.meta_value <> ''
				AND expiry.meta_value < %s
			)";
			$params[]   = $today;
		}
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT stats.promocode_id
			FROM {$table} stats
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
			{$expiry_sql}
			GROUP BY stats.promocode_id
			ORDER BY SUM(stats.clicks) DESC, stats.promocode_id DESC
			LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The query uses plugin-owned table identifiers and a bounded optional clause; all values use placeholders.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Count popularity-ranked active promocodes.
	 *
	 * @param int  $days            Rolling date window.
	 * @param bool $include_expired Whether expired coupons remain eligible.
	 */
	public static function ranked_count( int $days, bool $include_expired ): int {
		global $wpdb;

		$days  = max( 1, min( 31, $days ) );
		$table = $wpdb->prefix . 'promokodiki_click_stats';
		$start = wp_date( 'Y-m-d', current_datetime()->getTimestamp() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		$today = current_time( 'Y-m-d' );

		$expiry_sql = '';
		$params     = array( $start );
		if ( ! $include_expired ) {
			$expiry_sql = "AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} expiry
				WHERE expiry.post_id = p.ID
				AND expiry.meta_key = '_promocode_expiry_date'
				AND expiry.meta_value <> ''
				AND expiry.meta_value < %s
			)";
			$params[]   = $today;
		}

		$sql = "SELECT COUNT(DISTINCT stats.promocode_id)
			FROM {$table} stats
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
			{$expiry_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The query uses plugin-owned table identifiers and a bounded optional clause; all values use placeholders.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count tracked clicks for one promocode and date window.
	 *
	 * @param int $post_id Promocode post ID.
	 * @param int $days    Rolling date window.
	 */
	public static function count_for_post( int $post_id, int $days ): int {
		global $wpdb;
		$days  = max( 1, min( 31, $days ) );
		$start = wp_date( 'Y-m-d', current_datetime()->getTimestamp() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		$table = $wpdb->prefix . 'promokodiki_click_stats';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin-owned table identifier cannot be a value placeholder.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(clicks), 0) FROM {$table} WHERE promocode_id = %d AND click_date >= %s", $post_id, $start ) );
	}
}
