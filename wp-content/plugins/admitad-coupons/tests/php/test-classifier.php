<?php
/**
 * Deterministic classifier integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids    = array();
$external_id = random_int( 900000, 949999 );
$campaign_id = random_int( 950000, 999999 );
$old_settings = get_option( 'promokodiki_admitad_settings', array() );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	update_option(
		'promokodiki_admitad_settings',
		array_merge(
			(array) $old_settings,
			array(
				'confidence_high'    => 80,
				'confidence_medium'  => 50,
				'max_categories'     => 3,
				'weight_title'       => 20,
				'weight_description' => 10,
			)
		),
		false
	);
	$suffix   = wp_generate_password( 8, false );
	$fashion  = wp_insert_term( 'Тест мода ' . $suffix, 'promocode_category' );
	$shoes    = wp_insert_term( 'Тест обувь ' . $suffix, 'promocode_category', array( 'parent' => (int) $fashion['term_id'] ) );
	$clothing = wp_insert_term( 'Тест одежда ' . $suffix, 'promocode_category', array( 'parent' => (int) $fashion['term_id'] ) );
	$travel   = wp_insert_term( 'Тест путешествия ' . $suffix, 'promocode_category' );
	$beauty   = wp_insert_term( 'Тест красота ' . $suffix, 'promocode_category' );
	$other    = wp_insert_term( 'Тест прочее ' . $suffix, 'promocode_category', array( 'slug' => 'classifier-other-' . sanitize_title( $suffix ) ) );
	foreach ( array( $fashion, $shoes, $clothing, $travel, $beauty, $other ) as $term ) {
		if ( is_wp_error( $term ) ) {
			throw new RuntimeException( 'Unable to create classifier test terms.' );
		}
		$term_ids[] = (int) $term['term_id'];
	}
	$fashion_id  = (int) $fashion['term_id'];
	$shoe_id     = (int) $shoes['term_id'];
	$clothing_id = (int) $clothing['term_id'];
	$travel_id   = (int) $travel['term_id'];
	$beauty_id   = (int) $beauty['term_id'];
	$other_id    = (int) $other['term_id'];

	$maps     = new Promokodiki_Admitad_Category_Map_Repository();
	$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
	$rules    = new Promokodiki_Admitad_Rule_Repository();
	$classifier = new Promokodiki_Admitad_Classifier( $maps, $profiles, $rules );

	Promokodiki_Admitad_Test_Harness::run(
		'manual category locks are absolute',
		static function () use ( $classifier, $shoe_id ): void {
			$result = $classifier->classify(
				array( 'title' => 'Туры и косметика', 'description' => '', 'categories' => array(), 'campaign' => array() ),
				array(
					'locked_term_ids'  => array( $shoe_id ),
					'locked_primary_id' => $shoe_id,
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $shoe_id, $result->primary_term_id() );
			Promokodiki_Admitad_Test_Harness::assert_same( 'locked', $result->confidence() );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'stable coupon IDs classify empty descriptions with high confidence',
		static function () use ( $classifier, $maps, $external_id, $shoe_id, $clothing_id, $other_id ): void {
			$maps->save( 'coupon', $external_id, $shoe_id, 100 );
			$maps->save( 'coupon', $external_id, $clothing_id, 90 );
			$result = $classifier->classify(
				array(
					'title'       => 'Скидка 10%',
					'description' => '',
					'categories'  => array( array( 'id' => $external_id ) ),
					'campaign'    => array(),
				),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $shoe_id, $result->primary_term_id() );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $shoe_id, $clothing_id ), $result->term_ids() );
			Promokodiki_Admitad_Test_Harness::assert_same( 'high', $result->confidence() );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'company allowed sets reject out-of-profile signals',
		static function () use ( $classifier, $maps, $profiles, $external_id, $campaign_id, $shoe_id, $clothing_id, $other_id ): void {
			$maps->save( 'coupon', $external_id + 1, $shoe_id, 100 );
			$profiles->save_profile( $campaign_id, $clothing_id, array( $clothing_id ), 60, 'Restricted marketplace' );
			$result = $classifier->classify(
				array(
					'title'       => '',
					'description' => '',
					'categories'  => array( array( 'id' => $external_id + 1 ) ),
					'campaign'    => array( 'id' => $campaign_id ),
				),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $clothing_id, $result->primary_term_id() );
			Promokodiki_Admitad_Test_Harness::assert_true( ! in_array( $shoe_id, $result->term_ids(), true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! empty( $result->explanation()['rejected'] ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'title evidence outweighs equal description evidence',
		static function () use ( $classifier, $rules, $shoe_id, $travel_id, $other_id ): void {
			$rules->save( 'кроссовки', $shoe_id, 30, 'active', 'token', 'test' );
			$rules->save( 'отель', $travel_id, 30, 'active', 'token', 'test' );
			$result = $classifier->classify(
				array(
					'title'       => 'Кроссовки со скидкой',
					'description' => 'Отель со скидкой',
					'categories'  => array(),
					'campaign'    => array(),
				),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $shoe_id, $result->primary_term_id() );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'strong ties are deterministic, prefer depth, and lower confidence',
		static function () use ( $classifier, $maps, $external_id, $shoe_id, $travel_id, $other_id ): void {
			$maps->save( 'coupon', $external_id + 2, $shoe_id, 100 );
			$maps->save( 'coupon', $external_id + 2, $travel_id, 100 );
			$result = $classifier->classify(
				array(
					'title'       => '',
					'description' => '',
					'categories'  => array( array( 'id' => $external_id + 2 ) ),
					'campaign'    => array(),
				),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $shoe_id, $result->primary_term_id() );
			Promokodiki_Admitad_Test_Harness::assert_same( 'medium', $result->confidence() );
			Promokodiki_Admitad_Test_Harness::assert_true( ! empty( $result->explanation()['conflicts'] ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'child selection removes its redundant parent and caps at three',
		static function () use ( $classifier, $maps, $external_id, $fashion_id, $shoe_id, $clothing_id, $travel_id, $beauty_id, $other_id ): void {
			$maps->save( 'coupon', $external_id + 3, $fashion_id, 99 );
			$maps->save( 'coupon', $external_id + 3, $shoe_id, 100 );
			$maps->save( 'coupon', $external_id + 3, $clothing_id, 98 );
			$maps->save( 'coupon', $external_id + 3, $travel_id, 97 );
			$maps->save( 'coupon', $external_id + 3, $beauty_id, 96 );
			$result = $classifier->classify(
				array(
					'title'       => '',
					'description' => '',
					'categories'  => array( array( 'id' => $external_id + 3 ) ),
					'campaign'    => array(),
				),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 3, count( $result->term_ids() ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! in_array( $fashion_id, $result->term_ids(), true ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'no signals use the editorial other term with low confidence',
		static function () use ( $classifier, $other_id ): void {
			$result = $classifier->classify(
				array( 'title' => 'Скидка', 'description' => '', 'categories' => array(), 'campaign' => array() ),
				array( 'other_term_id' => $other_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $other_id, $result->primary_term_id() );
			Promokodiki_Admitad_Test_Harness::assert_same( 'low', $result->confidence() );
		}
	);
} finally {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) . " WHERE source = 'test'" );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'external_category_id' => $external_id ), array( '%d' ) );
	for ( $index = 1; $index <= 3; ++$index ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'external_category_id' => $external_id + $index ), array( '%d' ) );
	}
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	update_option( 'promokodiki_admitad_settings', $old_settings, false );
}

Promokodiki_Admitad_Test_Harness::finish();
