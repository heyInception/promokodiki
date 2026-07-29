<?php
/**
 * Schema integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'schema installation is idempotent and preserves editorial and legacy data',
	static function (): void {
		global $wpdb;

		$suffixes      = array( 'category_map', 'company_profile', 'company_category', 'rule', 'review_queue', 'sync_run', 'classification_history' );
		$legacy_tables = array(
			$wpdb->prefix . 'admitad_category_mapping',
			$wpdb->prefix . 'subcategory_keywords',
			$wpdb->prefix . 'admitad_companies_mapping',
		);
		$existing      = array();
		$legacy_counts = array();
		$version_key   = 'promokodiki_admitad_db_version';
		$missing       = '__promokodiki_missing_option__';
		$old_version   = get_option( $version_key, $missing );

		Promokodiki_Admitad_Plugin::register();
		$term_count = count(
			get_terms(
				array(
					'taxonomy'   => 'promocode_category',
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			)
		);

		foreach ( $suffixes as $suffix ) {
			$table              = $wpdb->prefix . 'admitad_' . $suffix;
			$existing[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}
		foreach ( $legacy_tables as $table ) {
			$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$legacy_counts[ $table ] = array(
				'exists' => $exists,
				'count'  => $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0,
			);
		}

		try {
			Promokodiki_Admitad_Schema::install();
			Promokodiki_Admitad_Schema::install();

			foreach ( $suffixes as $suffix ) {
				$table = Promokodiki_Admitad_Schema::table( $suffix );
				Promokodiki_Admitad_Test_Harness::assert_same(
					$table,
					$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
				);
			}

			Promokodiki_Admitad_Test_Harness::assert_same( '4', (string) get_option( $version_key ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				$term_count,
				count(
					get_terms(
						array(
							'taxonomy'   => 'promocode_category',
							'hide_empty' => false,
							'fields'     => 'ids',
						)
					)
				)
			);

			foreach ( $legacy_counts as $table => $baseline ) {
				$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				Promokodiki_Admitad_Test_Harness::assert_same( $baseline['exists'], $exists );
				if ( $exists ) {
					Promokodiki_Admitad_Test_Harness::assert_same(
						$baseline['count'],
						(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" )
					);
				}
			}
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

Promokodiki_Admitad_Test_Harness::run(
	'plugin registers the versioned activator',
	static function (): void {
		Promokodiki_Admitad_Test_Harness::assert_true( class_exists( 'Promokodiki_Admitad_Activator' ) );
		Promokodiki_Admitad_Test_Harness::assert_true(
			false !== has_action(
				'activate_' . plugin_basename( dirname( __DIR__, 2 ) . '/admitad-coupons.php' ),
				array( 'Promokodiki_Admitad_Activator', 'activate' )
			)
		);
	}
);

Promokodiki_Admitad_Test_Harness::finish();
