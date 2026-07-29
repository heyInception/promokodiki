<?php
/** Recovery controls remain reachable only through bounded AJAX. */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user = get_current_user_id();
$user_id  = 0;
$post_id  = 0;
$term_ids = array();
$snapshot_id = '';
try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$suffix  = strtolower( wp_generate_password( 8, false ) );
	$user_id = wp_insert_user( array( 'user_login' => 'recovery-ui-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'ui-' . $suffix . '@example.test', 'role' => 'administrator' ) );
	wp_set_current_user( $user_id );
	$old = wp_insert_term( 'Recovery UI old ' . $suffix, 'promocode_category' );
	$new = wp_insert_term( 'Recovery UI new ' . $suffix, 'promocode_category' );
	$term_ids = array( (int) $old['term_id'], (int) $new['term_id'] );
	$post_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Recovery UI fixture' ) );
	Promokodiki_Admitad_Import_Context::run( static fn() => wp_set_post_terms( $post_id, array( $term_ids[0] ), 'promocode_category', false ) );
	update_post_meta( $post_id, '_admitad_primary_term_id', $term_ids[0] );
	$service = new Promokodiki_Admitad_Reclassification_Service(
		static fn() => new Promokodiki_Admitad_Classification_Result( array( $term_ids[1] ), $term_ids[1], 'high', array( 'algorithm_version' => 'recovery-ui-test' ) )
	);
	$preview = $service->preview( array( $post_id ) );
	$snapshot_id = $preview['id'];

	Promokodiki_Admitad_Test_Harness::run(
		'recovery and snapshot controls expose only nonce-backed bounded AJAX operations',
		static function () use ( $snapshot_id ): void {
			ob_start();
			( new Promokodiki_Admitad_Diagnostics_Page() )->render();
			$diagnostics = (string) ob_get_clean();
			foreach ( array( 'recovery_reference_start', 'recovery_migration_start', 'recovery_migration_status', 'data-admitad-ajax', '_ajax_nonce' ) as $needle ) {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $diagnostics, $needle ), 'Missing diagnostics control: ' . $needle );
			}
			Promokodiki_Admitad_Test_Harness::assert_true( ! preg_match( '/[A-Z]:\\\\[^<\"]+/i', $diagnostics ) );

			$_GET['snapshot'] = $snapshot_id;
			ob_start();
			( new Promokodiki_Admitad_History_Page() )->render();
			$history = (string) ob_get_clean();
			unset( $_GET['snapshot'] );
			foreach ( array( 'preview_start', 'snapshot_apply_start', 'snapshot_apply_step', 'snapshot_status', 'confirmed', 'value="1"', $snapshot_id, 'data-admitad-ajax' ) as $needle ) {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $history, $needle ), 'Missing history control: ' . $needle );
			}
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $history, 'JavaScript' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $history, 'operation" value="apply"' ) );
		}
	);
} finally {
	global $wpdb;
	unset( $_GET['snapshot'] );
	wp_set_current_user( $old_user );
	if ( $post_id > 0 ) { wp_delete_post( $post_id, true ); }
	if ( $user_id > 0 ) { wp_delete_user( $user_id ); }
	foreach ( array_reverse( $term_ids ) as $term_id ) { wp_delete_term( $term_id, 'promocode_category' ); }
	if ( $snapshot_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'snapshot_id' => $snapshot_id ), array( '%s' ) );
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
}
Promokodiki_Admitad_Test_Harness::finish();
