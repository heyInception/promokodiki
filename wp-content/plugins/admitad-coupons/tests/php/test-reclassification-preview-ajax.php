<?php
/** Bounded immutable recovery preview contract. */
require_once dirname( __DIR__ ) . '/harness.php'; require_once __DIR__ . '/class-test-environment-guard.php'; Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database(); require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
Promokodiki_Admitad_Test_Harness::run( 'preview exposes resumable bounded batch methods', static function (): void { $service = new Promokodiki_Admitad_Reclassification_Service(); Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $service, 'start_preview' ) ); Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $service, 'preview_next_batch' ) ); Promokodiki_Admitad_Test_Harness::assert_true( method_exists( $service, 'preview_progress' ) ); } );
Promokodiki_Admitad_Test_Harness::finish();
