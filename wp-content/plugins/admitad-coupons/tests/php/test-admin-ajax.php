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
		'admin AJAX securely renders bound fragments and rejects invalid requests',
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
			$success = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'foundation',
					'context'     => array( 'message' => '<b>Foundation response</b>' ),
					'paged'       => '2',
					'per_page'    => '50',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! is_wp_error( $success ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Готово.', $success['message'] );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $success['html'], 'Foundation response' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $success['html'], '<b>' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $success['url'], 'page=admitad-settings' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'admitad-settings', $success['state']['page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $success['state']['paged'] );

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
					'fragment'    => 'foundation',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $forbidden->get_error_code() );
			$mismatched = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-overview',
					'fragment'    => 'foundation',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $mismatched->get_error_code() );

			wp_set_current_user( $administrator_id );
			$invalid_operation = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'unexpected_operation',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_operation', $invalid_operation->get_error_code() );

			$wide_context = array_fill( 0, 21, 'value' );
			$wide_context['message'] = 'ignored';
			$invalid_width = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'foundation',
					'context'     => $wide_context,
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_request', $invalid_width->get_error_code() );

			$invalid_depth = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'foundation',
					'context'     => array( 'message' => array( 'one' => array( 'two' => 'too-deep' ) ) ),
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_request', $invalid_depth->get_error_code() );

			$masked_failure = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'foundation',
					'context'     => array( 'message' => array( 'invalid' ) ),
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'server_error', $masked_failure->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $masked_failure->get_error_message(), 'foundation fragment context' ) );

			$long_message = str_repeat( 'a', 300 );
			$trimmed = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'render_fragment',
					'page'        => 'admitad-settings',
					'fragment'    => 'foundation',
					'context'     => array( 'message' => $long_message ),
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! is_wp_error( $trimmed ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $trimmed['html'], $long_message ) );

			$buffer_level = ob_get_level();
			$threw        = false;
			try {
				Promokodiki_Admitad_Admin_Fragments::render( 'foundation', array( 'message' => array( 'invalid' ) ) );
			} catch ( Throwable $error ) {
				$threw = true;
			}
			Promokodiki_Admitad_Test_Harness::assert_true( $threw );
			Promokodiki_Admitad_Test_Harness::assert_same( $buffer_level, ob_get_level() );

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
