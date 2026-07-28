<?php
/**
 * Suspected duplicate detection integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$post_ids   = array();
$external_a = 'duplicate-a-' . wp_generate_password( 8, false );
$external_b = 'duplicate-b-' . wp_generate_password( 8, false );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$existing_id = wp_insert_post(
		array(
			'post_type'    => 'promocode',
			'post_status'  => 'publish',
			'post_title'   => 'Скидка на беговые кроссовки!',
			'post_content' => '',
		)
	);
	$post_ids[] = $existing_id;
	update_post_meta( $existing_id, 'admitad_coupon_id', $external_a );
	update_post_meta( $existing_id, 'campaign_id', '7775' );
	update_post_meta( $existing_id, '_promocode_code', 'RUN-10' );
	update_post_meta( $existing_id, 'date_start', '2026-07-01T00:00:00' );
	update_post_meta( $existing_id, 'date_end', '2026-08-31T23:59:59' );
	update_post_meta( $existing_id, '_promocode_is_active', 'no' );

	$base_coupon = array(
		'external_id'        => $external_a,
		'source_status'      => 'active',
		'title'              => 'Скидка на беговые-кроссовки',
		'description'        => '',
		'short_name'         => '',
		'campaign'           => array( 'id' => '7775', 'name' => 'Test campaign', 'site_url' => '' ),
		'categories'         => array(),
		'types'              => array(),
		'species'            => 'promocode',
		'promocode'          => 'RUN-10',
		'goto_link'          => 'https://example.test/duplicate',
		'frameset_link'      => '',
		'image_url'          => '',
		'date_start'         => '2026-08-01T00:00:00',
		'date_end'           => '2026-09-30T23:59:59',
		'discount'           => '10%',
		'regions'            => array( 'ru' ),
		'has_affiliate_link' => true,
		'payload_hash'       => hash( 'sha256', $external_a . '-changed' ),
	);

	Promokodiki_Admitad_Test_Harness::run(
		'the same Admitad ID updates even when currently inactive',
		static function () use ( $base_coupon, $existing_id ): void {
			$result = ( new Promokodiki_Admitad_Coupon_Repository() )->upsert( $base_coupon, 30 );
			Promokodiki_Admitad_Test_Harness::assert_same( $existing_id, $result['post_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'updated', $result['state'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'different IDs with matching campaign, code, title, and dates stay separate and queue',
		static function () use ( $base_coupon, $external_b, $existing_id, &$post_ids ): void {
			global $wpdb;

			$coupon                 = $base_coupon;
			$coupon['external_id']  = $external_b;
			$coupon['payload_hash'] = hash( 'sha256', $external_b );
			$detector               = new Promokodiki_Admitad_Duplicate_Detector();
			Promokodiki_Admitad_Test_Harness::assert_same( array( $existing_id ), $detector->find( $coupon ) );

			$result     = ( new Promokodiki_Admitad_Coupon_Repository() )->upsert( $coupon, 31 );
			$post_ids[] = $result['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_true( $result['post_id'] !== $existing_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'created', $result['state'] );
			$table = Promokodiki_Admitad_Schema::table( 'review_queue' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The test verifies its own row in the plugin-owned queue table; values are prepared.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE entity_type = %s AND entity_id = %s AND reason_code = %s AND status = %s",
					'coupon',
					$external_b,
					'suspected_duplicate',
					'open'
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $count );
		}
	);
} finally {
	global $wpdb;
	$orphan_ids = get_posts(
		array(
			'post_type'                     => 'promocode',
			'post_status'                   => 'any',
			'posts_per_page'                => -1,
			'fields'                        => 'ids',
			'promokodiki_include_inactive' => true,
			'meta_query'                    => array(
				'relation' => 'OR',
				array( 'key' => 'admitad_coupon_id', 'value' => 'duplicate-a-', 'compare' => 'LIKE' ),
				array( 'key' => 'admitad_coupon_id', 'value' => 'duplicate-b-', 'compare' => 'LIKE' ),
			),
		)
	);
	$post_ids = array_merge( $post_ids, array_map( 'intval', $orphan_ids ) );
	foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'review_queue' ), array( 'entity_id' => $external_b ), array( '%s' ) );
}

Promokodiki_Admitad_Test_Harness::finish();
