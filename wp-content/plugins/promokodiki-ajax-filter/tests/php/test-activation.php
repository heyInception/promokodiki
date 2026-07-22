<?php
/** Activation integration test. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once WP_PLUGIN_DIR . '/promokodiki-ajax-filter/promokodiki-ajax-filter.php';

Promokodiki_Filter_Test_Harness::run(
	'activation creates schema',
	static function (): void {
		global $wpdb;

		Promokodiki_Filter_Activator::activate();
		Promokodiki_Filter_Activator::activate();

		$table = $wpdb->prefix . 'promokodiki_click_stats';
		Promokodiki_Filter_Test_Harness::assert_true( class_exists( 'Promokodiki_Filter_Plugin' ) );
		Promokodiki_Filter_Test_Harness::assert_same( '1', get_option( 'promokodiki_filter_db_version' ) );
		Promokodiki_Filter_Test_Harness::assert_same(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}
);

Promokodiki_Filter_Test_Harness::finish();
