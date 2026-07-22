<?php
/** Admin settings integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

Promokodiki_Filter_Test_Harness::run(
	'settings register with manage options capability',
	static function (): void {
		Promokodiki_Filter_Settings::register();
		$registered = get_registered_settings();
		Promokodiki_Filter_Test_Harness::assert_true( isset( $registered[ Promokodiki_Filter_Settings::OPTION_NAME ] ) );
		Promokodiki_Filter_Test_Harness::assert_same(
			'manage_options',
			$registered[ Promokodiki_Filter_Settings::OPTION_NAME ]['capability']
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'admin hooks are registered',
	static function (): void {
		Promokodiki_Filter_Test_Harness::assert_true(
			false !== has_action( 'admin_menu', array( 'Promokodiki_Filter_Settings', 'add_menu' ) )
		);
		Promokodiki_Filter_Test_Harness::assert_true(
			false !== has_action( 'admin_notices', array( 'Promokodiki_Filter_Settings', 'render_conflict_notice' ) )
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'conflict notice names active plugins without mutation links',
	static function (): void {
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
		$original_user = get_current_user_id();
		wp_set_current_user( (int) $admins[0] );
		ob_start();
		Promokodiki_Filter_Settings::render_conflict_notice();
		$html = (string) ob_get_clean();
		wp_set_current_user( $original_user );

		Promokodiki_Filter_Test_Harness::assert_contains( 'Filter Everything', $html );
		Promokodiki_Filter_Test_Harness::assert_contains( 'Filter Everything Pro', $html );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'action=deactivate', $html );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'action=delete', $html );
	}
);

Promokodiki_Filter_Test_Harness::finish();
