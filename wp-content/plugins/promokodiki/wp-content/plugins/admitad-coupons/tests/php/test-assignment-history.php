<?php
/**
 * Assignment locks, history, preview, apply, and rollback tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$post_ids = array();
$term_ids = array();
$snapshot_ids = array();
$user_ids = array();
$old_user = get_current_user_id();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$suffix = wp_generate_password( 8, false );
	$owner_id = wp_insert_user(
		array(
			'user_login' => 'assignment-owner-' . $suffix,
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$other_id = wp_insert_user(
		array(
			'user_login' => 'assignment-other-' . $suffix,
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$user_ids = array( $owner_id, $other_id );
	wp_set_current_user( $owner_id );
	$old    = wp_insert_term( 'Тест старая рубрика ' . $suffix, 'promocode_category' );
	$new    = wp_insert_term( 'Тест новая рубрика ' . $suffix, 'promocode_category' );
	if ( is_wp_error( $old ) || is_wp_error( $new ) ) {
		throw new RuntimeException( 'Unable to create assignment test terms.' );
	}
	$old_id   = (int) $old['term_id'];
	$new_id   = (int) $new['term_id'];
	$term_ids = array( $old_id, $new_id );
	$post_id  = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'Assignment test coupon',
		)
	);
	$post_ids[] = $post_id;
	update_post_meta( $post_id, 'admitad_coupon_id', 'assignment-test' );
	Promokodiki_Admitad_Import_Context::run(
		static function () use ( $post_id, $old_id ): void {
			wp_set_post_terms( $post_id, array( $old_id ), 'promocode_category', false );
			update_post_meta( $post_id, '_admitad_primary_term_id', $old_id );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'manual term edits create an absolute category lock',
		static function () use ( $post_id, $old_id, $new_id, &$snapshot_ids ): void {
			wp_set_post_terms( $post_id, array( $old_id ), 'promocode_category', false );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_admitad_category_locked', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $old_id ), get_post_meta( $post_id, '_admitad_locked_term_ids', true ) );

			$result = new Promokodiki_Admitad_Classification_Result(
				array( $new_id ),
				$new_id,
				'high',
				array( 'algorithm_version' => 'test', 'signals' => array( 'structured' ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! ( new Promokodiki_Admitad_Assignment_Service() )->assign( $post_id, $result, 'test' ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $old_id ),
				array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'assignment writes primary, explanation, and complete before-after history',
		static function () use ( $post_id, $old_id, $new_id ): void {
			delete_post_meta( $post_id, '_admitad_category_locked' );
			delete_post_meta( $post_id, '_admitad_locked_term_ids' );
			$result = new Promokodiki_Admitad_Classification_Result(
				array( $new_id ),
				$new_id,
				'high',
				array( 'algorithm_version' => 'test', 'signals' => array( array( 'source' => 'coupon_category' ) ) )
			);
			$history = new Promokodiki_Admitad_Classification_History_Repository();
			$service = new Promokodiki_Admitad_Assignment_Service( $history );
			Promokodiki_Admitad_Test_Harness::assert_true( $service->assign( $post_id, $result, 'test_assign' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( (string) $new_id, (string) get_post_meta( $post_id, '_admitad_primary_term_id', true ) );
			$row = $history->latest_for_post( $post_id );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $old_id ), $row['previous_terms'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $new_id ), $row['result_terms'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'test_assign', $row['trigger_name'] );
			Promokodiki_Admitad_Test_Harness::assert_true( ! empty( $row['explanation']['signals'] ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'bounded owner preview is immutable, applies once, and rolls back exactly',
		static function () use ( $post_id, $old_id, $new_id, $owner_id, $other_id, &$snapshot_ids ): void {
			Promokodiki_Admitad_Import_Context::run(
				static function () use ( $post_id, $old_id ): void {
					wp_set_post_terms( $post_id, array( $old_id ), 'promocode_category', false );
					update_post_meta( $post_id, '_admitad_primary_term_id', $old_id );
				}
			);
			$classifier = static fn(): Promokodiki_Admitad_Classification_Result => new Promokodiki_Admitad_Classification_Result(
				array( $new_id ),
				$new_id,
				'high',
				array( 'algorithm_version' => 'preview-test', 'signals' => array( 'fixture' ) )
			);
			$service = new Promokodiki_Admitad_Reclassification_Service( $classifier );
			$before  = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			$preview = $service->start_preview( array( $post_id ) );
			$snapshot_ids[] = $preview['id'];
			$preview_progress = $service->preview_next_batch( $preview['id'] );
			$after   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );

			Promokodiki_Admitad_Test_Harness::assert_same( $before, $after );
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewed', $preview_progress['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $post_id ), $service->get_snapshot( $preview['id'] )['post_ids'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewed', $service->get_snapshot( $preview['id'] )['status'] );
			wp_set_current_user( $other_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'foreign_snapshot', $service->start_apply( $preview['id'] )->get_error_code() );
			wp_set_current_user( $owner_id );

			Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $service->start_apply( $preview['id'] )['status'] );
			$applied = $service->apply_next_batch( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'applied', $applied['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $applied['changed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_snapshot_state', $service->apply_next_batch( $preview['id'] )->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $new_id ),
				array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'rolling_back', $service->start_rollback( $preview['id'] )['status'] );
			$rolled_back = $service->rollback_next_batch( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'rolled_back', $rolled_back['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $rolled_back['changed'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_snapshot_state', $service->rollback_next_batch( $preview['id'] )->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $old_id ),
				array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( $old_id, (int) get_post_meta( $post_id, '_admitad_primary_term_id', true ) );
		}
	);
} finally {
	global $wpdb;
	wp_set_current_user( $old_user );
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
	$wpdb->query( "DELETE FROM {$table} WHERE algorithm_version IN ('test','preview-test')" );
	foreach ( $snapshot_ids as $snapshot_id ) {
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
