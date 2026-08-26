<?php
/**
 * Bounded apply and rollback recovery tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user  = get_current_user_id();
$post_ids  = array();
$term_ids  = array();
$user_ids  = array();
$snapshots = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$suffix = strtolower( wp_generate_password( 8, false ) );
	$old_a  = wp_insert_term( 'Apply old A ' . $suffix, 'promocode_category' );
	$old_b  = wp_insert_term( 'Apply old B ' . $suffix, 'promocode_category' );
	$new    = wp_insert_term( 'Apply new ' . $suffix, 'promocode_category' );
	$old_a_id = (int) $old_a['term_id'];
	$old_b_id = (int) $old_b['term_id'];
	$new_id   = (int) $new['term_id'];
	$term_ids = array( $old_a_id, $old_b_id, $new_id );

	$owner_id = wp_insert_user( array( 'user_login' => 'apply-owner-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'owner-' . $suffix . '@example.test', 'role' => 'administrator' ) );
	$other_id = wp_insert_user( array( 'user_login' => 'apply-other-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'other-' . $suffix . '@example.test', 'role' => 'administrator' ) );
	$basic_id = wp_insert_user( array( 'user_login' => 'apply-basic-' . $suffix, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'basic-' . $suffix . '@example.test', 'role' => 'subscriber' ) );
	$user_ids = array( $owner_id, $other_id, $basic_id );
	wp_set_current_user( $owner_id );

	for ( $index = 0; $index < 52; ++$index ) {
		$post_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Apply fixture ' . $suffix . ' ' . $index ) );
		$post_ids[] = $post_id;
		update_post_meta( $post_id, 'admitad_coupon_id', 'apply-' . $suffix . '-' . $index );
		$previous = 0 === $index ? array( $old_a_id, $old_b_id ) : array( $old_a_id );
		Promokodiki_Admitad_Import_Context::run( static fn() => wp_set_post_terms( $post_id, $previous, 'promocode_category', false ) );
		update_post_meta( $post_id, '_admitad_primary_term_id', 0 === $index ? $old_b_id : $old_a_id );
	}

	Promokodiki_Admitad_Test_Harness::run(
		'confirmed owner-only apply and rollback are bounded, lock-safe, resumable, and exact',
		static function () use ( $post_ids, $old_a_id, $old_b_id, $new_id, $owner_id, $other_id, $basic_id, &$snapshots ): void {
			$classifier = static fn(): Promokodiki_Admitad_Classification_Result => new Promokodiki_Admitad_Classification_Result(
				array( $new_id ),
				$new_id,
				'high',
				array( 'algorithm_version' => 'apply-ajax-test' )
			);
			$service  = new Promokodiki_Admitad_Reclassification_Service( $classifier );
			$preview  = $service->preview( $post_ids );
			$snapshots[] = $preview['id'];
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewed', $service->snapshot_progress( $preview['id'] )['status'] );

			wp_set_current_user( $other_id );
			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $service->start_apply( $preview['id'] ) ) );
			wp_set_current_user( $basic_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $service->start_apply( $preview['id'] )->get_error_code() );
			wp_set_current_user( $owner_id );

			$nonce = wp_create_nonce( 'promokodiki_admitad_admin_ajax' );
			$missing_confirmation = Promokodiki_Admitad_Admin_Ajax::handle(
				array( 'operation' => 'snapshot_apply_start', 'page' => 'admitad-history', 'snapshot_id' => $preview['id'], '_ajax_nonce' => $nonce )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'confirmation_required', $missing_confirmation->get_error_code() );
			$expired = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $preview['id'] ) );
			$expired['expires_at'] = time() - 10;
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $preview['id'] ), $expired, false );
			$expired_start = Promokodiki_Admitad_Admin_Ajax::handle(
				array( 'operation' => 'snapshot_apply_start', 'page' => 'admitad-history', 'snapshot_id' => $preview['id'], 'confirmed' => '1', '_ajax_nonce' => $nonce )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_snapshot', $expired_start->get_error_code() );
			$expired['expires_at'] = time() + DAY_IN_SECONDS;
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $preview['id'] ), $expired, false );
			$started = Promokodiki_Admitad_Admin_Ajax::handle(
				array( 'operation' => 'snapshot_apply_start', 'page' => 'admitad-history', 'snapshot_id' => $preview['id'], 'confirmed' => '1', '_ajax_nonce' => $nonce )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $started['progress']['status'] );
			Promokodiki_Admitad_Test_Harness::assert_true( $started['progress']['expires_at'] > time() );

			update_post_meta( $post_ids[1], '_admitad_category_locked', 'yes' );
			update_post_meta( $post_ids[1], '_admitad_locked_term_ids', array( $old_a_id ) );
			$failure = static function ( int $object_id, $terms, array $tt_ids, string $taxonomy ) use ( $post_ids ): void {
				unset( $terms, $tt_ids );
				if ( $post_ids[3] === $object_id && 'promocode_category' === $taxonomy ) {
					throw new RuntimeException( 'secret C:\\private\\fixture.sql' );
				}
			};
			add_action( 'set_object_terms', $failure, 1, 4 );
			try {
				$lock_key = 'promokodiki_admitad_snapshot_lock_' . sanitize_key( $preview['id'] );
				add_option( $lock_key, array( 'token' => 'concurrent', 'owner_id' => $owner_id, 'heartbeat' => time() ), '', false );
				Promokodiki_Admitad_Test_Harness::assert_same( 'snapshot_locked', $service->apply_next_batch( $preview['id'] )->get_error_code() );
				delete_option( $lock_key );
				$first = $service->apply_next_batch( $preview['id'] );
				Promokodiki_Admitad_Test_Harness::assert_same( 50, $first['processed'] );
				Promokodiki_Admitad_Test_Harness::assert_same( 50, $first['cursor'] );
				Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $first['status'] );
				$paused = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $preview['id'] ) );
				$paused['expires_at'] = time() - 10;
				update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $preview['id'] ), $paused, false );
				$resumed = $service->start_apply( $preview['id'] );
				Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $resumed['status'] );
				Promokodiki_Admitad_Test_Harness::assert_true( $resumed['expires_at'] > time() );
				$second = $service->apply_next_batch( $preview['id'] );
			} finally {
				remove_action( 'set_object_terms', $failure, 1 );
			}
			Promokodiki_Admitad_Test_Harness::assert_same( 'applied', $second['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 52, $second['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $second['changed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $second['skipped'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $second['failed'] );
			Promokodiki_Admitad_Test_Harness::assert_true( false === str_contains( wp_json_encode( $second ), 'private' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $old_a_id ), array_map( 'intval', wp_get_object_terms( $post_ids[1], 'promocode_category', array( 'fields' => 'ids' ) ) ) );
			global $wpdb;
			$history_table = Promokodiki_Admitad_Schema::table( 'classification_history' );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$history_table} WHERE algorithm_version = 'apply-ajax-test' AND trigger_name = 'preview_apply'" ) );

			update_post_meta( $post_ids[2], '_admitad_category_locked', 'yes' );
			update_post_meta( $post_ids[2], '_admitad_locked_term_ids', array( $new_id ) );
			$rollback = $service->start_rollback( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'rolling_back', $rollback['status'] );
			Promokodiki_Admitad_Test_Harness::assert_true( $rollback['expires_at'] > time() );
			$rollback_first = $service->rollback_next_batch( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $rollback_first['processed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'rolling_back', $rollback_first['status'] );
			$rollback_final = $service->rollback_next_batch( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'rolled_back', $rollback_final['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $old_a_id, $old_b_id ), array_map( 'intval', wp_get_object_terms( $post_ids[0], 'promocode_category', array( 'fields' => 'ids' ) ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $old_b_id, (int) get_post_meta( $post_ids[0], '_admitad_primary_term_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $new_id ), array_map( 'intval', wp_get_object_terms( $post_ids[2], 'promocode_category', array( 'fields' => 'ids' ) ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $new_id, (int) get_post_meta( $post_ids[2], '_admitad_primary_term_id', true ) );
		}
	);
} finally {
	global $wpdb;
	wp_set_current_user( $old_user );
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	foreach ( $snapshots as $snapshot_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'snapshot_id' => $snapshot_id ), array( '%s' ) );
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
	$wpdb->query( "DELETE FROM " . Promokodiki_Admitad_Schema::table( 'classification_history' ) . " WHERE algorithm_version = 'apply-ajax-test'" );
}

Promokodiki_Admitad_Test_Harness::finish();
