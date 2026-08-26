<?php
/** Activation integration test. */

require_once dirname( __DIR__ ) . '/harness.php';
if ( ! class_exists( 'Promokodiki_Filter_Activator' ) ) {
	require_once WP_PLUGIN_DIR . '/promokodiki-ajax-filter/promokodiki-ajax-filter.php';
}

Promokodiki_Filter_Test_Harness::run(
	'activation creates schema',
	static function (): void {
		global $wpdb;

		Promokodiki_Filter_Activator::activate();
		Promokodiki_Filter_Activator::activate();

		$table = $wpdb->prefix . 'promokodiki_click_stats';
		Promokodiki_Filter_Test_Harness::assert_true( class_exists( 'Promokodiki_Filter_Plugin' ) );
		Promokodiki_Filter_Test_Harness::assert_same( '2', get_option( 'promokodiki_filter_db_version' ) );
		Promokodiki_Filter_Test_Harness::assert_same(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
		Promokodiki_Filter_Test_Harness::assert_same(
			$wpdb->prefix . 'promokodiki_promo_usage',
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'promokodiki_promo_usage' ) )
		);
		Promokodiki_Filter_Test_Harness::assert_same(
			$wpdb->prefix . 'promokodiki_promo_votes',
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'promokodiki_promo_votes' ) )
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'plugin metadata constant and every public asset share one cache version',
	static function (): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$original_scripts = $GLOBALS['wp_scripts'] ?? null;
		$original_styles  = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		try {
			Promokodiki_Filter_Plugin::enqueue_assets();
			$metadata_version = get_plugin_data( PROMOKODIKI_FILTER_FILE, false, false )['Version'];
			$versions         = array(
				'metadata' => $metadata_version,
				'constant' => PROMOKODIKI_FILTER_VERSION,
				'style'    => wp_styles()->registered['promokodiki-ajax-filter']->ver,
				'state'    => wp_scripts()->registered['promokodiki-filter-state']->ver,
				'view'     => wp_scripts()->registered['promokodiki-filter-view']->ver,
				'filter'   => wp_scripts()->registered['promokodiki-ajax-filter']->ver,
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array_fill_keys( array_keys( $versions ), PROMOKODIKI_FILTER_VERSION ),
				$versions
			);
		} finally {
			$GLOBALS['wp_scripts'] = $original_scripts;
			$GLOBALS['wp_styles']  = $original_styles;
		}
	}
);

Promokodiki_Filter_Test_Harness::run(
	'plugin cache version advances beyond the prior public asset key',
	static function (): void {
		Promokodiki_Filter_Test_Harness::assert_true(
			version_compare( PROMOKODIKI_FILTER_VERSION, '0.2.0', '>' ),
			'Changed filter assets still use the prior 0.2.0 cache key'
		);
	}
);

Promokodiki_Filter_Test_Harness::finish();
