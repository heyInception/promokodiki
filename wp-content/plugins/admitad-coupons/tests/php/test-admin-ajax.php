<?php
/**
 * Secure Admitad administration AJAX integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user = get_current_user_id();
$user_ids = array();

try {
	Promokodiki_Admitad_Capabilities::install();
	$administrator_id = wp_insert_user(
		array(
			'user_login' => 'admitad-ajax-admin-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$editor_id = wp_insert_user(
		array(
			'user_login' => 'admitad-ajax-editor-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'editor',
		)
	);
	$user_ids = array( $administrator_id, $editor_id );

	Promokodiki_Admitad_Test_Harness::run(
		'admin AJAX rejects invalid requests and applies section capabilities',
		static function () use ( $administrator_id, $editor_id ): void {
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action(
					'wp_ajax_promokodiki_admitad_admin',
					array( 'Promokodiki_Admitad_Admin_Ajax', 'dispatch' )
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				false,
				has_action( 'wp_ajax_nopriv_promokodiki_admitad_admin' )
			);

			wp_set_current_user( $administrator_id );
			$invalid_nonce = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-overview',
					'fragment'    => 'overview',
					'_ajax_nonce' => 'invalid-nonce',
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_nonce', $invalid_nonce->get_error_code() );

			wp_set_current_user( $editor_id );
			$forbidden = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'settings',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $forbidden->get_error_code() );

			wp_set_current_user( $administrator_id );
			$invalid_operation = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'unexpected_operation',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_operation', $invalid_operation->get_error_code() );

			$rejected = false;
			try {
				Promokodiki_Admitad_Admin_Fragments::render( '../settings', array() );
			} catch ( InvalidArgumentException $error ) {
				$rejected = true;
			}
			Promokodiki_Admitad_Test_Harness::assert_true( $rejected );
		}
	);
} finally {
	wp_set_current_user( $old_user );
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
