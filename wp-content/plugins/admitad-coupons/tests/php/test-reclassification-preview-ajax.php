<?php
/**
 * Bounded immutable recovery preview tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$post_ids   = array();
$term_ids   = array();
$snapshots  = array();
$trash_id   = 0;
$manual_id  = 0;
$old_user   = get_current_user_id();
$user_ids   = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$suffix = strtolower( wp_generate_password( 8, false ) );
	$owner_id = wp_insert_user( array( 'user_login' => 'preview-owner-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'preview-owner-' . $suffix . '@example.test', 'role' => 'administrator' ) );
	$other_id = wp_insert_user( array( 'user_login' => 'preview-other-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'preview-other-' . $suffix . '@example.test', 'role' => 'administrator' ) );
	$user_ids = array( $owner_id, $other_id );
	wp_set_current_user( $owner_id );
	$old    = wp_insert_term( 'Preview old ' . $suffix, 'promocode_category' );
	$new    = wp_insert_term( 'Preview new ' . $suffix, 'promocode_category' );
	$old_id = (int) $old['term_id'];
	$new_id = (int) $new['term_id'];
	$term_ids = array( $old_id, $new_id );

	for ( $index = 0; $index < 53; ++$index ) {
		$post_id    = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 2 === $index ? 'Preview unchanged ' . $suffix : 'Preview affected ' . $suffix . ' ' . $index,
			)
		);
		$post_ids[] = $post_id;
		update_post_meta( $post_id, 'admitad_coupon_id', $suffix . '-' . $index );
		update_post_meta( $post_id, '_admitad_primary_term_id', $old_id );
		Promokodiki_Admitad_Import_Context::run(
			static fn() => wp_set_post_terms( $post_id, array( $old_id ), 'promocode_category', false )
		);
	}
	update_post_meta( $post_ids[1], '_admitad_category_locked', 'yes' );
	update_post_meta( $post_ids[1], '_admitad_locked_term_ids', array( $old_id ) );

	$trash_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'trash', 'post_title' => 'Preview trashed ' . $suffix ) );
	update_post_meta( $trash_id, 'admitad_coupon_id', $suffix . '-trash' );
	$manual_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Preview manual ' . $suffix ) );

	Promokodiki_Admitad_Test_Harness::run(
		'full preview is stable, bounded, lock-safe, taxonomy-read-only, resumable, and history-idempotent',
		static function () use ( $post_ids, $old_id, $new_id, $trash_id, $manual_id, $owner_id, $other_id, &$snapshots ): void {
			global $wpdb;
			$classifier = static function ( array $coupon ) use ( $old_id, $new_id ): Promokodiki_Admitad_Classification_Result {
				$term_id = str_contains( (string) $coupon['title'], 'unchanged' ) ? $old_id : $new_id;
				return new Promokodiki_Admitad_Classification_Result(
					array( $term_id ),
					$term_id,
					'high',
					array( 'algorithm_version' => 'preview-ajax-test', 'reason_ru' => 'Проверка предварительного просмотра.' )
				);
			};
			$service       = new Promokodiki_Admitad_Reclassification_Service( $classifier );
			$before_terms  = array();
			foreach ( $post_ids as $post_id ) {
				$before_terms[ $post_id ] = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			}
			$taxonomy_count = wp_count_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false ) );
			$started        = $service->start_preview();
			$snapshots[]    = $started['id'];
			$raw_state      = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $started['id'] ) );

			Promokodiki_Admitad_Test_Harness::assert_same( $post_ids, $raw_state['source_post_ids'] );
			Promokodiki_Admitad_Test_Harness::assert_true( ! in_array( $trash_id, $raw_state['source_post_ids'], true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! in_array( $manual_id, $raw_state['source_post_ids'], true ) );
			$raw_state['expires_at'] = time() - 10;
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $started['id'] ), $raw_state, false );
			wp_set_current_user( $other_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'foreign_snapshot', $service->preview_progress( $started['id'] )->get_error_code() );
			wp_set_current_user( $owner_id );
			$lock_key = 'promokodiki_admitad_snapshot_lock_' . sanitize_key( $started['id'] );
			add_option( $lock_key, array( 'token' => 'concurrent', 'owner_id' => $owner_id, 'heartbeat' => time() ), '', false );
			Promokodiki_Admitad_Test_Harness::assert_same( 'snapshot_locked', $service->preview_next_batch( $started['id'] )->get_error_code() );
			delete_option( $lock_key );

			$first = $service->preview_next_batch( $started['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $first['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $first['cursor'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewing', $first['status'] );
			$history_table = Promokodiki_Admitad_Schema::table( 'classification_history' );
			$first_rows    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$history_table} WHERE snapshot_id = %s AND trigger_name = 'preview'", $started['id'] ) );
			$immutable     = ( new Promokodiki_Admitad_Classification_History_Repository() )->snapshot_rows( $started['id'] );

			$retry_state = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $started['id'] ) );
			foreach ( array( 'cursor', 'processed', 'affected', 'unchanged', 'locked', 'failed' ) as $counter ) {
				$retry_state[ $counter ] = 0;
			}
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $started['id'] ), $retry_state, false );
			$retry = $service->preview_next_batch( $started['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $retry['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $first_rows, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$history_table} WHERE snapshot_id = %s AND trigger_name = 'preview'", $started['id'] ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $immutable, ( new Promokodiki_Admitad_Classification_History_Repository() )->snapshot_rows( $started['id'] ) );

			$final = $service->preview_next_batch( $started['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewed', $final['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 53, $final['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 51, $final['affected'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $final['locked'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $final['unchanged'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $final['failed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 51, count( $service->get_snapshot( $started['id'] )['rows'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $taxonomy_count, wp_count_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false ) ) );
			foreach ( $post_ids as $post_id ) {
				Promokodiki_Admitad_Test_Harness::assert_same( $before_terms[ $post_id ], array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) ) );
			}
		}
	);
} finally {
	global $wpdb;
	wp_set_current_user( $old_user );
	foreach ( array_merge( $post_ids, array( $trash_id, $manual_id ) ) as $post_id ) {
		if ( $post_id > 0 ) {
			wp_delete_post( $post_id, true );
		}
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	foreach ( $user_ids as $user_id ) { wp_delete_user( $user_id ); }
	foreach ( $snapshots as $snapshot_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'snapshot_id' => $snapshot_id ), array( '%s' ) );
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
