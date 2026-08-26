<?php
/**
 * Configuration and capability integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'configuration exposes safe automation defaults',
	static function (): void {
		$defaults = Promokodiki_Admitad_Config::defaults();

		Promokodiki_Admitad_Test_Harness::assert_same( 3600, $defaults['coupon_interval'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 2, $defaults['missing_threshold'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 3, $defaults['max_categories'] );
		Promokodiki_Admitad_Test_Harness::assert_same( true, $defaults['auto_tags'] );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'configuration clamps unsafe values',
	static function (): void {
		$sanitized = Promokodiki_Admitad_Config::sanitize(
			array(
				'coupon_interval'    => 1,
				'batch_size'         => 5000,
				'missing_threshold'  => 0,
				'max_categories'     => 25,
				'log_retention_days' => 1,
				'auto_tags'          => '0',
			)
		);

		Promokodiki_Admitad_Test_Harness::assert_same( 300, $sanitized['coupon_interval'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 500, $sanitized['batch_size'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 1, $sanitized['missing_threshold'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 3, $sanitized['max_categories'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 7, $sanitized['log_retention_days'] );
		Promokodiki_Admitad_Test_Harness::assert_same( false, $sanitized['auto_tags'] );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'configuration reads saved settings and prefers credential constants',
	static function (): void {
		$original = get_option( 'promokodiki_admitad_settings', null );

		try {
			update_option(
				'promokodiki_admitad_settings',
				array( 'coupon_interval' => 7200 ),
				false
			);

			if ( ! defined( 'PROMOKODIKI_ADMITAD_CLIENT_ID' ) ) {
				define( 'PROMOKODIKI_ADMITAD_CLIENT_ID', 'constant-client-id' );
			}
			update_option( 'promokodiki_admitad_client_id', 'option-client-id', false );

			Promokodiki_Admitad_Test_Harness::assert_same( 7200, Promokodiki_Admitad_Config::get( 'coupon_interval' ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				constant( 'PROMOKODIKI_ADMITAD_CLIENT_ID' ),
				Promokodiki_Admitad_Config::get( 'client_id' )
			);
		} finally {
			if ( null === $original ) {
				delete_option( 'promokodiki_admitad_settings' );
			} else {
				update_option( 'promokodiki_admitad_settings', $original, false );
			}
			delete_option( 'promokodiki_admitad_client_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'roles receive only their intended Admitad capabilities',
	static function (): void {
		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );
		$admin_manage  = $administrator->has_cap( 'manage_admitad_automation' );
		$admin_review  = $administrator->has_cap( 'review_admitad_mapping' );
		$editor_manage = $editor->has_cap( 'manage_admitad_automation' );
		$editor_review = $editor->has_cap( 'review_admitad_mapping' );

		try {
			Promokodiki_Admitad_Capabilities::install();

			Promokodiki_Admitad_Test_Harness::assert_true(
				$administrator->has_cap( 'manage_admitad_automation' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				$administrator->has_cap( 'review_admitad_mapping' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				$editor->has_cap( 'review_admitad_mapping' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				! $editor->has_cap( 'manage_admitad_automation' )
			);
		} finally {
			$admin_manage ? $administrator->add_cap( 'manage_admitad_automation' ) : $administrator->remove_cap( 'manage_admitad_automation' );
			$admin_review ? $administrator->add_cap( 'review_admitad_mapping' ) : $administrator->remove_cap( 'review_admitad_mapping' );
			$editor_manage ? $editor->add_cap( 'manage_admitad_automation' ) : $editor->remove_cap( 'manage_admitad_automation' );
			$editor_review ? $editor->add_cap( 'review_admitad_mapping' ) : $editor->remove_cap( 'review_admitad_mapping' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
