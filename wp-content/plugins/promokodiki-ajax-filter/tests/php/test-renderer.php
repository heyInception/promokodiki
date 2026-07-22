<?php
/** Renderer integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$terms = array();

try {
	$category = wp_insert_term( 'PAF Render Category ' . wp_generate_uuid4(), 'promocode_category' );
	$category_id = (int) $category['term_id'];
	$terms[] = array( $category_id, 'promocode_category' );
	$shop = wp_insert_term( 'PAF Render Shop ' . wp_generate_uuid4(), 'shops_category' );
	$shop_id = (int) $shop['term_id'];
	$terms[] = array( $shop_id, 'shops_category' );

	Promokodiki_Filter_Test_Harness::run(
		'home renderer exposes category brand and weekly controls',
		static function (): void {
			$html = Promokodiki_Filter_Renderer::render( 'home', 0 );
			Promokodiki_Filter_Test_Harness::assert_contains( 'class="promocodes__filters"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_category"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_brand"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_popular"', $html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'Проверенные', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'aria-live="polite"', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'category renderer contains only branch and sort controls',
		static function () use ( $category_id ): void {
			$html = Promokodiki_Filter_Renderer::render( 'category', $category_id );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_category"', $html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_brand"', $html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_popular"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_sort"', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'shop renderer contains only brand and sort controls',
		static function () use ( $shop_id ): void {
			$html = Promokodiki_Filter_Renderer::render( 'shop', $shop_id );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_category"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_brand"', $html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_popular"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_sort"', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	foreach ( array_reverse( $terms ) as $term ) {
		wp_delete_term( $term[0], $term[1] );
	}
}
