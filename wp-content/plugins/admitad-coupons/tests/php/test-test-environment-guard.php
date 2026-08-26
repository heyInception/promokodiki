<?php
/**
 * Test-environment guard contracts.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';

Promokodiki_Admitad_Test_Harness::run(
	'disposable database guard requires exact sentinel equality and a test name segment',
	static function (): void {
		Promokodiki_Admitad_Test_Harness::assert_true( Promokodiki_Admitad_Test_Environment_Guard::is_disposable_database( 'promokodiki_test', 'promokodiki_test' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( Promokodiki_Admitad_Test_Environment_Guard::is_disposable_database( 'test_promokodiki', 'test_promokodiki' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Test_Environment_Guard::is_disposable_database( null, 'promokodiki_test' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Test_Environment_Guard::is_disposable_database( 'promokodiki', 'promokodiki_test' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Test_Environment_Guard::is_disposable_database( 'promokodiki', 'promokodiki' ) );
	}
);

Promokodiki_Admitad_Test_Harness::finish();
