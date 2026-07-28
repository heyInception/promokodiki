<?php
/** Durable recovery migration contract tests. */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
Promokodiki_Admitad_Test_Harness::run( 'recovery migration exposes bounded owner-protected batch methods', static function (): void {
	$coordinator = new Promokodiki_Admitad_Recovery_Coordinator();
	Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $coordinator, 'start_migration' ) );
	Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $coordinator, 'migrate_next_batch' ) );
	Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $coordinator, 'migration_progress' ) );
} );
Promokodiki_Admitad_Test_Harness::finish();
