<?php
/**
 * Non-destructive legacy mapping migration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

global $wpdb;
$suffix         = strtolower( wp_generate_password( 6, false ) );
$keyword_table  = $wpdb->prefix . 'subcategory_keywords_test_' . $suffix;
$company_table  = $wpdb->prefix . 'admitad_companies_mapping_test_' . $suffix;
$category_table = $wpdb->prefix . 'admitad_category_mapping_test_' . $suffix;
$term_ids       = array();
$campaign_ids   = array();
$state_option   = 'promokodiki_admitad_legacy_test_' . $suffix;
$initial_rule_ids = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$initial_rule_ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) ) );
	$term_a = wp_insert_term( 'Legacy migration A ' . $suffix, 'promocode_category' );
	$term_b = wp_insert_term( 'Legacy migration B ' . $suffix, 'promocode_category' );
	$term_ids = array( (int) $term_a['term_id'], (int) $term_b['term_id'] );
	$wpdb->query( "CREATE TABLE {$keyword_table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, site_subcategory_id BIGINT UNSIGNED NOT NULL, keyword VARCHAR(255) NOT NULL, weight INT NOT NULL DEFAULT 20, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$company_table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, company_name VARCHAR(255) NOT NULL, site_subcategory_id BIGINT UNSIGNED NOT NULL, priority INT NOT NULL DEFAULT 0, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$category_table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, admitad_category_name VARCHAR(255) NOT NULL, site_subcategory_id BIGINT UNSIGNED NOT NULL, priority INT NOT NULL DEFAULT 0, PRIMARY KEY (id))" );

	for ( $index = 0; $index < 1350; ++$index ) {
		$phrase = 0 === $index ? 'x' : ( $index < 3 ? 'legacy conflict phrase' : 'legacy keyword ' . $suffix . ' ' . $index );
		$wpdb->insert(
			$keyword_table,
			array(
				'site_subcategory_id' => $term_ids[ $index % 2 ],
				'keyword'             => $phrase,
				'weight'              => 10 + ( $index % 20 ),
			)
		);
	}
	$wpdb->insert(
		$category_table,
		array(
			'admitad_category_name' => 'Legacy named category ' . $suffix,
			'site_subcategory_id'   => $term_ids[0],
			'priority'              => 1,
		)
	);
	for ( $index = 0; $index < 59; ++$index ) {
		$campaign_id   = random_int( 990000, 999999 ) + ( $index * 100000 );
		$campaign_ids[] = $campaign_id;
		$name          = 'Legacy company ' . $suffix . ' ' . $index;
		( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns(
			array(
				array(
					'external_id'  => $campaign_id,
					'name'         => $name,
					'source_status' => 'active',
					'categories'   => array(),
				),
			)
		);
		$wpdb->insert(
			$company_table,
			array(
				'company_name'        => $name,
				'site_subcategory_id' => $term_ids[ $index % 2 ],
				'priority'            => $index,
			)
		);
	}

	Promokodiki_Admitad_Test_Harness::run(
		'legacy rules and companies migrate in batches without deleting sources',
		static function () use ( $keyword_table, $company_table, $category_table, $state_option ): void {
			$migration = new Promokodiki_Admitad_Legacy_Migration(
				array(
					'keywords'  => $keyword_table,
					'companies' => $company_table,
					'categories' => $category_table,
				),
				$state_option
			);
			$before = $migration->analyze();
			Promokodiki_Admitad_Test_Harness::assert_same( 1350, $before['legacy_keywords'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 59, $before['legacy_companies'] );
			for ( $offset = 0; $offset < $before['total']; $offset += 200 ) {
				$migration->migrate_batch( $offset, 200 );
			}
			$after = $migration->verify();
			Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_keywords'], $after['migrated_keywords'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_companies'], $after['migrated_companies'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_keywords'], $after['legacy_keywords_remaining'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $after['orphan_term_references'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $after['taxonomy_terms_without_rule'] );
			Promokodiki_Admitad_Test_Harness::assert_true( $after['suspended_unsafe'] >= 1 );
			Promokodiki_Admitad_Test_Harness::assert_true( $after['conflicting_rules'] >= 2 );

			$rerun = $migration->migrate_batch( 0, 2000 );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $rerun['created'] );
		}
	);
} finally {
	$current_rule_ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) ) );
	foreach ( array_diff( $current_rule_ids, $initial_rule_ids ) as $rule_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'rule' ), array( 'id' => $rule_id ), array( '%d' ) );
	}
	foreach ( $campaign_ids as $campaign_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	delete_option( $state_option );
	$wpdb->query( "DROP TABLE IF EXISTS {$keyword_table}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$company_table}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$category_table}" );
}

Promokodiki_Admitad_Test_Harness::finish();
