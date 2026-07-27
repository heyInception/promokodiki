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

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$suffix = wp_generate_password( 8, false );
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
		'preview is immutable, stores affected rows, applies once, and rolls back exactly',
		static function () use ( $post_id, $old_id, $new_id ): void {
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
			$preview = $service->preview( array( $post_id ) );
			$snapshot_ids[] = $preview['id'];
			$after   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );

			Promokodiki_Admitad_Test_Harness::assert_same( $before, $after );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $post_id ), $preview['post_ids'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'previewed', $service->get_snapshot( $preview['id'] )['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $service->apply_preview( $preview['id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $service->apply_preview( $preview['id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $new_id ),
				array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $service->rollback( $preview['id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $old_id ),
				array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) )
			);
			$service->schedule_apply( $preview['id'] );
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== wp_next_scheduled( 'promokodiki_admitad_apply_classification', array( $preview['id'], 0 ) )
			);
			wp_clear_scheduled_hook( 'promokodiki_admitad_apply_classification', array( $preview['id'], 0 ) );
		}
	);
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
	$wpdb->query( "DELETE FROM {$table} WHERE algorithm_version IN ('test','preview-test')" );
	foreach ( $snapshot_ids as $snapshot_id ) {
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
