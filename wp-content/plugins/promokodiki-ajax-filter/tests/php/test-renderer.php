<?php
/** Renderer integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$terms    = array();
$post_ids = array();

try {
	$parent      = wp_insert_term( 'PAF Render Parent ' . wp_generate_uuid4(), 'promocode_category' );
	$parent_id   = (int) $parent['term_id'];
	$terms[]     = array( $parent_id, 'promocode_category' );
	$leaf        = wp_insert_term( 'PAF Render Leaf', 'promocode_category', array( 'parent' => $parent_id ) );
	$leaf_id     = (int) $leaf['term_id'];
	$terms[]     = array( $leaf_id, 'promocode_category' );
	$single_shop = wp_insert_term( 'PAF Render Single Shop ' . wp_generate_uuid4(), 'shops_category' );
	$single_shop_id = (int) $single_shop['term_id'];
	$terms[] = array( $single_shop_id, 'shops_category' );
	$multi_shop = wp_insert_term( 'PAF Render Multi Shop ' . wp_generate_uuid4(), 'shops_category' );
	$multi_shop_id = (int) $multi_shop['term_id'];
	$terms[] = array( $multi_shop_id, 'shops_category' );
	$multi_brand = wp_insert_term( 'PAF Render Associated Shop ' . wp_generate_uuid4(), 'shops_category' );
	$multi_brand_id = (int) $multi_brand['term_id'];
	$terms[] = array( $multi_brand_id, 'shops_category' );

	$single_post_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF render single shop fixture',
		)
	);
	$post_ids[] = $single_post_id;
	wp_set_post_terms( $single_post_id, array( $single_shop_id ), 'shops_category' );
	update_post_meta( $single_post_id, '_promocode_expiry_date', '2026-12-31' );
	update_post_meta( $single_post_id, '_promocode_code', 'SAVE20' );
	update_post_meta( $single_post_id, '_promocode_link', 'https://shop.example/offer' );
	Promokodiki_Filter_Promo_Interactions::vote( $single_post_id, 'renderer-visitor', 'like' );

	$multi_post_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF render multi shop fixture',
		)
	);
	$post_ids[] = $multi_post_id;
	wp_set_post_terms( $multi_post_id, array( $multi_shop_id, $multi_brand_id ), 'shops_category' );
	update_post_meta( $multi_post_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );

	$discount_ids = array();
	for ( $index = 0; $index < 7; $index++ ) {
		$discount_id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 'PAF renderer discount ' . $index,
				'post_date'   => wp_date( 'Y-m-d H:i:s', time() - ( $index * MINUTE_IN_SECONDS ) ),
			)
		);
		$post_ids[]    = $discount_id;
		$discount_ids[] = $discount_id;
		update_post_meta( $discount_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	}
	Promokodiki_Filter_Context::flush_cache();

	Promokodiki_Filter_Test_Harness::run(
		'card exposes the interaction data and restores the visitor reaction',
		static function () use ( $single_post_id ): void {
			global $post;
			$original_cookie = $_COOKIE['promokodiki_visitor'] ?? null;
			$post            = get_post( $single_post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$_COOKIE['promokodiki_visitor'] = 'renderer-visitor';
			setup_postdata( $post );
			ob_start();
			try {
				require dirname( __DIR__, 4 ) . '/themes/promokodiki/template-parts/promocode-card.php';
				$html = (string) ob_get_clean();
			} finally {
				wp_reset_postdata();
				if ( null === $original_cookie ) {
					unset( $_COOKIE['promokodiki_visitor'] );
				} else {
					$_COOKIE['promokodiki_visitor'] = $original_cookie;
				}
				if ( ob_get_level() ) {
					ob_end_clean();
				}
			}

			Promokodiki_Filter_Test_Harness::assert_contains( 'data-store-url="https://shop.example/offer"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'data-code="SAVE20"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'data-expiry="31.12.2026"', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'promocodes__like_yes is-active', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts renderer exposes one six-card feed and GET sort links',
		static function () use ( $discount_ids ): void {
			$restrict_query = static function ( WP_Query $query ) use ( $discount_ids ): void {
				if ( 'promocode' === $query->get( 'post_type' ) ) {
					$query->set( 'post__in', $discount_ids );
				}
			};
			$original_get = $_GET;
			$_GET         = array( 'paf_sort' => 'newest' );
			add_action( 'pre_get_posts', $restrict_query );
			try {
				$html = Promokodiki_Filter_Renderer::render( 'discounts', 0 );
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
				$_GET = $original_get;
			}

			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-filter-results' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-filter-more' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 3, substr_count( $html, 'data-filter-sort' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-filter-form' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 6, substr_count( $html, 'class="promocodes__item ' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'aria-current="true"' ) );
			Promokodiki_Filter_Test_Harness::assert_true(
				1 === preg_match( '/<input(?=[^>]*type="hidden")(?=[^>]*name="paf_sort")(?=[^>]*value="newest")[^>]*>/', $html )
			);
			Promokodiki_Filter_Test_Harness::assert_true(
				1 === preg_match( '/href="[^"]*paf_sort=newest[^"]*"[^>]*aria-current="true"/', $html )
			);
			Promokodiki_Filter_Test_Harness::assert_contains( 'Сортировать:', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'paf_sort=popular', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'paf_sort=newest', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'paf_sort=discussed', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts renderer defaults to the popular GET sort',
		static function (): void {
			$original_get = $_GET;
			$_GET         = array();
			try {
				$html = Promokodiki_Filter_Renderer::render( 'discounts', 0 );
			} finally {
				$_GET = $original_get;
			}

			Promokodiki_Filter_Test_Harness::assert_true(
				1 === preg_match( '/href="[^"]*paf_sort=popular[^"]*"[^>]*aria-current="true"/', $html )
			);
		}
	);

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
			Promokodiki_Filter_Test_Harness::assert_contains( 'data-filter-loader', $html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'aria-hidden="true"', $html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'category renderer hides the category dropdown when only the current leaf is available',
		static function () use ( $leaf_id ): void {
			$leaf_html = Promokodiki_Filter_Renderer::render( 'category', $leaf_id );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_category"', $leaf_html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_brand"', $leaf_html );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_popular"', $leaf_html );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_sort"', $leaf_html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'category renderer shows the category dropdown when descendants are available',
		static function () use ( $parent_id ): void {
			$parent_html = Promokodiki_Filter_Renderer::render( 'category', $parent_id );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_category"', $parent_html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'shop renderer hides the brand dropdown when only one active shop is available',
		static function () use ( $single_shop_id ): void {
			$single_brand_html = Promokodiki_Filter_Renderer::render( 'shop', $single_shop_id );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_brand"', $single_brand_html );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'shop renderer shows the brand dropdown when active promocodes have multiple shops',
		static function () use ( $multi_shop_id ): void {
			$multi_brand_html = Promokodiki_Filter_Renderer::render( 'shop', $multi_shop_id );
			Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_brand"', $multi_brand_html );
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $terms ) as $term ) {
		wp_delete_term( $term[0], $term[1] );
	}
}
