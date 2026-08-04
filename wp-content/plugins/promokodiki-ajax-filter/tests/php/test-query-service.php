<?php
/** Query service integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$term_ids = array();
$post_ids = array();

try {
	$category_one = wp_insert_term( 'PAF Query Category A ' . wp_generate_uuid4(), 'promocode_category' );
	$category_one_id = (int) $category_one['term_id'];
	$term_ids[] = array( $category_one_id, 'promocode_category' );
	$category_two = wp_insert_term( 'PAF Query Category B ' . wp_generate_uuid4(), 'promocode_category' );
	$category_two_id = (int) $category_two['term_id'];
	$term_ids[] = array( $category_two_id, 'promocode_category' );
	$brand_one = wp_insert_term( 'PAF Query Brand A ' . wp_generate_uuid4(), 'promocode_brand' );
	$brand_one_id = (int) $brand_one['term_id'];
	$term_ids[] = array( $brand_one_id, 'promocode_brand' );
	$brand_two = wp_insert_term( 'PAF Query Brand B ' . wp_generate_uuid4(), 'promocode_brand' );
	$brand_two_id = (int) $brand_two['term_id'];
	$term_ids[] = array( $brand_two_id, 'promocode_brand' );
	$shop = wp_insert_term( 'PAF Query Shop ' . wp_generate_uuid4(), 'shops_category' );
	$shop_id = (int) $shop['term_id'];
	$term_ids[] = array( $shop_id, 'shops_category' );

	$create_promo = static function (
		string $title,
		string $date,
		int $usage,
		string $expiry,
		int $category_id,
		int $brand_id
	) use ( &$post_ids, $shop_id ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_date'   => current_time( 'mysql' ),
			)
		);
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
			),
			array( 'ID' => $post_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
		$post_ids[] = $post_id;
		wp_set_post_terms( $post_id, array( $category_id ), 'promocode_category' );
		wp_set_post_terms( $post_id, array( $brand_id ), 'promocode_brand' );
		wp_set_post_terms( $post_id, array( $shop_id ), 'shops_category' );
		update_post_meta( $post_id, '_promocode_used_count', $usage );
		if ( '' !== $expiry ) {
			update_post_meta( $post_id, '_promocode_expiry_date', $expiry );
		}
		return $post_id;
	};

	$today = current_time( 'Y-m-d' );
	$new_id = $create_promo( 'PAF newest', '2026-07-20 10:00:00', 5, wp_date( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ), $category_one_id, $brand_one_id );
	$old_id = $create_promo( 'PAF oldest', '2026-07-10 10:00:00', 20, wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ), $category_one_id, $brand_one_id );
	$mid_id = $create_promo( 'PAF middle', '2026-07-15 10:00:00', 0, '', $category_one_id, $brand_two_id );
	$expired_id = $create_promo( 'PAF expired', '2026-07-18 10:00:00', 100, wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ), $category_one_id, $brand_one_id );
	$other_id = $create_promo( 'PAF unrelated', '2026-07-21 10:00:00', 200, wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ), $category_two_id, $brand_one_id );
	$now      = current_time( 'timestamp' );
	$discount_active_ids = array();
	for ( $index = 1; $index <= 7; $index++ ) {
		$discount_active_ids[] = $create_promo(
			'PAF discount active ' . $index,
			'2099-01-0' . $index . ' 12:00:00',
			0,
			wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) ),
			$category_two_id,
			$brand_one_id
		);
	}
	$undated_id = $create_promo(
		'PAF discount undated',
		'2099-01-10 12:00:00',
		0,
		'',
		$category_two_id,
		$brand_one_id
	);
	$discount_expired_id = $create_promo(
		'PAF discount expired',
		'2099-01-11 12:00:00',
		0,
		wp_date( 'Y-m-d', $now - DAY_IN_SECONDS ),
		$category_two_id,
		$brand_one_id
	);
	$inactive_id = $create_promo(
		'PAF discount inactive',
		'2099-01-12 12:00:00',
		1000,
		wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) ),
		$category_two_id,
		$brand_one_id
	);
	update_post_meta( $inactive_id, '_promocode_is_active', 'no' );
	$fallback_high_id = $create_promo(
		'PAF discount lifetime high',
		wp_date( 'Y-m-d H:i:s', $now - ( 9 * MINUTE_IN_SECONDS ) ),
		2000000000,
		wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) ),
		$category_two_id,
		$brand_one_id
	);
	$fallback_low_id = $create_promo(
		'PAF discount lifetime low',
		wp_date( 'Y-m-d H:i:s', $now - ( 10 * MINUTE_IN_SECONDS ) ),
		1999999999,
		wp_date( 'Y-m-d', $now + ( 30 * DAY_IN_SECONDS ) ),
		$category_two_id,
		$brand_one_id
	);
	$discussed_legacy_id = $create_promo( 'PAF discussed legacy', wp_date( 'Y-m-d H:i:s', $now - ( 11 * MINUTE_IN_SECONDS ) ), 0, '', $category_two_id, $brand_one_id );
	update_post_meta( $discussed_legacy_id, '_promocode_likes', 700000000 );
	update_post_meta( $discussed_legacy_id, '_promocode_dislikes', 500000000 );
	$discussed_total_id = $create_promo( 'PAF discussed total', wp_date( 'Y-m-d H:i:s', $now - ( 12 * MINUTE_IN_SECONDS ) ), 0, '', $category_two_id, $brand_one_id );
	update_post_meta( $discussed_total_id, '_promocode_votes_total', 1000000000 );
	$discussed_tie_recent_id = $create_promo( 'PAF discussed tie recent', wp_date( 'Y-m-d H:i:s', $now - ( 13 * MINUTE_IN_SECONDS ) ), 0, '', $category_two_id, $brand_one_id );
	update_post_meta( $discussed_tie_recent_id, '_promocode_votes_total', 900000000 );
	$discussed_tie_same_date = wp_date( 'Y-m-d H:i:s', $now - ( 14 * MINUTE_IN_SECONDS ) );
	$discussed_tie_id_low = $create_promo( 'PAF discussed tie ID low', $discussed_tie_same_date, 0, '', $category_two_id, $brand_one_id );
	update_post_meta( $discussed_tie_id_low, '_promocode_votes_total', 900000000 );
	$discussed_tie_id_high = $create_promo( 'PAF discussed tie ID high', $discussed_tie_same_date, 0, '', $category_two_id, $brand_one_id );
	update_post_meta( $discussed_tie_id_high, '_promocode_votes_total', 900000000 );

	$home_context = array(
		'type'                 => 'home',
		'object_id'            => 0,
		'brand_taxonomy'       => 'promocode_brand',
		'allowed_category_ids' => array( $category_one_id, $category_two_id ),
		'allowed_brand_ids'    => array( $brand_one_id, $brand_two_id ),
	);
	$discounts_context = array(
		'type'                 => 'discounts',
		'object_id'            => 0,
		'brand_taxonomy'       => 'shops_category',
		'allowed_category_ids' => array(),
		'allowed_brand_ids'    => array(),
	);
	$settings = array_merge( Promokodiki_Filter_Settings::defaults(), array( 'initial_count' => 10, 'load_more_count' => 10 ) );
	$state = static fn( array $overrides = array() ): array => array_merge(
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
		'home category and brand combine with AND',
		static function () use ( $state, $settings, $home_context, $category_one_id, $brand_one_id, $new_id, $old_id ): void {
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'category_id' => $category_one_id, 'brand_id' => $brand_one_id ) ),
				$home_context,
				$settings
			);
			Promokodiki_Filter_Test_Harness::assert_same( array( $new_id, $old_id ), wp_list_pluck( $result['posts'], 'ID' ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'ordinary sorting matrix is deterministic',
		static function () use ( $state, $settings, $home_context, $category_one_id, $new_id, $mid_id, $old_id ): void {
			$expected = array(
				'newest'  => array( $new_id, $mid_id, $old_id ),
				'popular' => array( $old_id, $new_id, $mid_id ),
				'expiring'=> array( $old_id, $new_id, $mid_id ),
				'oldest'  => array( $old_id, $mid_id, $new_id ),
			);
			foreach ( $expected as $sort => $ids ) {
				$result = Promokodiki_Filter_Query_Service::run(
					$state( array( 'category_id' => $category_one_id, 'sort' => $sort ) ),
					$home_context,
					$settings
				);
				Promokodiki_Filter_Test_Harness::assert_same( $ids, wp_list_pluck( $result['posts'], 'ID' ), $sort );
			}
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'expired promocodes stay after every active promocode',
		static function () use ( $state, $settings, $home_context, $category_one_id, $new_id, $mid_id, $old_id, $expired_id ): void {
			$with_expired = array_merge( $settings, array( 'show_expired' => true ) );
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'category_id' => $category_one_id, 'sort' => 'newest' ) ),
				$home_context,
				$with_expired
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $new_id, $mid_id, $old_id, $expired_id ),
				wp_list_pluck( $result['posts'], 'ID' )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'pagination respects different first and later portion sizes',
		static function () use ( $state, $settings, $home_context, $category_one_id, $new_id, $mid_id, $old_id ): void {
			$paged_settings = array_merge( $settings, array( 'initial_count' => 2, 'load_more_count' => 1 ) );
			$page_one = Promokodiki_Filter_Query_Service::run(
				$state( array( 'category_id' => $category_one_id ) ),
				$home_context,
				$paged_settings
			);
			$page_two = Promokodiki_Filter_Query_Service::run(
				$state( array( 'category_id' => $category_one_id, 'page' => 2 ) ),
				$home_context,
				$paged_settings
			);
			Promokodiki_Filter_Test_Harness::assert_same( array( $new_id, $mid_id ), wp_list_pluck( $page_one['posts'], 'ID' ) );
			Promokodiki_Filter_Test_Harness::assert_same( true, $page_one['has_more'] );
			Promokodiki_Filter_Test_Harness::assert_same( array( $old_id ), wp_list_pluck( $page_two['posts'], 'ID' ) );
			Promokodiki_Filter_Test_Harness::assert_same( false, $page_two['has_more'] );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts newest keeps undated active offers and paginates eligible cards',
		static function () use ( $state, $settings, $discounts_context, $undated_id, $discount_expired_id, $inactive_id ): void {
			$discount_settings = array_merge( $settings, array( 'initial_count' => 6, 'load_more_count' => 6 ) );
			$page_one = Promokodiki_Filter_Query_Service::run(
				$state( array( 'sort' => 'newest' ) ),
				$discounts_context,
				$discount_settings
			);
			$page_two = Promokodiki_Filter_Query_Service::run(
				$state( array( 'sort' => 'newest', 'page' => 2 ) ),
				$discounts_context,
				$discount_settings
			);
			$ids = wp_list_pluck( $page_one['posts'], 'ID' );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $undated_id, $ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $discount_expired_id, $ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $inactive_id, $ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_same( 6, count( $page_one['posts'] ) );
			Promokodiki_Filter_Test_Harness::assert_same( true, $page_one['has_more'] );
			Promokodiki_Filter_Test_Harness::assert_same( array(), array_values( array_intersect( $ids, wp_list_pluck( $page_two['posts'], 'ID' ) ) ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts discussed ranks total reactions then date then ID',
		static function () use ( $state, $settings, $discounts_context, $discussed_legacy_id, $discussed_total_id, $discussed_tie_recent_id, $discussed_tie_id_high, $discussed_tie_id_low ): void {
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'sort' => 'discussed' ) ),
				$discounts_context,
				array_merge( $settings, array( 'initial_count' => 6, 'load_more_count' => 6 ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same(
				array( $discussed_legacy_id, $discussed_total_id, $discussed_tie_recent_id, $discussed_tie_id_high, $discussed_tie_id_low ),
				array_slice( wp_list_pluck( $result['posts'], 'ID' ), 0, 5 )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts popular falls back to lifetime usage only with no tracked clicks',
		static function () use ( $state, $settings, $discounts_context, $fallback_high_id, $fallback_low_id ): void {
			Promokodiki_Filter_Test_Harness::assert_same( 0, Promokodiki_Filter_Click_Stats::ranked_count( 7, false ) );
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'sort' => 'popular' ) ),
				$discounts_context,
				array_merge( $settings, array( 'initial_count' => 6, 'load_more_count' => 6 ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same( array( $fallback_high_id, $fallback_low_id ), array_slice( wp_list_pluck( $result['posts'], 'ID' ), 0, 2 ) );
		}
	);

	global $wpdb;
	$table = $wpdb->prefix . 'promokodiki_click_stats';
	$wpdb->insert( $table, array( 'promocode_id' => $new_id, 'click_date' => $today, 'clicks' => 2 ), array( '%d', '%s', '%d' ) );
	$wpdb->insert( $table, array( 'promocode_id' => $old_id, 'click_date' => wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) ), 'clicks' => 5 ), array( '%d', '%s', '%d' ) );

	Promokodiki_Filter_Test_Harness::run(
		'weekly popularity follows seven-day click totals',
		static function () use ( $state, $settings, $home_context, $old_id, $new_id ): void {
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'popular' => true, 'sort' => '' ) ),
				$home_context,
				$settings
			);
			$ids = wp_list_pluck( $result['posts'], 'ID' );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $old_id, $ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $new_id, $ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( array_search( $old_id, $ids, true ) < array_search( $new_id, $ids, true ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts popular uses tracked seven-day clicks when rankings exist',
		static function () use ( $state, $settings, $discounts_context, $old_id, $new_id ): void {
			$result = Promokodiki_Filter_Query_Service::run(
				$state( array( 'sort' => 'popular' ) ),
				$discounts_context,
				array_merge( $settings, array( 'initial_count' => 6, 'load_more_count' => 6, 'popular_days' => 1 ) )
			);
			Promokodiki_Filter_Test_Harness::assert_same( array( $old_id, $new_id ), wp_list_pluck( $result['posts'], 'ID' ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'disallowed term selection is rejected',
		static function () use ( $state, $settings, $home_context ): void {
			Promokodiki_Filter_Test_Harness::assert_true(
				is_wp_error(
					Promokodiki_Filter_Query_Service::run(
						$state( array( 'brand_id' => 999999999 ) ),
						$home_context,
						$settings
					)
				)
			);
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $term_ids ) as $term ) {
		wp_delete_term( $term[0], $term[1] );
	}
}
