const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );

const root = path.resolve( __dirname, '../..' );
const runner = fs.readFileSync( path.join( root, 'tests/run-all.ps1' ), 'utf8' );
const coordinator = fs.readFileSync( path.join( root, 'tests/php/test-sync-coordinator.php' ), 'utf8' );

test( 'the full integration runner refuses an unverified database before tests can execute', () => {
	assert.match( runner, /PROMOKODIKI_ADMITAD_TEST_DATABASE/ );
	assert.match( runner, /DB_NAME/ );
	assert.match( runner, /(?:_test|_tests|test_)/i );
	assert.match( runner, /throw\s+["'].*(?:disposable|test database|refus)/i );
} );

test( 'sync coordinator cleanup restores fixtures without dropping pre-existing Admitad tables', () => {
	assert.doesNotMatch( coordinator, /DROP TABLE IF EXISTS \{\$table\}/ );
	assert.match( coordinator, /promokodiki_admitad_sync_snapshot/ );
	assert.match( coordinator, /promokodiki_admitad_sync_restore/ );
	assert.match( coordinator, /restore_option/ );
} );
