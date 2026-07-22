<?php
/**
 * Daily click aggregation and weekly rankings.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Click_Stats {
	private const BASELINE_META_KEY = '_promokodiki_filter_click_baseline';

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
				throw new RuntimeException( $wpdb->last_error ?: 'Click row could not be updated.' );
			}

			$plugin_clicks = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(clicks) FROM {$table} WHERE promocode_id = %d",
					$post_id
				)
			);
			$new_total = max( 0, (int) $baseline ) + $plugin_clicks;
			update_post_meta( $post_id, '_promocode_used_count', $new_total );
			$wpdb->query( 'COMMIT' );

			return $new_total;
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'click_tracking_failed', $throwable->getMessage() );
		}
	}

	public static function ranked_ids( int $days, int $limit, int $offset, bool $include_expired ): array {
		global $wpdb;

		$days   = max( 1, min( 31, $days ) );
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );
		$table  = $wpdb->prefix . 'promokodiki_click_stats';
		$start  = wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
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
			$params[] = $today;
		}
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT stats.promocode_id
			FROM {$table} stats
			INNER JOIN {$wpdb->posts} p ON p.ID = stats.promocode_id
			WHERE stats.click_date >= %s
			AND p.post_type = 'promocode'
			AND p.post_status = 'publish'
			{$expiry_sql}
			GROUP BY stats.promocode_id
			ORDER BY SUM(stats.clicks) DESC, stats.promocode_id DESC
			LIMIT %d OFFSET %d";

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
	}
}
