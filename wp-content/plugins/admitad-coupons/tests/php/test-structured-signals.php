<?php
/**
 * Stable category-map and company-profile integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids   = array();
$external_id = random_int( 700000, 799999 );
$campaign_id = random_int( 800000, 899999 );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$suffix   = wp_generate_password( 8, false );
	$shoe     = wp_insert_term( 'Тест обувь ' . $suffix, 'promocode_category' );
	$clothing = wp_insert_term( 'Тест одежда ' . $suffix, 'promocode_category' );
	$other    = wp_insert_term( 'Тест прочее ' . $suffix, 'promocode_category' );
	if ( is_wp_error( $shoe ) || is_wp_error( $clothing ) || is_wp_error( $other ) ) {
		throw new RuntimeException( 'Unable to create structured-signal test terms.' );
	}
	$shoe_id     = (int) $shoe['term_id'];
	$clothing_id = (int) $clothing['term_id'];
	$other_id    = (int) $other['term_id'];
	$term_ids    = array( $shoe_id, $clothing_id, $other_id );

	Promokodiki_Admitad_Test_Harness::run(
		'coupon and campaign namespaces remain separate and support multiple terms',
		static function () use ( $external_id, $shoe_id, $clothing_id, $other_id ): void {
			$maps = new Promokodiki_Admitad_Category_Map_Repository();
			$maps->save( 'coupon', $external_id, $shoe_id, 100 );
			$maps->save( 'coupon', $external_id, $clothing_id, 90 );
			$maps->save( 'campaign', $external_id, $other_id, 60 );

			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $shoe_id, $clothing_id ),
				$maps->terms_for_external( 'coupon', $external_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $other_id ),
				$maps->terms_for_external( 'campaign', $external_id )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'category maps reject missing site terms',
		static function () use ( $external_id ): void {
			$thrown = false;
			try {
				( new Promokodiki_Admitad_Category_Map_Repository() )->save( 'coupon', $external_id, 999999999, 100 );
			} catch ( InvalidArgumentException ) {
				$thrown = true;
			}
			Promokodiki_Admitad_Test_Harness::assert_true( $thrown );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'marketplace profiles allow no default and enforce their allowed set',
		static function () use ( $campaign_id, $shoe_id, $clothing_id ): void {
			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$profiles->save_profile( $campaign_id, 0, array( $shoe_id, $clothing_id ), 40, 'Marketplace test' );
			$profile = $profiles->profile_for_campaign( $campaign_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $profile['default_term_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $shoe_id, $clothing_id ), $profile['allowed_term_ids'] );

			$profiles->save_profile( $campaign_id, $shoe_id, array( $shoe_id, $clothing_id ), 55, 'Configured test' );
			( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns(
				array(
					array(
						'external_id'  => $campaign_id,
						'name'         => 'Refreshed API name',
						'source_status' => 'active',
						'categories'   => array( array( 'id' => 10, 'name' => 'API snapshot' ) ),
					),
				)
			);
			$preserved = $profiles->profile_for_campaign( $campaign_id );
			Promokodiki_Admitad_Test_Harness::assert_same( $shoe_id, $preserved['default_term_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 55, $preserved['weight'] );

			$profiles->save_profile( $campaign_id, $shoe_id, array( $clothing_id ), 55, 'Restricted test' );
			$restricted = $profiles->profile_for_campaign( $campaign_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $restricted['default_term_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $clothing_id ), $restricted['allowed_term_ids'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 55, $restricted['weight'] );
		}
	);
} finally {
	global $wpdb;
	$map_table     = Promokodiki_Admitad_Schema::table( 'category_map' );
	$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
	$company_table = Promokodiki_Admitad_Schema::table( 'company_category' );
	$wpdb->delete( $map_table, array( 'external_category_id' => $external_id ), array( '%d' ) );
	$wpdb->delete( $profile_table, array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( $company_table, array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
