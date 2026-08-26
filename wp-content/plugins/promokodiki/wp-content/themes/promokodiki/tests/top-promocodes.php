<?php
/** Telegram top snapshot integration tests. */

require_once dirname( __DIR__, 3 ) . '/plugins/promokodiki-ajax-filter/tests/harness.php';
require_once dirname( __DIR__ ) . '/inc/top.php';

$post_ids       = array();
$original_count = get_option( 'popular_promocodes_count', null );
$original_cache = get_option( 'promokodiki_top_snapshot_v2', null );
$now            = current_time( 'timestamp' );
$window_start   = intdiv( $now, 3 * HOUR_IN_SECONDS ) * ( 3 * HOUR_IN_SECONDS );

$create_offer = static function ( string $title, int $age_hours, bool $active, string $expiry, string $code ) use ( &$post_ids, $now ): int {
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_date'   => wp_date( 'Y-m-d H:i:s', time() - ( $age_hours * HOUR_IN_SECONDS ) ),
		)
	);
	$post_ids[] = $post_id;
	update_post_meta( $post_id, '_promocode_is_active', $active ? 'yes' : 'no' );
	update_post_meta( $post_id, '_promocode_expiry_date', $expiry );
	update_post_meta( $post_id, '_promocode_code', $code );
	update_post_meta( $post_id, '_promocode_link', 'https://shop.example/' . $post_id );
	return $post_id;
};

try {
	update_option( 'popular_promocodes_count', 3 );
	delete_option( 'promokodiki_top_snapshot_v2' );
	$future      = wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) );
	$past        = wp_date( 'Y-m-d', $now - DAY_IN_SECONDS );
	$fresh_id    = $create_offer( 'Fresh code', 2, true, $future, 'FRESH' );
	$popular_id  = $create_offer( 'Popular code', 240, true, $future, 'POPULAR' );
	$second_id   = $create_offer( 'Second code', 300, true, $future, 'SECOND' );
	$discount_id = $create_offer( 'No-code discount', 240, true, '', '' );
	$spare_id    = $create_offer( 'Spare code', 400, true, $future, 'SPARE' );
	$expired_id  = $create_offer( 'Expired', 1, true, $past, 'EXPIRED' );
	$inactive_id = $create_offer( 'Inactive', 1, false, $future, 'INACTIVE' );

	global $wpdb;
	foreach ( array( $popular_id => 20, $second_id => 10, $discount_id => 30, $spare_id => 5 ) as $post_id => $clicks ) {
		$wpdb->replace(
			$wpdb->prefix . 'promokodiki_click_stats',
			array( 'promocode_id' => $post_id, 'click_date' => current_time( 'Y-m-d' ), 'clicks' => $clicks ),
			array( '%d', '%s', '%d' )
		);
	}

	Promokodiki_Filter_Test_Harness::run(
		'top snapshot is shared inside a window and reserves a fresh eligible offer',
		static function () use ( $window_start, $fresh_id, $expired_id, $inactive_id ): void {
			$first  = promokodiki_top_snapshot( $window_start + 60, true );
			$stable = promokodiki_top_snapshot( $window_start + 600 );
			Promokodiki_Filter_Test_Harness::assert_same( $first['ids'], $stable['ids'] );
			Promokodiki_Filter_Test_Harness::assert_same( 3, count( $first['ids'] ) );
			Promokodiki_Filter_Test_Harness::assert_same( $window_start + ( 3 * HOUR_IN_SECONDS ), $first['next_update'] );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $fresh_id, $first['ids'], true ), 'Fresh offer was not reserved: ' . wp_json_encode( $first['ids'] ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $expired_id, $first['ids'], true ), 'Expired offer leaked into snapshot' );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $inactive_id, $first['ids'], true ), 'Inactive offer leaked into snapshot' );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'top snapshot rotates between windows when the eligible pool is larger',
		static function () use ( $window_start ): void {
			$first = promokodiki_top_snapshot( $window_start + 60, true );
			$next  = promokodiki_top_snapshot( $window_start + ( 3 * HOUR_IN_SECONDS ) + 60, true );
			Promokodiki_Filter_Test_Harness::assert_true( $first['ids'] !== $next['ids'] );
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	if ( null === $original_count ) {
		delete_option( 'popular_promocodes_count' );
	} else {
		update_option( 'popular_promocodes_count', $original_count );
	}
	if ( null === $original_cache ) {
		delete_option( 'promokodiki_top_snapshot_v2' );
	} else {
		update_option( 'promokodiki_top_snapshot_v2', $original_cache );
	}
}
