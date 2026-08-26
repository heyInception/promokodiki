<?php
/**
 * Coupon reconciliation and public visibility integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
if ( ! class_exists( 'Promokodiki_Filter_Click_Stats' ) ) {
	require_once dirname( __DIR__, 3 ) . '/promokodiki-ajax-filter/promokodiki-ajax-filter.php';
	Promokodiki_Filter_Plugin::boot();
}

$post_ids = array();
$old_settings = get_option( 'promokodiki_admitad_settings', array() );

try {
	update_option(
		'promokodiki_admitad_settings',
		array_merge( (array) $old_settings, array( 'missing_threshold' => 2 ) ),
		false
	);
	Promokodiki_Admitad_Schema::install();
	$runs       = new Promokodiki_Admitad_Sync_Run_Repository();
	$reconciler = new Promokodiki_Admitad_Reconciler( $runs );
	$post_id    = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'Admitad reconciliation fixture',
		)
	);
	$post_ids[] = $post_id;
	update_post_meta( $post_id, 'admitad_coupon_id', 'reconcile-fixture' );
	update_post_meta( $post_id, '_promocode_is_active', 'yes' );

	Promokodiki_Admitad_Test_Harness::run(
		'incomplete runs never change coupon activity',
		static function () use ( $runs, $reconciler, $post_id ): void {
			$run_id = $runs->start( 'coupon' );
			$result = $reconciler->after_completed_run( $run_id );

			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $result ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_post_meta( $post_id, '_admitad_miss_count', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_promocode_is_active', true ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'two completed misses deactivate and a later sighting reactivates',
		static function () use ( $runs, $reconciler, $post_id ): void {
			$first = $runs->start( 'coupon' );
			$runs->complete( $first, array() );
			$reconciler->after_completed_run( $first );
			Promokodiki_Admitad_Test_Harness::assert_same( '1', get_post_meta( $post_id, '_admitad_miss_count', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_promocode_is_active', true ) );

			$second = $runs->start( 'coupon' );
			$runs->complete( $second, array() );
			$reconciler->after_completed_run( $second );
			Promokodiki_Admitad_Test_Harness::assert_same( '2', get_post_meta( $post_id, '_admitad_miss_count', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'no', get_post_meta( $post_id, '_promocode_is_active', true ) );

			$third = $runs->start( 'coupon' );
			update_post_meta( $post_id, '_admitad_last_seen_run_id', $third );
			$runs->complete( $third, array() );
			$result = $reconciler->after_completed_run( $third );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_promocode_is_active', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_post_meta( $post_id, '_admitad_miss_count', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $result['reactivated'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'inactive coupons stay singular but leave listings and popularity',
		static function () use ( $post_id ): void {
			global $wpdb;

			update_post_meta( $post_id, '_promocode_is_active', 'no' );
			$singular = new WP_Query(
				array(
					'p'         => $post_id,
					'post_type' => 'promocode',
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $post_id, (int) $singular->post->ID );

			$listing = new WP_Query(
				array(
					'post_type'      => 'promocode',
					'post__in'       => array( $post_id ),
					'posts_per_page' => 10,
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $listing->post_count );
			Promokodiki_Admitad_Test_Harness::assert_true(
				str_contains( Promokodiki_Admitad_Visibility::inactive_notice( $post_id ), 'promokodiki-admitad-inactive-notice' )
			);

			if ( class_exists( 'Promokodiki_Filter_Activator' ) ) {
				Promokodiki_Filter_Activator::activate();
			}
			$wpdb->insert(
				$wpdb->prefix . 'promokodiki_click_stats',
				array(
					'promocode_id' => $post_id,
					'click_date'   => current_time( 'Y-m-d' ),
					'clicks'       => 50,
				),
				array( '%d', '%s', '%d' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				! in_array( $post_id, Promokodiki_Filter_Click_Stats::ranked_ids( 7, 100, 0, true ), true )
			);
		}
	);
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	update_option( 'promokodiki_admitad_settings', $old_settings, false );
}

Promokodiki_Admitad_Test_Harness::finish();
