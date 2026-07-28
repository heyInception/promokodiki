<?php
/**
 * Verified recovery backup gate tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$option = 'promokodiki_admitad_backup_gate_test_' . strtolower( wp_generate_password( 6, false ) );
$file   = tempnam( sys_get_temp_dir(), 'admitad-backup-' );
$before = get_option( $option, null );

try {
	Promokodiki_Admitad_Test_Harness::run(
		'backup gate rejects missing, empty, changed, and expired files without exposing paths',
		static function () use ( $option, $file ): void {
			$gate = new Promokodiki_Admitad_Backup_Gate( $option );
			$missing = $gate->verify();
			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $missing ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'backup_missing', $missing->get_error_code() );

			update_option( $option, array( 'path' => $file, 'size' => 0, 'sha256' => hash( 'sha256', '' ), 'created_at' => time() ), false );
			$empty = $gate->verify();
			Promokodiki_Admitad_Test_Harness::assert_same( 'backup_empty', $empty->get_error_code() );

			file_put_contents( $file, 'safe test backup' );
			$registered = $gate->register( $file );
			Promokodiki_Admitad_Test_Harness::assert_same( wp_normalize_path( realpath( $file ) ), get_option( $option )['path'] );
			Promokodiki_Admitad_Test_Harness::assert_true( true === $gate->verify() );

			file_put_contents( $file, 'changed', FILE_APPEND );
			$changed = $gate->verify();
			Promokodiki_Admitad_Test_Harness::assert_same( 'backup_changed', $changed->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_true( false === str_contains( $changed->get_error_message(), wp_normalize_path( $file ) ) );

			file_put_contents( $file, 'safe test backup' );
			$registered = $gate->register( $file );
			update_option( $option, array_merge( get_option( $option ), array( 'created_at' => time() - DAY_IN_SECONDS - 1 ) ), false );
			$expired = $gate->verify();
			Promokodiki_Admitad_Test_Harness::assert_same( 'backup_expired', $expired->get_error_code() );
		}
	);
} finally {
	delete_option( $option );
	if ( is_file( $file ) ) {
		unlink( $file );
	}
	if ( null !== $before ) {
		update_option( $option, $before, false );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
