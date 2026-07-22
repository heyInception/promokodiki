<?php
/** Settings and request-state integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

Promokodiki_Filter_Test_Harness::run(
	'settings expose safe defaults',
	static function (): void {
		$settings = Promokodiki_Filter_Settings::defaults();
		Promokodiki_Filter_Test_Harness::assert_same( 8, $settings['initial_count'] );
		Promokodiki_Filter_Test_Harness::assert_same( 8, $settings['load_more_count'] );
		Promokodiki_Filter_Test_Harness::assert_same( 7, $settings['popular_days'] );
		Promokodiki_Filter_Test_Harness::assert_same( 'newest', $settings['default_sort'] );
		Promokodiki_Filter_Test_Harness::assert_same(
			array( 'newest', 'popular', 'expiring', 'oldest' ),
			$settings['enabled_sorts']
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'settings sanitize bounds labels and sort consistency',
	static function (): void {
		$settings = Promokodiki_Filter_Settings::sanitize(
			array(
				'initial_count'   => 0,
				'load_more_count' => 999,
				'popular_days'    => 0,
				'default_sort'    => 'oldest',
				'enabled_sorts'   => array( 'popular', 'invalid' ),
				'show_expired'    => '1',
				'category_label'  => '<b>Категории</b>',
			)
		);

		Promokodiki_Filter_Test_Harness::assert_same( 1, $settings['initial_count'] );
		Promokodiki_Filter_Test_Harness::assert_same( 100, $settings['load_more_count'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $settings['popular_days'] );
		Promokodiki_Filter_Test_Harness::assert_same( array( 'popular' ), $settings['enabled_sorts'] );
		Promokodiki_Filter_Test_Harness::assert_same( 'popular', $settings['default_sort'] );
		Promokodiki_Filter_Test_Harness::assert_same( true, $settings['show_expired'] );
		Promokodiki_Filter_Test_Harness::assert_same( 'Категории', $settings['category_label'] );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'weekly state clears ordinary home filters',
	static function (): void {
		$state = Promokodiki_Filter_State::from_request(
			array(
				'paf_category' => '-2',
				'paf_brand'    => '17',
				'paf_sort'     => 'drop-table',
				'paf_popular'  => '1',
				'paf_page'     => '0',
			),
			Promokodiki_Filter_Settings::defaults(),
			'home'
		);

		Promokodiki_Filter_Test_Harness::assert_same(
			array(
				'category_id' => 0,
				'brand_id'    => 0,
				'sort'        => '',
				'popular'     => true,
				'page'        => 1,
			),
			$state
		);
	}
);

Promokodiki_Filter_Test_Harness::run(
	'weekly mode is ignored outside home',
	static function (): void {
		$state = Promokodiki_Filter_State::from_request(
			array(
				'paf_category' => '12',
				'paf_popular'  => '1',
				'paf_sort'     => 'oldest',
				'paf_page'     => '3',
			),
			Promokodiki_Filter_Settings::defaults(),
			'category'
		);

		Promokodiki_Filter_Test_Harness::assert_same( 12, $state['category_id'] );
		Promokodiki_Filter_Test_Harness::assert_same( false, $state['popular'] );
		Promokodiki_Filter_Test_Harness::assert_same( 'oldest', $state['sort'] );
		Promokodiki_Filter_Test_Harness::assert_same( 3, $state['page'] );
	}
);

Promokodiki_Filter_Test_Harness::finish();
