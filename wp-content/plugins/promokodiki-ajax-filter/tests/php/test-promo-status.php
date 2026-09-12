<?php
/** Promocode badge status integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

Promokodiki_Filter_Test_Harness::run(
	'promocode statuses are provided by a dedicated service',
	static function (): void {
		Promokodiki_Filter_Test_Harness::assert_true(
			class_exists( 'Promokodiki_Filter_Promo_Status' )
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'interaction defaults support new badge rules',
	static function (): void {
		$settings = Promokodiki_Filter_Settings::defaults();

		Promokodiki_Filter_Test_Harness::assert_same( 14, $settings['new_days'] ?? null );
		Promokodiki_Filter_Test_Harness::assert_same( 24, $settings['usage_cooldown_hours'] ?? null );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $settings['popular_min_clicks'] ?? null );
	}
);

$fixtures = array();
try {
	$expired = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Expired status fixture' ) );
	update_post_meta( $expired, '_promocode_expiry_date', wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( 7 * DAY_IN_SECONDS ) ) );
	$fixtures[] = $expired;
	$old_expired = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Old expired status fixture' ) );
	update_post_meta( $old_expired, '_promocode_expiry_date', wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( 8 * DAY_IN_SECONDS ) ) );
	$fixtures[] = $old_expired;
	$ends_today = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Ends today status fixture' ) );
	update_post_meta( $ends_today, '_promocode_expiry_date', current_time( 'Y-m-d' ) );
	$fixtures[] = $ends_today;
	$new = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'New status fixture' ) );
	$fixtures[] = $new;
	$popular = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_date' => '2020-01-01 00:00:00', 'post_title' => 'Popular status fixture' ) );
	$fixtures[] = $popular;
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $popular, 'click_date' => current_time( 'Y-m-d' ), 'clicks' => 1 ), array( '%d', '%s', '%d' ) );
	Promokodiki_Filter_Test_Harness::run( 'expired badge takes priority over new', static function () use ( $expired ): void {
		Promokodiki_Filter_Test_Harness::assert_same( 'expired', Promokodiki_Filter_Promo_Status::for_post( $expired ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'expiry date stays active through the end of its site-calendar day', static function () use ( $ends_today ): void {
		Promokodiki_Filter_Test_Harness::assert_true( Promokodiki_Filter_Promo_Status::is_recommendable( $ends_today ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'seven-day expired offer remains in general listings but not recommendations', static function () use ( $expired ): void {
		Promokodiki_Filter_Test_Harness::assert_true( Promokodiki_Filter_Promo_Status::is_listing_visible( $expired ) );
		Promokodiki_Filter_Test_Harness::assert_true( ! Promokodiki_Filter_Promo_Status::is_recommendable( $expired ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'eight-day expired offer leaves general listings', static function () use ( $old_expired ): void {
		Promokodiki_Filter_Test_Harness::assert_true( ! Promokodiki_Filter_Promo_Status::is_listing_visible( $old_expired ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'missing expiry uses an honest label', static function () use ( $new ): void {
		Promokodiki_Filter_Test_Harness::assert_same( 'Срок не указан', Promokodiki_Filter_Promo_Status::expiry_label( $new ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'stored expiry time cannot move the displayed calendar date', static function () use ( $ends_today ): void {
		update_post_meta( $ends_today, '_promocode_expiry_date', current_time( 'Y-m-d' ) . ' 23:59:59' );
		Promokodiki_Filter_Test_Harness::assert_same( wp_date( 'd.m.Y' ), Promokodiki_Filter_Promo_Status::expiry_label( $ends_today ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'recent promocode receives new badge', static function () use ( $new ): void {
		Promokodiki_Filter_Test_Harness::assert_same( 'new', Promokodiki_Filter_Promo_Status::for_post( $new ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'weekly click threshold receives popular badge', static function () use ( $popular ): void {
		Promokodiki_Filter_Test_Harness::assert_same( 'popular', Promokodiki_Filter_Promo_Status::for_post( $popular ) );
	} );
} finally {
	foreach ( $fixtures as $fixture ) { $wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $fixture ), array( '%d' ) ); wp_delete_post( $fixture, true ); }
}

Promokodiki_Filter_Test_Harness::finish();
