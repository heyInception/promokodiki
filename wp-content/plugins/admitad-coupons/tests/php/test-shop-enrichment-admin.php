<?php
/** Admin security tests for shop enrichment. @package Promokodiki_Admitad */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
Promokodiki_Admitad_Schema::install();

Promokodiki_Admitad_Test_Harness::run(
	'shop enrichment operations require the automation capability and dedicated nonce',
	static function (): void {
		$actions = new Promokodiki_Admitad_Admin_Actions();
		$original = get_current_user_id();
		$editor = wp_insert_user( array( 'user_login' => 'shop-editor-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
		$admin  = wp_insert_user( array( 'user_login' => 'shop-admin-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		try {
			wp_set_current_user( (int) $editor );
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $actions->shop_enrichment_preview( 'bad' )->get_error_code() );
			wp_set_current_user( (int) $admin );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_nonce', $actions->shop_enrichment_preview( 'bad' )->get_error_code() );
			$nonce = wp_create_nonce( 'promokodiki_admitad_shop_enrichment' );
			$preview = $actions->shop_enrichment_preview( $nonce );
			Promokodiki_Admitad_Test_Harness::assert_true( is_array( $preview ) && isset( $preview['token'], $preview['logos'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'stale_cleanup_preview', $actions->logo_cleanup_execute( array( 1 ), 'wrong', $nonce )->get_error_code() );
		} finally {
			wp_set_current_user( $original );
			wp_delete_user( (int) $editor );
			wp_delete_user( (int) $admin );
		}
	}
);
Promokodiki_Admitad_Test_Harness::finish();
