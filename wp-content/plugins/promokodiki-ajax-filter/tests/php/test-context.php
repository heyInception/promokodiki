<?php
/** Context integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$term_ids = array();
$post_ids = array();

try {
	$parent      = wp_insert_term( 'PAF Parent ' . wp_generate_uuid4(), 'promocode_category' );
	$parent_id   = (int) $parent['term_id'];
	$term_ids[]  = array( $parent_id, 'promocode_category' );
	$child       = wp_insert_term( 'PAF Child', 'promocode_category', array( 'parent' => $parent_id ) );
	$child_id    = (int) $child['term_id'];
	$term_ids[]  = array( $child_id, 'promocode_category' );
	$grandchild  = wp_insert_term( 'PAF Grandchild', 'promocode_category', array( 'parent' => $child_id ) );
	$grand_id    = (int) $grandchild['term_id'];
	$term_ids[]  = array( $grand_id, 'promocode_category' );
	$unrelated   = wp_insert_term( 'PAF Unrelated ' . wp_generate_uuid4(), 'promocode_category' );
	$unrelated_id= (int) $unrelated['term_id'];
	$term_ids[]  = array( $unrelated_id, 'promocode_category' );

	$shop       = wp_insert_term( 'PAF Shop A ' . wp_generate_uuid4(), 'shops_category' );
	$shop_id    = (int) $shop['term_id'];
	$term_ids[] = array( $shop_id, 'shops_category' );
	$other_shop = wp_insert_term( 'PAF Other Shop ' . wp_generate_uuid4(), 'shops_category' );
	$other_shop_id = (int) $other_shop['term_id'];
	$term_ids[] = array( $other_shop_id, 'shops_category' );
	$brand_a    = wp_insert_term( 'PAF Shop B ' . wp_generate_uuid4(), 'shops_category' );
	$brand_a_id = (int) $brand_a['term_id'];
	$term_ids[] = array( $brand_a_id, 'shops_category' );
	$brand_b    = wp_insert_term( 'PAF Shop C ' . wp_generate_uuid4(), 'shops_category' );
	$brand_b_id = (int) $brand_b['term_id'];
	$term_ids[] = array( $brand_b_id, 'shops_category' );
	$unrelated_brand = wp_insert_term( 'PAF Promocode Brand ' . wp_generate_uuid4(), 'promocode_brand' );
	$unrelated_brand_id = (int) $unrelated_brand['term_id'];
	$term_ids[] = array( $unrelated_brand_id, 'promocode_brand' );

	$active_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF active context fixture',
		)
	);
	$post_ids[] = $active_id;
	wp_set_post_terms( $active_id, array( $shop_id, $brand_a_id, $brand_b_id ), 'shops_category' );
	update_post_meta( $active_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );

	$expired_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF expired context fixture',
		)
	);
	$post_ids[] = $expired_id;
	wp_set_post_terms( $expired_id, array( $other_shop_id ), 'shops_category' );
	update_post_meta( $expired_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) );
	Promokodiki_Filter_Context::flush_cache();

	Promokodiki_Filter_Test_Harness::run(
		'home brand options follow the populated shop taxonomy',
		static function () use ( $shop_id, $unrelated_brand_id ): void {
			$context = Promokodiki_Filter_Context::resolve( 'home' );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $shop_id, $context['allowed_brand_ids'], true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $unrelated_brand_id, $context['allowed_brand_ids'], true ) );
			Promokodiki_Filter_Test_Harness::assert_same( 'shops_category', $context['brand_taxonomy'] );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'category context includes only the current branch',
		static function () use ( $parent_id, $child_id, $grand_id, $unrelated_id ): void {
			$context = Promokodiki_Filter_Context::resolve( 'category', $parent_id );
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $parent_id, $child_id, $grand_id ),
				$context['allowed_category_ids']
			);
			Promokodiki_Filter_Test_Harness::assert_true(
				! in_array( $unrelated_id, $context['allowed_category_ids'], true )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'shop context exposes every associated shop term with active promocodes',
		static function () use ( $shop_id, $brand_a_id, $brand_b_id ): void {
			$context = Promokodiki_Filter_Context::resolve( 'shop', $shop_id );
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $shop_id, $brand_a_id, $brand_b_id ),
				$context['allowed_brand_ids']
			);
			Promokodiki_Filter_Test_Harness::assert_same( 'shops_category', $context['brand_taxonomy'] );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'invalid context object is rejected',
		static function (): void {
			Promokodiki_Filter_Test_Harness::assert_true(
				is_wp_error( Promokodiki_Filter_Context::resolve( 'category', 999999999 ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $term_ids ) as $term ) {
		wp_delete_term( $term[0], $term[1] );
	}
}
