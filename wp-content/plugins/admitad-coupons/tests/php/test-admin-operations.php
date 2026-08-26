<?php
/**
 * Operations dashboard and diagnostics security tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user    = get_current_user_id();
$user_ids    = array();
$run_ids     = array();
$option_keys = array(
	'promokodiki_admitad_client_secret',
	'admitad_access_token',
	'promokodiki_admitad_lock_coupon',
);
$old_options = array();
foreach ( $option_keys as $option_key ) {
	$old_options[ $option_key ] = get_option( $option_key, '__missing__' );
}

try {
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	Promokodiki_Admitad_Plugin::schedule();
	$admin_id   = wp_insert_user(
		array(
			'user_login' => 'admitad-ops-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$user_ids[] = $admin_id;
	wp_set_current_user( $admin_id );

	Promokodiki_Admitad_Test_Harness::run(
		'diagnostics exposes schema, cron, runs, and queue without credentials',
		static function () use ( &$run_ids ): void {
			update_option( 'promokodiki_admitad_client_secret', 'diagnostic-secret', false );
			update_option( 'admitad_access_token', 'Bearer diagnostic-token', false );
			$runs   = new Promokodiki_Admitad_Sync_Run_Repository();
			$run_id = $runs->start( 'coupon' );
			$run_ids[] = $run_id;
			$runs->fail( $run_id, new WP_Error( 'api_error', 'Authorization: Bearer diagnostic-token' ) );

			$snapshot = Promokodiki_Admitad_Diagnostics::snapshot();
			$json     = wp_json_encode( $snapshot );
			Promokodiki_Admitad_Test_Harness::assert_same( '5', (string) $snapshot['schema_version'] );
			Promokodiki_Admitad_Test_Harness::assert_true( isset( $snapshot['cron']['coupon_sync'] ) );
			Promokodiki_Admitad_Test_Harness::assert_true( isset( $snapshot['locks']['coupon'] ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $json, 'diagnostic-secret' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $json, 'diagnostic-token' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $json, 'Bearer ' ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'manual operations require nonce and recover only stale locks',
		static function (): void {
			$actions = new Promokodiki_Admitad_Admin_Actions();
			$invalid = $actions->run_operation( 'recover_coupon_lock', 'invalid-nonce' );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_nonce', $invalid->get_error_code() );

			update_option(
				'promokodiki_admitad_lock_coupon',
				array(
					'owner'     => 'stale-owner',
					'acquired'  => time() - 1000,
					'heartbeat' => time() - 1000,
					'ttl'       => 10,
				),
				false
			);
			$result = $actions->run_operation(
				'recover_coupon_lock',
				wp_create_nonce( 'promokodiki_admitad_operation' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( true === $result );
			Promokodiki_Admitad_Test_Harness::assert_same( false, get_option( 'promokodiki_admitad_lock_coupon', false ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'test email action is nonce protected and routed without exposing mail data',
		static function (): void {
			$called = false;
			$filter = static function ( $return ) use ( &$called ) {
				$called = true;
				return true;
			};
			add_filter( 'pre_wp_mail', $filter );
			try {
				$result = ( new Promokodiki_Admitad_Admin_Actions() )->run_operation(
					'test_email',
					wp_create_nonce( 'promokodiki_admitad_operation' )
				);
				Promokodiki_Admitad_Test_Harness::assert_true( true === $result );
				Promokodiki_Admitad_Test_Harness::assert_true( $called );
				Promokodiki_Admitad_Test_Harness::assert_true(
					false !== has_action( 'admin_post_promokodiki_admitad_operation', array( 'Promokodiki_Admitad_Admin_Actions', 'handle_operation' ) )
				);
			} finally {
				remove_filter( 'pre_wp_mail', $filter );
			}
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'overview, sync, and diagnostics pages render safe operational data',
		static function (): void {
			foreach ( array( 'Promokodiki_Admitad_Overview_Page', 'Promokodiki_Admitad_Sync_Page', 'Promokodiki_Admitad_Diagnostics_Page' ) as $class_name ) {
				ob_start();
				( new $class_name() )->render();
				$html = (string) ob_get_clean();
				Promokodiki_Admitad_Test_Harness::assert_true( '' !== $html );
				Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, 'diagnostic-secret' ) );
				Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, 'diagnostic-token' ) );
				Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, 'delete=' ) );
			}
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'sync forms retain the allowlisted AJAX operation contract',
		static function (): void {
			ob_start();
			( new Promokodiki_Admitad_Sync_Page() )->render();
			$html = (string) ob_get_clean();
			Promokodiki_Admitad_Test_Harness::assert_true( 4 === substr_count( $html, 'data-admitad-operation="sync_operation"' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( 4 === substr_count( $html, 'name="run_operation"' ) );
		}
	);
} finally {
	global $wpdb;
	$run_table = Promokodiki_Admitad_Schema::table( 'sync_run' );
	foreach ( $run_ids as $run_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test cleanup for plugin-owned rows.
		$wpdb->delete( $run_table, array( 'id' => $run_id ), array( '%d' ) );
	}
	wp_set_current_user( $old_user );
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $old_options as $option_key => $value ) {
		if ( '__missing__' === $value ) {
			delete_option( $option_key );
		} else {
			update_option( $option_key, $value, false );
		}
	}
}

Promokodiki_Admitad_Test_Harness::finish();
