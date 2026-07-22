<?php
/**
 * Verify that the theme delegates promocode filtering to the plugin.
 *
 * @package PromokodikiAjaxFilter
 */

require_once dirname( __DIR__ ) . '/harness.php';

$theme_dir = get_theme_root() . '/promokodiki';
$templates = array(
	'home'     => $theme_dir . '/template-parts/partials/promocodes.php',
	'category' => $theme_dir . '/taxonomy-promocode_category.php',
	'shop'     => $theme_dir . '/taxonomy-shops_category.php',
);

foreach ( $templates as $context => $path ) {
	Promokodiki_Filter_Test_Harness::run(
		'theme delegates the ' . $context . ' context to the filter plugin',
		static function () use ( $context, $path ): void {
			$contents = file_get_contents( $path );

			Promokodiki_Filter_Test_Harness::assert_true( false !== $contents, 'Could not read ' . $path );
			Promokodiki_Filter_Test_Harness::assert_contains( 'promokodiki_filter_render', $contents );
			Promokodiki_Filter_Test_Harness::assert_contains( "'context' => '" . $context . "'", $contents );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'fe_widget', $contents );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'fe_sort', $contents );
		}
	);
}

Promokodiki_Filter_Test_Harness::run(
	'theme no longer registers legacy filter ajax handlers',
	static function () use ( $theme_dir ): void {
		$contents = file_get_contents( $theme_dir . '/functions.php' );

		Promokodiki_Filter_Test_Harness::assert_not_contains( 'increment_promocode_used_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'wp_ajax_increment_promocode_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'function load_more_promocodes', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'wp_ajax_load_more_promocodes', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'theme modal script leaves click tracking to the plugin',
	static function () use ( $theme_dir ): void {
		$contents = file_get_contents( $theme_dir . '/js/promocodes-ajax.js' );

		Promokodiki_Filter_Test_Harness::assert_not_contains( 'increment_promocode_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'openPromoModal', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'mobile filter keeps controls in a horizontal scroll row',
	static function (): void {
		$contents = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/css/filter.css' );

		Promokodiki_Filter_Test_Harness::assert_contains( '@media (max-width: 767px)', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'overflow-x: auto', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'flex-flow: row nowrap', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'filter assets synchronize dropdowns and display the loader',
	static function (): void {
		$script = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/js/filter.js' );
		$styles = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/css/filter.css' );

		Promokodiki_Filter_Test_Harness::assert_true( false !== $script );
		Promokodiki_Filter_Test_Harness::assert_true( false !== $styles );
		Promokodiki_Filter_Test_Harness::assert_contains( 'replaceSelectOptions', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'data-filter-loader', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( '@keyframes promokodiki-filter-spin', $styles );
	}
);

Promokodiki_Filter_Test_Harness::finish();
