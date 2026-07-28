<?php
/** Recovery preflight remains read-only until its prerequisites are ready. */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

global $wpdb;
$suffix = strtolower( wp_generate_password( 6, false ) );
$tables = array( 'keywords' => $wpdb->prefix . 'subcategory_keywords_recovery_' . $suffix, 'companies' => $wpdb->prefix . 'admitad_companies_mapping_recovery_' . $suffix, 'categories' => $wpdb->prefix . 'admitad_category_mapping_recovery_' . $suffix );
$backup_option = 'promokodiki_admitad_recovery_backup_test_' . $suffix;
$backup = tempnam( sys_get_temp_dir(), 'admitad-preflight-' );
try {
	$wpdb->query( "CREATE TABLE {$tables['keywords']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, site_subcategory_id BIGINT UNSIGNED NOT NULL, keyword VARCHAR(255) NOT NULL, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$tables['companies']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, company_name VARCHAR(255) NOT NULL, site_subcategory_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (id))" );
	$wpdb->query( "CREATE TABLE {$tables['categories']} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, site_subcategory_id BIGINT UNSIGNED NOT NULL, admitad_category_name VARCHAR(255) NOT NULL, PRIMARY KEY (id))" );
	for ( $i = 0; $i < 1350; ++$i ) { $wpdb->insert( $tables['keywords'], array( 'site_subcategory_id' => 0, 'keyword' => 'fixture ' . $i ) ); }
	for ( $i = 0; $i < 59; ++$i ) { $wpdb->insert( $tables['companies'], array( 'site_subcategory_id' => 0, 'company_name' => 'fixture ' . $i ) ); }
	file_put_contents( $backup, 'disposable preflight backup' );
	( new Promokodiki_Admitad_Backup_Gate( $backup_option ) )->register( $backup );
	Promokodiki_Admitad_Test_Harness::run( 'recovery preflight reports reference blockers without mutating legacy sources', static function () use ( $tables, $backup_option ): void {
		$migration = new Promokodiki_Admitad_Legacy_Migration( $tables, 'promokodiki_admitad_recovery_state_test' );
		$coordinator = new Promokodiki_Admitad_Recovery_Coordinator( $migration, new Promokodiki_Admitad_Backup_Gate( $backup_option ) );
		$before = $migration->analyze(); $state = $coordinator->preflight();
		Promokodiki_Admitad_Test_Harness::assert_same( 1350, $state['legacy_keywords'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 59, $state['legacy_companies'] );
		Promokodiki_Admitad_Test_Harness::assert_same( true, $state['backup_ready'] );
		Promokodiki_Admitad_Test_Harness::assert_same( false, $state['reference_ready'] );
		Promokodiki_Admitad_Test_Harness::assert_true( in_array( 'reference_required', $state['blockers'], true ) );
		Promokodiki_Admitad_Test_Harness::assert_same( $before, $migration->analyze() );
	} );
} finally { delete_option( $backup_option ); delete_option( 'promokodiki_admitad_recovery_state_test' ); foreach ( $tables as $table ) { $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); } if ( is_file( $backup ) ) { unlink( $backup ); } }
Promokodiki_Admitad_Test_Harness::finish();
