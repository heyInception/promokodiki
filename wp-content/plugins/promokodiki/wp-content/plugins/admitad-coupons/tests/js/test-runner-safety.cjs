const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );

const root = path.resolve( __dirname, '../..' );
const runner = fs.readFileSync( path.join( root, 'tests/run-all.ps1' ), 'utf8' );
const coordinator = fs.readFileSync( path.join( root, 'tests/php/test-sync-coordinator.php' ), 'utf8' );
const guard = fs.readFileSync( path.join( root, 'tests/php/class-test-environment-guard.php' ), 'utf8' );

test( 'the full integration runner refuses an unverified database before tests can execute', () => {
	assert.match( runner, /PROMOKODIKI_ADMITAD_TEST_DATABASE/ );
	assert.match( runner, /class-test-environment-guard\.php/ );
	assert.match( runner, /DB_NAME/ );
	assert.match( runner, /(?:_test|_tests|test_)/i );
	assert.match( runner, /throw\s+["'].*(?:disposable|test database|refus)/i );
} );

test( 'every table-dropping integration test invokes the shared guard before plugin setup', () => {
	for ( const file of [ 'test-sync-coordinator.php', 'test-schema.php', 'test-sync-state.php', 'test-legacy-migration.php' ] ) {
		const source = fs.readFileSync( path.join( root, 'tests/php', file ), 'utf8' );
		assert.match( source, /class-test-environment-guard\.php/ );
		assert.match( source, /assert_disposable_database\(\)/ );
	}
	assert.match( guard, /sentinel === \$database/ );
	assert.match( guard, /preg_match\(.*tests\?/ );
} );

test( 'sync coordinator cleanup restores fixtures without dropping pre-existing Admitad tables', () => {
	assert.doesNotMatch( coordinator, /DROP TABLE IF EXISTS \{\$table\}/ );
	assert.match( coordinator, /promokodiki_admitad_sync_snapshot/ );
	assert.match( coordinator, /promokodiki_admitad_sync_restore/ );
	assert.match( coordinator, /restore_option/ );
	assert.match( coordinator, /\$wpdb->replace/ );
	assert.match( coordinator, /sync_snapshot_cron/ );
	assert.match( coordinator, /sync_restore_cron/ );
	assert.match( coordinator, /wp_rand\( 700000000/ );
	assert.match( coordinator, /promokodiki_admitad_sync_cleanup\( \$post_ids,[\s\S]*\$campaign_id \)/ );
	assert.match( coordinator, /'meta_key'\s*=>\s*'campaign_id'/ );
	assert.match( coordinator, /'meta_key'\s*=>\s*'admitad_campaign_id'/ );
} );
