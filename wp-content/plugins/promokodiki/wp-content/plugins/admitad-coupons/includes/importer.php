<?php
/**
 * Backward-compatible import facades over the current pipeline.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return every plugin-owned scheduled hook.
 *
 * @return array<int,string>
 */
function admitad_cron_hooks(): array {
	return Promokodiki_Admitad_Plugin::cron_hooks();
}

/**
 * Register recurring jobs.
 */
function admitad_schedule_events(): void {
	Promokodiki_Admitad_Plugin::schedule();
}

/**
 * Acquire the deprecated import lock for external legacy callers.
 */
function admitad_acquire_import_lock(): bool {
	$now      = time();
	$existing = (int) get_option( 'admitad_import_lock', 0 );
	if ( $existing && $existing > $now - 1800 ) {
		return false;
	}
	if ( $existing ) {
		delete_option( 'admitad_import_lock' );
	}
	return add_option( 'admitad_import_lock', $now, '', false );
}

/**
 * Release the deprecated import lock.
 */
function admitad_release_import_lock(): void {
	delete_option( 'admitad_import_lock' );
}

/**
 * Return the canonical post ID for each imported external ID.
 *
 * @return array<string,int>
 */
function admitad_existing_coupon_map(): array {
	global $wpdb;

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table identifiers are provided by wpdb.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compatibility lookup is read-only and intentionally uncached.
	$rows = $wpdb->get_results(
		"SELECT pm.meta_value AS external_id, MIN(p.ID) AS post_id
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'admitad_coupon_id'
		WHERE p.post_type = 'promocode' AND p.post_status NOT IN ('trash', 'auto-draft')
		GROUP BY pm.meta_value"
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$map = array();
	foreach ( (array) $rows as $row ) {
		$map[ (string) $row->external_id ] = (int) $row->post_id;
	}
	return $map;
}

/**
 * Find or create one campaign shop by stable ID, falling back to exact name.
 *
 * @param array<string,mixed> $campaign  Campaign data.
 * @param string              $image_url Optional image.
 */
function admitad_find_or_create_shop( array $campaign, string $image_url = '' ): int {
	$campaign_id = sanitize_text_field( (string) ( $campaign['id'] ?? '' ) );
	$name        = sanitize_text_field( (string) ( $campaign['name'] ?? '' ) );
	if ( '' === $name ) {
		return 0;
	}

	$term_id = 0;
	if ( '' !== $campaign_id ) {
		$ids = get_terms(
			array(
				'taxonomy'   => 'shops_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Stable campaign ID is the canonical shop lookup.
				'meta_query' => array(
					array(
						'key'   => 'admitad_campaign_id',
						'value' => $campaign_id,
					),
				),
			)
		);
		if ( ! is_wp_error( $ids ) && $ids ) {
			$term_id = (int) $ids[0];
		}
	}
	if ( ! $term_id ) {
		$term = get_term_by( 'name', $name, 'shops_category' );
		if ( $term instanceof WP_Term ) {
			$term_id = (int) $term->term_id;
		} else {
			$created = wp_insert_term( $name, 'shops_category', array( 'slug' => sanitize_title( $name ) ) );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$term_id = (int) $created['term_id'];
		}
	}
	if ( '' !== $campaign_id ) {
		update_term_meta( $term_id, 'admitad_campaign_id', $campaign_id );
	}
	if ( ! empty( $campaign['site_url'] ) ) {
		update_term_meta( $term_id, 'shop_website', esc_url_raw( (string) $campaign['site_url'] ) );
	}
	if ( '' !== $image_url && ! get_term_meta( $term_id, 'image_url', true ) ) {
		update_term_meta( $term_id, 'image_url', esc_url_raw( $image_url ) );
	}
	return $term_id;
}

/**
 * Process one raw coupon through the complete current pipeline.
 *
 * @param array<string,mixed> $coupon      Raw API coupon.
 * @param array<string,int>   $existing_map Legacy external ID cache.
 * @return int|WP_Error
 */
function admitad_upsert_coupon( array $coupon, array &$existing_map ) {
	$result = ( new Promokodiki_Admitad_Import_Pipeline() )->process( $coupon, 0 );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$post_id = (int) ( $result['post_id'] ?? 0 );
	if ( $post_id <= 0 ) {
		return new WP_Error( 'coupon_ineligible', 'Coupon did not meet import eligibility rules.' );
	}
	$external_id = (string) ( $result['normalized']['external_id'] ?? '' );
	if ( '' !== $external_id ) {
		$existing_map[ $external_id ] = $post_id;
	}
	return $post_id;
}

/**
 * Start the resumable coupon synchronization.
 *
 * @return array{run_id:int,status:string}|WP_Error
 */
function update_admitad_coupons_data() {
	$run_id = ( new Promokodiki_Admitad_Sync_Coordinator() )->start_coupon_sync();
	if ( is_wp_error( $run_id ) ) {
		return $run_id;
	}
	return array(
		'run_id' => $run_id,
		'status' => 'scheduled',
	);
}
