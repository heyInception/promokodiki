<?php
/** Durable recovery migration gate and accounting tests. */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

global $wpdb;
$suffix = strtolower( wp_generate_password( 8, false ) );
$tables = array(
	'keywords' => $wpdb->prefix . 'subcategory_keywords_recovery_step_' . $suffix,
	'companies' => $wpdb->prefix . 'admitad_companies_mapping_recovery_step_' . $suffix,
	'categories' => $wpdb->prefix . 'admitad_category_mapping_recovery_step_' . $suffix,
);
$backup_option = 'promokodiki_admitad_recovery_backup_step_' . $suffix;
$legacy_state  = 'promokodiki_admitad_recovery_legacy_step_' . $suffix;
$recovery_option = 'promokodiki_admitad_recovery_migration';
$old_recovery = get_option( $recovery_option, null );
$backup = tempnam( sys_get_temp_dir(), 'admitad-recovery-step-' );
$term_id = 0;
$run_id = 0;
$campaign_id = wp_rand( 910000000, 919999999 );
$external_category = wp_rand( 920000000, 929999999 );
$initial_rule_ids = array();
try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$initial_rule_ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) ) );
	$term = wp_insert_term( 'Recovery migration ' . $suffix, 'promocode_category' );
	$term_id = (int) $term['term_id'];
	$wpdb->query( "CREATE TABLE {$tables['keywords']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, site_subcategory_id BIGINT UNSIGNED NOT NULL, keyword VARCHAR(255) NOT NULL, weight INT NOT NULL DEFAULT 20, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$tables['companies']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, company_name VARCHAR(255) NOT NULL, site_subcategory_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$tables['categories']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, site_subcategory_id BIGINT UNSIGNED NOT NULL, admitad_category_name VARCHAR(255) NOT NULL, PRIMARY KEY (id))" );
	$wpdb->insert( $tables['keywords'], array( 'site_subcategory_id' => $term_id, 'keyword' => 'recovery failure ' . $suffix, 'weight' => 20 ) );
	$wpdb->insert( $tables['keywords'], array( 'site_subcategory_id' => $term_id, 'keyword' => 'recovery success ' . $suffix, 'weight' => 20 ) );

	( new Promokodiki_Admitad_Reference_Repository() )->sync_coupon_categories( array( array( 'id' => $external_category, 'name' => 'Recovery reference', 'parent_id' => 0 ) ) );
	( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns( array( array( 'external_id' => $campaign_id, 'name' => 'Recovery campaign', 'source_status' => 'active', 'categories' => array() ) ) );
	$runs = new Promokodiki_Admitad_Sync_Run_Repository();
	$run_id = $runs->start( 'reference' );
	$runs->complete( $run_id, array( 'processed' => 2 ) );
	file_put_contents( $backup, 'disposable migration backup' );
	$gate = new Promokodiki_Admitad_Backup_Gate( $backup_option );
	$gate->register( $backup );
	$migration = new Promokodiki_Admitad_Legacy_Migration( $tables, $legacy_state );
	$coordinator = new Promokodiki_Admitad_Recovery_Coordinator( $migration, $gate );

	Promokodiki_Admitad_Test_Harness::run(
		'every migration resume rechecks gates and durably accounts per-row failures without changing sources',
		static function () use ( $coordinator, $migration, $gate, $backup, $backup_option, $legacy_state, $term_id ): void {
			$before = $migration->analyze();
			$started = $coordinator->start_migration();
			Promokodiki_Admitad_Test_Harness::assert_same( 'running', $started['status'] );
			$backup_state = get_option( $backup_option );
			$backup_state['created_at'] = time() - DAY_IN_SECONDS - 1;
			update_option( $backup_option, $backup_state, false );
			$blocked = $coordinator->migrate_next_batch( $started['owner'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'backup_required', $blocked->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $coordinator->migration_progress()['cursor'] );
			$gate->register( $backup );

			$thrown = false;
			$failure = static function ( $term, string $taxonomy ) use ( $term_id, &$thrown ) {
				if ( ! $thrown && 'promocode_category' === $taxonomy && $term instanceof WP_Term && (int) $term->term_id === $term_id ) {
					$thrown = true;
					throw new RuntimeException( 'secret C:\\private\\migration.sql' );
				}
				return $term;
			};
			add_filter( 'get_term', $failure, 1, 2 );
			try {
				$finished = $coordinator->migrate_next_batch( $started['owner'] );
			} finally {
				remove_filter( 'get_term', $failure, 1 );
			}
			Promokodiki_Admitad_Test_Harness::assert_same( 'completed', $finished['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $finished['cursor'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $finished['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $finished['failed'] );
			Promokodiki_Admitad_Test_Harness::assert_true( false === str_contains( wp_json_encode( $finished ), 'private' ) );
			$state = get_option( $legacy_state );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, count( $state['outcomes']['keywords'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'failed', $state['outcomes']['keywords'][1]['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'created', $state['outcomes']['keywords'][2]['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $before, $migration->analyze() );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $finished['verification']['unaccounted'] );
			Promokodiki_Admitad_Test_Harness::assert_same( true, $finished['verification']['source_counts_unchanged'] );
			Promokodiki_Admitad_Test_Harness::assert_same( true, $finished['verification']['taxonomy_seed_complete'] );
		}
	);
} finally {
	$current_rule_ids = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) ) );
	foreach ( array_diff( $current_rule_ids, $initial_rule_ids ) as $rule_id ) { $wpdb->delete( Promokodiki_Admitad_Schema::table( 'rule' ), array( 'id' => $rule_id ), array( '%d' ) ); }
	if ( $term_id > 0 ) { wp_delete_term( $term_id, 'promocode_category' ); }
	if ( $run_id > 0 ) { $wpdb->delete( Promokodiki_Admitad_Schema::table( 'sync_run' ), array( 'id' => $run_id ), array( '%d' ) ); }
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'source_namespace' => 'coupon', 'external_category_id' => $external_category ), array( '%s', '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	foreach ( $tables as $table ) { $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); }
	delete_option( $backup_option );
	delete_option( $legacy_state );
	if ( null === $old_recovery ) { delete_option( $recovery_option ); } else { update_option( $recovery_option, $old_recovery, false ); }
	if ( is_file( $backup ) ) { unlink( $backup ); }
}
Promokodiki_Admitad_Test_Harness::finish();
