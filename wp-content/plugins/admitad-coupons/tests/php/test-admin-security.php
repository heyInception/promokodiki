<?php
/**
 * Administrative routing, settings, secret safety, and lock controls.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$user_ids = array();
$post_ids = array();
$options  = array(
	Promokodiki_Admitad_Config::OPTION_NAME,
	'promokodiki_admitad_client_id',
	'promokodiki_admitad_client_secret',
	'promokodiki_admitad_website_id',
	'admitad_access_token',
);
$old_options = array();
foreach ( $options as $option ) {
	$old_options[ $option ] = get_option( $option, '__missing__' );
}
$old_user = get_current_user_id();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Capabilities::install();
	$administrator_id = wp_insert_user(
		array(
			'user_login' => 'admitad-admin-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$editor_id = wp_insert_user(
		array(
			'user_login' => 'admitad-editor-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'editor',
		)
	);
	$user_ids = array( $administrator_id, $editor_id );

	Promokodiki_Admitad_Test_Harness::run(
		'ten admin sections expose explicit administrator and reviewer capabilities',
		static function (): void {
			$capabilities = Promokodiki_Admitad_Admin_Menu::section_capabilities();
			Promokodiki_Admitad_Test_Harness::assert_same( 10, count( $capabilities ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'manage_admitad_automation', $capabilities['admitad-settings'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'manage_admitad_automation', $capabilities['admitad-unlinked-shops'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'review_admitad_mapping', $capabilities['admitad-review'] );
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action( 'admin_post_promokodiki_admitad_save_settings', array( 'Promokodiki_Admitad_Admin_Actions', 'handle_save_settings' ) )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'settings reject editors and invalid nonces, clamp bounds, and preserve blank secrets',
		static function () use ( $administrator_id, $editor_id ): void {
			$actions = new Promokodiki_Admitad_Admin_Actions();
			wp_set_current_user( $editor_id );
			$denied = $actions->save_settings( array(), array(), wp_create_nonce( 'promokodiki_admitad_save_settings' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $denied ) );

			wp_set_current_user( $administrator_id );
			$invalid = $actions->save_settings( array(), array(), 'invalid-nonce' );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_nonce', $invalid->get_error_code() );

			update_option( 'promokodiki_admitad_client_secret', 'test-secret-not-for-output', false );
			$saved = $actions->save_settings(
				array(
					'batch_size'         => 99999,
					'missing_threshold'  => -10,
					'coupon_interval'    => 1,
					'email_alerts'       => '1',
					'auto_tags'          => '1',
				),
				array(
					'client_id'     => 'client-test',
					'client_secret' => '',
					'website_id'    => '2811611',
				),
				wp_create_nonce( 'promokodiki_admitad_save_settings' )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( true === $saved );
			Promokodiki_Admitad_Test_Harness::assert_same( 'test-secret-not-for-output', get_option( 'promokodiki_admitad_client_secret' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 500, Promokodiki_Admitad_Config::get( 'batch_size' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, Promokodiki_Admitad_Config::get( 'missing_threshold' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 300, Promokodiki_Admitad_Config::get( 'coupon_interval' ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'settings HTML contains every setting but never stored secrets or tokens',
		static function () use ( $administrator_id ): void {
			wp_set_current_user( $administrator_id );
			update_option( 'admitad_access_token', 'test-token-not-for-output', false );
			ob_start();
			( new Promokodiki_Admitad_Settings_Page() )->render();
			$html = (string) ob_get_clean();
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, 'test-secret-not-for-output' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, 'test-token-not-for-output' ) );
			if ( defined( 'PROMOKODIKI_ADMITAD_CLIENT_SECRET' ) ) {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, 'Задан константой wp-config.php' ) );
			} else {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, 'autocomplete="new-password"' ) );
			}
			foreach ( array_keys( Promokodiki_Admitad_Config::defaults() ) as $key ) {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, 'settings[' . $key . ']' ), 'Missing setting field: ' . $key );
			}
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'editors can return one coupon lock to automation but cannot manage globals',
		static function () use ( $editor_id, &$post_ids ): void {
			$post_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Lock metabox fixture' ) );
			$post_ids[] = $post_id;
			update_post_meta( $post_id, '_admitad_category_locked', 'yes' );
			update_post_meta( $post_id, '_admitad_locked_term_ids', array( 123 ) );
			wp_set_current_user( $editor_id );
			$result = ( new Promokodiki_Admitad_Admin_Actions() )->unlock_post(
				$post_id,
				'categories',
				wp_create_nonce( 'promokodiki_admitad_unlock_' . $post_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( true === $result );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_post_meta( $post_id, '_admitad_category_locked', true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! current_user_can( 'manage_admitad_automation' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( current_user_can( 'review_admitad_mapping' ) );
		}
	);
} finally {
	wp_set_current_user( $old_user );
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $old_options as $option => $value ) {
		if ( '__missing__' === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value, false );
		}
	}
}

Promokodiki_Admitad_Test_Harness::finish();
