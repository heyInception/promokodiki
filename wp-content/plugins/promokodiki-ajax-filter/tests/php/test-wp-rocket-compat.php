<?php
/** WP Rocket compatibility integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

Promokodiki_Filter_Test_Harness::run(
	'windows background css cache urls are normalized',
	static function (): void {
		$relative_path = '/cache/background-css/1/promokodiki.wp.local/wp-content/cache/min/1/theme.css';
		$windows_path  = str_replace( '/', '\\', wp_normalize_path( WP_CONTENT_DIR ) );
		$malformed_url = content_url() . $windows_path . $relative_path;
		$expected_url  = content_url( ltrim( $relative_path, '/' ) );

		Promokodiki_Filter_Test_Harness::assert_same(
			$expected_url,
			apply_filters( 'rocket_css_url', $malformed_url )
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'valid css urls remain unchanged',
	static function (): void {
		$url = content_url( 'cache/background-css/1/promokodiki.wp.local/theme.css' );
		Promokodiki_Filter_Test_Harness::assert_same( $url, apply_filters( 'rocket_css_url', $url ) );
	}
);

Promokodiki_Filter_Test_Harness::finish();
