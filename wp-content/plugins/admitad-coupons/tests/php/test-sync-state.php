<?php
/**
 * Synchronization state and lock integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'job lock rejects overlap, refreshes heartbeat, and recovers stale owners',
	static function (): void {
		$now  = 1000;
		$lock = new Promokodiki_Admitad_Job_Lock(
			static function () use ( &$now ): int {
				return $now;
			}
		);

		try {
			Promokodiki_Admitad_Test_Harness::assert_true( $lock->acquire( 'coupon', 'owner-a', 300 ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! $lock->acquire( 'coupon', 'owner-b', 300 ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! $lock->release( 'coupon', 'owner-b' ) );

			$now = 1100;
			Promokodiki_Admitad_Test_Harness::assert_true( $lock->refresh( 'coupon', 'owner-a' ) );
			$now = 1350;
			Promokodiki_Admitad_Test_Harness::assert_true( ! $lock->acquire( 'coupon', 'owner-b', 300 ) );
			$now = 1401;
			Promokodiki_Admitad_Test_Harness::assert_true( $lock->acquire( 'coupon', 'owner-b', 300 ) );
			Promokodiki_Admitad_Test_Harness::assert_true( $lock->release( 'coupon', 'owner-b' ) );
		} finally {
			delete_option( 'promokodiki_admitad_lock_coupon' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'sync runs persist cursors, counters, completion, and redacted failures',
	static function (): void {
		global $wpdb;

		$suffixes    = array( 'category_map', 'company_profile', 'company_category', 'rule', 'review_queue', 'sync_run', 'classification_history' );
		$existing    = array();
		$version_key = 'promokodiki_admitad_db_version';
		$missing     = '__promokodiki_missing_option__';
		$old_version = get_option( $version_key, $missing );

		foreach ( $suffixes as $suffix ) {
			$table              = $wpdb->prefix . 'admitad_' . $suffix;
			$existing[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}

		try {
			Promokodiki_Admitad_Schema::install();
			$runs   = new Promokodiki_Admitad_Sync_Run_Repository();
			$run_id = $runs->start( 'coupon' );
			$runs->heartbeat(
				$run_id,
				200,
				array(
					'processed' => 200,
					'created'   => 10,
					'updated'   => 20,
					'unchanged' => 170,
				)
			);
			$runs->complete(
				$run_id,
				array(
					'processed' => 200,
					'created'   => 10,
					'updated'   => 20,
					'unchanged' => 170,
				)
			);

			$table = Promokodiki_Admitad_Schema::table( 'sync_run' );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $run_id ), ARRAY_A );
			Promokodiki_Admitad_Test_Harness::assert_same( 'completed', $row['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '200', (string) $row['cursor_offset'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '170', (string) $row['unchanged_count'] );

			$failed_id = $runs->start( 'reference' );
			$runs->fail(
				$failed_id,
				new WP_Error(
					'oauth_failed',
					'Request failed access_token=secret-value client_secret=hunter2',
					array( 'status' => 401 )
				)
			);
			$summary = (string) $wpdb->get_var(
				$wpdb->prepare( "SELECT error_summary FROM {$table} WHERE id = %d", $failed_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $summary, 'secret-value' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $summary, 'hunter2' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $summary, '[redacted]' ) );
		} finally {
			foreach ( $existing as $table => $did_exist ) {
				if ( ! $did_exist ) {
					$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
				}
			}
			if ( $missing === $old_version ) {
				delete_option( $version_key );
			} else {
				update_option( $version_key, $old_version, false );
			}
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
