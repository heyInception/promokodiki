<?php
/**
 * Full normalize, import, classification, lock, lifecycle, and retention test.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
require_once dirname( __DIR__ ) . '/fixtures/class-fixtures.php';

$prefix       = 'e2e-' . strtolower( wp_generate_password( 8, false ) );
$fixtures     = new Promokodiki_Admitad_Fixtures( $prefix );
$post_ids     = array();
$term_ids     = array();
$shop_term_ids = array();
$external_categories = array( random_int( 700000, 709999 ), random_int( 710000, 719999 ), random_int( 720000, 729999 ) );
$campaign_id  = random_int( 730000, 739999 );
$snapshot_id  = '';
$expired_snapshot_id = '';
$run_ids      = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$shoes    = wp_insert_term( 'E2E shoes ' . $prefix, 'promocode_category' );
	$clothing = wp_insert_term( 'E2E clothing ' . $prefix, 'promocode_category' );
	$travel   = wp_insert_term( 'E2E travel ' . $prefix, 'promocode_category' );
	$other    = get_term_by( 'slug', 'other', 'promocode_category' );
	if ( ! $other instanceof WP_Term ) {
		$created = wp_insert_term( 'Other', 'promocode_category', array( 'slug' => 'other' ) );
		$other   = get_term( (int) $created['term_id'], 'promocode_category' );
		$term_ids[] = (int) $other->term_id;
	}
	$shoes_id    = (int) $shoes['term_id'];
	$clothing_id = (int) $clothing['term_id'];
	$travel_id   = (int) $travel['term_id'];
	$other_id    = (int) $other->term_id;
	$term_ids    = array_merge( $term_ids, array( $shoes_id, $clothing_id, $travel_id ) );
	$maps        = new Promokodiki_Admitad_Category_Map_Repository();
	$maps->save( 'coupon', $external_categories[0], $shoes_id, 100 );
	$maps->save( 'coupon', $external_categories[1], $clothing_id, 90 );
	$maps->save( 'coupon', $external_categories[2], $travel_id, 100 );
	( new Promokodiki_Admitad_Company_Profile_Repository() )->save_profile(
		$campaign_id,
		0,
		array( $shoes_id, $clothing_id, $travel_id, $other_id ),
		40,
		'E2E Campaign'
	);
	$pipeline = new Promokodiki_Admitad_Import_Pipeline();

	Promokodiki_Admitad_Test_Harness::run(
		'empty-description structured coupon receives both mapped categories',
		static function () use ( $fixtures, $pipeline, $campaign_id, $external_categories, $shoes_id, $clothing_id, &$post_ids ): void {
			$raw = $fixtures->coupon(
				'lacoste-empty-description',
				array(
					'description' => '',
					'campaign'    => array( 'id' => (string) $campaign_id, 'name' => 'E2E Campaign' ),
					'categories'  => array(
						array( 'id' => $external_categories[0], 'name' => 'Shoes' ),
						array( 'id' => $external_categories[1], 'name' => 'Clothing' ),
					),
				)
			);
			$result = $pipeline->process( $raw, 501 );
			$post_ids[] = $result['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( '', $result['normalized']['description'] );
			$expected = array( $shoes_id, $clothing_id );
			$actual   = wp_get_object_terms( $result['post_id'], 'promocode_category', array( 'fields' => 'ids' ) );
			sort( $expected );
			sort( $actual );
			Promokodiki_Admitad_Test_Harness::assert_same( $expected, $actual );
			Promokodiki_Admitad_Test_Harness::assert_same( 'high', $result['confidence'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'described travel, low fallback, conflicting text, and action without code flow end to end',
		static function () use ( $fixtures, $pipeline, $campaign_id, $external_categories, $shoes_id, $travel_id, $other_id, $prefix, &$post_ids ): void {
			$travel = $pipeline->process(
				$fixtures->coupon(
					'travel-described',
					array(
						'description' => 'Отель и перелёт',
						'campaign'   => array( 'id' => (string) $campaign_id, 'name' => 'E2E Campaign' ),
						'categories' => array( array( 'id' => $external_categories[2], 'name' => 'Travel' ) ),
					)
				),
				502
			);
			$post_ids[] = $travel['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( array( $travel_id ), wp_get_object_terms( $travel['post_id'], 'promocode_category', array( 'fields' => 'ids' ) ) );

			$low = $pipeline->process(
				$fixtures->coupon(
					'low-confidence',
					array( 'campaign' => array( 'id' => '0', 'name' => 'Unknown' ), 'name' => 'Нейтральное предложение', 'description' => '' )
				),
				503
			);
			$post_ids[] = $low['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( array( $other_id ), wp_get_object_terms( $low['post_id'], 'promocode_category', array( 'fields' => 'ids' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'low', $low['confidence'] );

			$rules = new Promokodiki_Admitad_Rule_Repository();
			$rules->save( 'равный конфликт ' . $prefix, $shoes_id, 50, 'active', 'phrase', 'e2e' );
			$rules->save( 'равный конфликт ' . $prefix, $travel_id, 50, 'active', 'phrase', 'e2e' );
			$conflict = $pipeline->process(
				$fixtures->coupon(
					'conflict',
					array( 'campaign' => array( 'id' => '0', 'name' => 'Unknown' ), 'name' => 'равный конфликт ' . $prefix, 'description' => '' )
				),
				504
			);
			$post_ids[] = $conflict['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_true( ! empty( $conflict['explanation']['conflicts'] ) );

			$action = $pipeline->process(
				$fixtures->coupon( 'action-no-code', array( 'species' => 'action', 'promocode' => '', 'campaign' => array( 'id' => '0', 'name' => 'Unknown' ) ) ),
				505
			);
			$post_ids[] = $action['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_true( $action['post_id'] > 0 );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'manual locks survive updates and inactive coupons reactivate by stable ID',
		static function () use ( $fixtures, $pipeline, $campaign_id, $external_categories, $shoes_id, $travel_id, &$post_ids ): void {
			$raw = $fixtures->coupon(
				'locked-lifecycle',
				array(
					'campaign'   => array( 'id' => (string) $campaign_id, 'name' => 'E2E Campaign' ),
					'categories' => array( array( 'id' => $external_categories[0], 'name' => 'Shoes' ) ),
				)
			);
			$first = $pipeline->process( $raw, 506 );
			$post_ids[] = $first['post_id'];
			wp_update_post( array( 'ID' => $first['post_id'], 'post_title' => 'Редакторский заголовок', 'post_content' => 'Редакторский текст' ) );
			wp_set_post_terms( $first['post_id'], array( $travel_id ), 'promocode_category', false );
			$changed = $raw;
			$changed['name'] = 'API changed title';
			$changed['description'] = 'API changed content';
			$changed['categories'] = array( array( 'id' => $external_categories[0], 'name' => 'Shoes' ) );
			$pipeline->process( $changed, 507 );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторский заголовок', get_the_title( $first['post_id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторский текст', get_post_field( 'post_content', $first['post_id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $travel_id ), wp_get_object_terms( $first['post_id'], 'promocode_category', array( 'fields' => 'ids' ) ) );

			$inactive = $changed;
			$inactive['status'] = 'inactive';
			$pipeline->process( $inactive, 508 );
			Promokodiki_Admitad_Test_Harness::assert_same( 'no', get_post_meta( $first['post_id'], '_promocode_is_active', true ) );
			$reactivated = $pipeline->process( $changed, 509 );
			Promokodiki_Admitad_Test_Harness::assert_same( $first['post_id'], $reactivated['post_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $first['post_id'], '_promocode_is_active', true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( $shoes_id > 0 );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'retention removes expired details but preserves open queue and active rollback snapshot',
		static function () use ( $post_ids, $travel_id, $prefix, &$run_ids, &$snapshot_id, &$expired_snapshot_id ): void {
			global $wpdb;
			$runs     = new Promokodiki_Admitad_Sync_Run_Repository();
			$run_id   = $runs->start( 'coupon' );
			$run_ids[] = $run_id;
			$runs->complete( $run_id, array( 'processed' => 1 ) );
			$old_date = gmdate( 'Y-m-d H:i:s', time() - ( 91 * DAY_IN_SECONDS ) );
			$wpdb->update( Promokodiki_Admitad_Schema::table( 'sync_run' ), array( 'completed_at' => $old_date ), array( 'id' => $run_id ) );

			$history = new Promokodiki_Admitad_Classification_History_Repository();
			$latest  = $history->latest_for_post( $post_ids[0] );
			$wpdb->update( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'created_at' => $old_date ), array( 'id' => $latest['id'] ) );
			$snapshot_id = wp_generate_uuid4();
			$snapshot_row = $history->record(
				$post_ids[0],
				new Promokodiki_Admitad_Classification_Result( array( $travel_id ), $travel_id, 'high', array( 'algorithm_version' => 'e2e-retention' ) ),
				array(),
				0,
				'preview',
				$snapshot_id
			);
			$wpdb->update( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'created_at' => $old_date ), array( 'id' => $snapshot_row ) );
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ), array( 'status' => 'applied', 'owner_id' => 0, 'created_at' => time(), 'expires_at' => time() + DAY_IN_SECONDS ), false );
			$expired_snapshot_id = wp_generate_uuid4();
			$expired_snapshot_row = $history->record(
				$post_ids[0],
				new Promokodiki_Admitad_Classification_Result( array( $travel_id ), $travel_id, 'high', array( 'algorithm_version' => 'e2e-expired-preview' ) ),
				array(),
				0,
				'preview',
				$expired_snapshot_id
			);
			$preview_date = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) );
			$wpdb->update( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'created_at' => $preview_date ), array( 'id' => $expired_snapshot_row ) );
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $expired_snapshot_id ), array( 'status' => 'previewed', 'owner_id' => 0, 'created_at' => time() - ( 2 * DAY_IN_SECONDS ), 'expires_at' => time() - DAY_IN_SECONDS ), false );
			$queue_id = ( new Promokodiki_Admitad_Review_Queue_Repository() )->enqueue( 'coupon', $prefix . '-retention', 'low_confidence', array() );

			$result = ( new Promokodiki_Admitad_Retention() )->run();
			Promokodiki_Admitad_Test_Harness::assert_true( $result['sync_runs'] >= 1 );
			Promokodiki_Admitad_Test_Harness::assert_true( $result['history_rows'] >= 1 );
			Promokodiki_Admitad_Test_Harness::assert_same( null, $runs->get( $run_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( null !== $history->get_by_id( $snapshot_row ) );
			Promokodiki_Admitad_Test_Harness::assert_same( null, $history->get_by_id( $expired_snapshot_row ) );
			Promokodiki_Admitad_Test_Harness::assert_true( null !== ( new Promokodiki_Admitad_Review_Queue_Repository() )->get_open( $queue_id ) );
		}
	);
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		$shop_term_ids = array_merge( $shop_term_ids, array_map( 'intval', wp_get_object_terms( $post_id, 'shops_category', array( 'fields' => 'ids' ) ) ) );
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'post_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	foreach ( array_unique( $shop_term_ids ) as $shop_term_id ) {
		wp_delete_term( $shop_term_id, 'shops_category' );
	}
	foreach ( $external_categories as $external_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'external_category_id' => $external_id ), array( '%d' ) );
	}
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'rule' ), array( 'source' => 'e2e' ), array( '%s' ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Promokodiki_Admitad_Schema::table( 'review_queue' ) . ' WHERE entity_id LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
	foreach ( $run_ids as $run_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'sync_run' ), array( 'id' => $run_id ), array( '%d' ) );
	}
	if ( $snapshot_id ) {
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
	if ( $expired_snapshot_id ) {
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $expired_snapshot_id ) );
	}
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
