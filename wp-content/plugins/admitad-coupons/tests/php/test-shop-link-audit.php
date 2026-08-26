<?php
/** Unlinked shop audit tests. @package Promokodiki_Admitad */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
if ( ! taxonomy_exists( 'shops_category' ) ) { register_taxonomy( 'shops_category', 'promocode', array( 'public' => true ) ); }
Promokodiki_Admitad_Schema::install();

Promokodiki_Admitad_Test_Harness::run(
	'unlinked shop audit reports missing invalid unknown and duplicate campaign IDs',
	static function (): void {
		$repo = new Promokodiki_Admitad_Reference_Repository();
		$repo->sync_campaigns( array( array( 'external_id' => 880001, 'name' => 'Known Campaign', 'categories' => array(), 'source_status' => 'active', 'description' => '', 'raw_description' => '', 'rating' => null, 'image_url' => '', 'site_url' => 'https://known.example.test/' ) ) );
		$prefix = 'Audit-' . wp_generate_password( 10, false ) . '-';
		$fixtures = array();
		foreach ( array( 'missing' => '', 'invalid' => 'abc', 'unknown' => '999999', 'duplicate-a' => '880001', 'duplicate-b' => '880001' ) as $name => $campaign ) {
			$term = wp_insert_term( $prefix . $name, 'shops_category' ); $id = (int) $term['term_id']; $fixtures[] = $id;
			if ( '' !== $campaign ) { update_term_meta( $id, 'admitad_campaign_id', $campaign ); }
		}
		try {
			$result = ( new Promokodiki_Admitad_Shop_Link_Audit() )->audit( array( 'per_page' => 20, 'paged' => 1, 's' => $prefix ) );
			$reasons = array_column( $result['items'], 'reason' ); sort( $reasons );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'duplicate', 'duplicate', 'invalid', 'missing', 'unknown' ), $reasons );
			Promokodiki_Admitad_Test_Harness::assert_same( 5, $result['total'] );
		} finally { foreach ( $fixtures as $id ) { wp_delete_term( $id, 'shops_category' ); } }
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'quick assignment validates local uniqueness and enriches without an API request',
	static function (): void {
		$campaign_id = 880002;
		( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns( array( array( 'external_id' => $campaign_id, 'name' => 'Assignable', 'categories' => array(), 'source_status' => 'active', 'description' => 'Описание', 'raw_description' => '<p>Описание help@assign.example.test</p>', 'rating' => 4.2, 'image_url' => '', 'site_url' => 'https://assign.example.test/' ) ) );
		$term = wp_insert_term( 'Assignable shop ' . wp_generate_uuid4(), 'shops_category' ); $term_id = (int) $term['term_id'];
		$other = wp_insert_term( 'Other shop ' . wp_generate_uuid4(), 'shops_category' ); $other_id = (int) $other['term_id'];
		$audit = new Promokodiki_Admitad_Shop_Link_Audit();
		try {
			Promokodiki_Admitad_Test_Harness::assert_same( 'unknown_campaign', $audit->assign( $term_id, 999998, 1 )->get_error_code() );
			update_term_meta( $other_id, 'admitad_campaign_id', (string) $campaign_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'duplicate_campaign', $audit->assign( $term_id, $campaign_id, 1 )->get_error_code() );
			delete_term_meta( $other_id, 'admitad_campaign_id' );
			Promokodiki_Admitad_Test_Harness::assert_true( true === $audit->assign( $term_id, $campaign_id, 1 ) );
			Promokodiki_Admitad_Test_Harness::assert_same( (string) $campaign_id, get_term_meta( $term_id, 'admitad_campaign_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://assign.example.test/', get_term_meta( $term_id, 'shop_website', true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( get_term_meta( $term_id, '_admitad_shop_source_description', true ), 'Описание' ) );
		} finally { wp_delete_term( $term_id, 'shops_category' ); wp_delete_term( $other_id, 'shops_category' ); }
	}
);
Promokodiki_Admitad_Test_Harness::finish();
