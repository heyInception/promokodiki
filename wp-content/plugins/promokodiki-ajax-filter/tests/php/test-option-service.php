<?php
/** Compatible filter option integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$term_ids = array();
$post_ids = array();

try {
	$category_a    = wp_insert_term( 'PAF Option Category A ' . wp_generate_uuid4(), 'promocode_category' );
	$category_a_id = (int) $category_a['term_id'];
	$term_ids[]    = array( $category_a_id, 'promocode_category' );
	$category_b    = wp_insert_term( 'PAF Option Category B ' . wp_generate_uuid4(), 'promocode_category' );
	$category_b_id = (int) $category_b['term_id'];
	$term_ids[]    = array( $category_b_id, 'promocode_category' );
	$category_parent    = wp_insert_term( 'PAF Option Parent ' . wp_generate_uuid4(), 'promocode_category' );
	$category_parent_id = (int) $category_parent['term_id'];
	$term_ids[]         = array( $category_parent_id, 'promocode_category' );
	$category_child     = wp_insert_term(
		'PAF Option Child ' . wp_generate_uuid4(),
		'promocode_category',
		array( 'parent' => $category_parent_id )
	);
	$category_child_id = (int) $category_child['term_id'];
	$term_ids[]        = array( $category_child_id, 'promocode_category' );
	$brand_a       = wp_insert_term( 'PAF Option Brand A ' . wp_generate_uuid4(), 'shops_category' );
	$brand_a_id    = (int) $brand_a['term_id'];
	$term_ids[]    = array( $brand_a_id, 'shops_category' );
	$brand_b       = wp_insert_term( 'PAF Option Brand B ' . wp_generate_uuid4(), 'shops_category' );
	$brand_b_id    = (int) $brand_b['term_id'];
	$term_ids[]    = array( $brand_b_id, 'shops_category' );
	$brand_c       = wp_insert_term( 'PAF Option Brand C ' . wp_generate_uuid4(), 'shops_category' );
	$brand_c_id    = (int) $brand_c['term_id'];
	$term_ids[]    = array( $brand_c_id, 'shops_category' );

	$create_promo = static function ( string $title, int $category_id, int $brand_id, string $expiry ) use ( &$post_ids ): void {
		$post_id    = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		$post_ids[] = $post_id;
		wp_set_post_terms( $post_id, array( $category_id ), 'promocode_category' );
		wp_set_post_terms( $post_id, array( $brand_id ), 'shops_category' );
		update_post_meta( $post_id, '_promocode_expiry_date', $expiry );
	};

	$create_promo( 'PAF option active A', $category_a_id, $brand_a_id, wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	$create_promo( 'PAF option active B', $category_b_id, $brand_b_id, wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	$create_promo( 'PAF option active child', $category_child_id, $brand_c_id, wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	$create_promo( 'PAF option expired', $category_a_id, $brand_b_id, wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) );
	Promokodiki_Filter_Context::flush_cache();

	$context = Promokodiki_Filter_Context::resolve( 'home' );
	$state   = static fn( array $overrides = array() ): array => array_merge(
		array(
			'category_id' => 0,
			'brand_id'    => 0,
			'sort'        => 'newest',
			'popular'     => false,
			'page'        => 1,
		),
		$overrides
	);

	Promokodiki_Filter_Test_Harness::run(
		'home category selection exposes only compatible active brands',
		static function () use ( $context, $state, $category_a_id, $brand_a_id ): void {
			$category_selected = Promokodiki_Filter_Option_Service::build(
				$context,
				$state( array( 'category_id' => $category_a_id ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $brand_a_id ),
				array_map( 'intval', wp_list_pluck( $category_selected['brand_options'], 'id' ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'home brand selection exposes only compatible active categories',
		static function () use ( $context, $state, $category_b_id, $brand_b_id ): void {
			$brand_selected = Promokodiki_Filter_Option_Service::build(
				$context,
				$state( array( 'brand_id' => $brand_b_id ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $category_b_id ),
				array_map( 'intval', wp_list_pluck( $brand_selected['category_options'], 'id' ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'home brand selection retains compatible ancestor categories',
		static function () use ( $context, $state, $category_child_id, $category_parent_id, $brand_c_id ): void {
			$brand_selected = Promokodiki_Filter_Option_Service::build(
				$context,
				$state( array( 'brand_id' => $brand_c_id ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $category_child_id, $category_parent_id ),
				array_map( 'intval', wp_list_pluck( $brand_selected['category_options'], 'id' ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'incompatible home pair keeps category and clears brand',
		static function () use ( $context, $state, $category_a_id, $brand_b_id ): void {
			$incompatible = Promokodiki_Filter_Option_Service::build(
				$context,
				$state( array( 'category_id' => $category_a_id, 'brand_id' => $brand_b_id ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same( 0, $incompatible['state']['brand_id'] );
			Promokodiki_Filter_Test_Harness::assert_same( $category_a_id, $incompatible['state']['category_id'] );
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
