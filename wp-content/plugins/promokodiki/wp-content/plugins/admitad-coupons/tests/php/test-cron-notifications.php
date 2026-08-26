<?php
/**
 * CRON schedule and operational notification integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$hooks = Promokodiki_Admitad_Plugin::cron_hooks();
foreach ( $hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

try {
	Promokodiki_Admitad_Test_Harness::run(
		'configured recurring schedules are idempotent',
		static function (): void {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'update_admitad_coupons_event' );
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'update_admitad_shop_coupons_event' );
			Promokodiki_Admitad_Plugin::schedule();
			$first = wp_get_scheduled_event( 'promokodiki_admitad_coupon_sync' );
			Promokodiki_Admitad_Plugin::schedule();
			$second = wp_get_scheduled_event( 'promokodiki_admitad_coupon_sync' );

			Promokodiki_Admitad_Test_Harness::assert_true( is_object( $first ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $first->timestamp, $second->timestamp );
			Promokodiki_Admitad_Test_Harness::assert_same(
				(int) Promokodiki_Admitad_Config::get( 'coupon_interval' ),
				(int) $first->interval
			);
			Promokodiki_Admitad_Test_Harness::assert_true( false !== has_action( 'promokodiki_admitad_coupon_sync' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( false !== has_action( 'promokodiki_admitad_reference_sync' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( false !== has_action( 'promokodiki_admitad_reconcile' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( false !== has_action( 'promokodiki_admitad_retention' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( false, wp_next_scheduled( 'update_admitad_coupons_event' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( false, wp_next_scheduled( 'update_admitad_shop_coupons_event' ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'jobs delayed beyond two intervals create an admin warning',
		static function (): void {
			wp_clear_scheduled_hook( 'promokodiki_admitad_coupon_sync' );
			wp_schedule_event(
				time() - ( 3 * (int) Promokodiki_Admitad_Config::get( 'coupon_interval' ) ),
				'promokodiki_admitad_coupon',
				'promokodiki_admitad_coupon_sync'
			);
			$notifier = new Promokodiki_Admitad_Notifier();
			$delayed  = $notifier->check_delayed_jobs();

			Promokodiki_Admitad_Test_Harness::assert_true( in_array( 'coupon', $delayed, true ) );
			Promokodiki_Admitad_Test_Harness::assert_true(
				str_contains( Promokodiki_Admitad_Notifier::admin_notice_message(), 'задерж' )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'failure thresholds throttle email while OAuth alerts immediately',
		static function (): void {
			$sent  = array();
			$now   = 100000;
			$clock = static function () use ( &$now ): int {
				return $now;
			};
			$mail  = static function ( string $to, string $subject, string $message ) use ( &$sent ): bool {
				$sent[] = compact( 'to', 'subject', 'message' );
				return true;
			};
			$notifier = new Promokodiki_Admitad_Notifier( $mail, $clock );

			$notifier->record_failure( 'coupon', new WP_Error( 'api_failure', 'Temporary failure.' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, count( $sent ) );
			$notifier->record_failure( 'coupon', new WP_Error( 'api_failure', 'Temporary failure.' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $sent ) );
			$notifier->record_failure( 'coupon', new WP_Error( 'api_failure', 'Temporary failure.' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $sent ) );

			$notifier->record_failure( 'reference', new WP_Error( 'oauth_failed', 'OAuth rejected.' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, count( $sent ) );
			$notifier->record_success( 'coupon' );
			Promokodiki_Admitad_Test_Harness::assert_same(
				0,
				(int) get_option( 'promokodiki_admitad_failure_count_coupon', 0 )
			);
		}
	);
} finally {
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	foreach ( array( 'coupon', 'reference', 'reconcile' ) as $job ) {
		delete_option( 'promokodiki_admitad_failure_count_' . $job );
		delete_option( 'promokodiki_admitad_last_alert_' . $job );
	}
	delete_option( 'promokodiki_admitad_delayed_jobs' );
	Promokodiki_Admitad_Plugin::schedule();
}

Promokodiki_Admitad_Test_Harness::finish();
