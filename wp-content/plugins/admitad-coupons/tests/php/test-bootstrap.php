<?php
/**
 * Bootstrap integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'plugin registers core hooks',
	static function (): void {
		Promokodiki_Admitad_Test_Harness::assert_true( class_exists( 'Promokodiki_Admitad_Plugin' ) );
		Promokodiki_Admitad_Test_Harness::assert_true(
			false !== has_action( 'init', array( 'Promokodiki_Admitad_Plugin', 'register' ) )
		);
	}
);

Promokodiki_Admitad_Test_Harness::finish();
