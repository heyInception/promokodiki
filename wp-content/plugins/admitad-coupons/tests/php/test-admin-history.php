<?php
/**
 * History UI, stored preview, and validation sample tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user    = get_current_user_id();
$user_ids    = array();
$post_ids    = array();
$term_ids    = array();
$snapshot_ids = array();
$sample_ids  = array();
$validation_campaign = random_int( 980000, 989998 );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$suffix = wp_generate_password( 8, false );
	$old    = wp_insert_term( 'History old ' . $suffix, 'promocode_category' );
	$new    = wp_insert_term( 'History new ' . $suffix, 'promocode_category' );
	$other  = get_term_by( 'slug', 'other', 'promocode_category' );
	if ( ! $other instanceof WP_Term ) {
		$created = wp_insert_term( 'Other', 'promocode_category', array( 'slug' => 'other' ) );
		$other   = get_term( (int) $created['term_id'], 'promocode_category' );
		$term_ids[] = (int) $other->term_id;
	}
	$old_id     = (int) $old['term_id'];
	$new_id     = (int) $new['term_id'];
	$other_id   = (int) $other->term_id;
	$term_ids[] = $old_id;
	$term_ids[] = $new_id;
	$admin_id   = wp_insert_user(
		array(
			'user_login' => 'admitad-history-' . $suffix,
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$user_ids[] = $admin_id;
	$other_admin_id = wp_insert_user(
		array(
			'user_login' => 'admitad-history-other-' . $suffix,
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$user_ids[] = $other_admin_id;
	wp_set_current_user( $admin_id );

	$post_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'History preview' ) );
	$locked_id  = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'History locked' ) );
	$post_ids   = array( $post_id, $locked_id );
	Promokodiki_Admitad_Import_Context::run(
		static function () use ( $post_id, $locked_id, $old_id ): void {
			wp_set_post_terms( $post_id, array( $old_id ), 'promocode_category', false );
			wp_set_post_terms( $locked_id, array( $old_id ), 'promocode_category', false );
		}
	);
	update_post_meta( $locked_id, '_admitad_category_locked', 'yes' );

	Promokodiki_Admitad_Test_Harness::run(
		'stored preview is immutable, owner-protected, expiry-safe, and excludes locks',
		static function () use ( $post_id, $locked_id, $old_id, $new_id, $admin_id, $other_admin_id, &$snapshot_ids ): void {
			$classifier = static fn(): Promokodiki_Admitad_Classification_Result => new Promokodiki_Admitad_Classification_Result(
				array( $new_id ),
				$new_id,
				'high',
				array( 'algorithm_version' => 'admin-history-test' )
			);
			$service    = new Promokodiki_Admitad_Reclassification_Service( $classifier );
			$before     = wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) );
			$snapshot   = $service->preview( array( $post_id, $locked_id ) );
			$snapshot_ids[] = $snapshot['id'];
			Promokodiki_Admitad_Test_Harness::assert_same( $before, wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $post_id ), $snapshot['post_ids'] );
			Promokodiki_Admitad_Test_Harness::assert_same( get_current_user_id(), $service->get_snapshot( $snapshot['id'] )['owner_id'] );
			$actions = new Promokodiki_Admitad_Admin_Actions();
			$nonce   = wp_create_nonce( 'promokodiki_admitad_history_action' );
			Promokodiki_Admitad_Test_Harness::assert_true( true === $actions->schedule_snapshot_apply( $snapshot['id'], $nonce ) );
			$first_schedule = wp_next_scheduled( 'promokodiki_admitad_apply_classification', array( $snapshot['id'], 0 ) );
			Promokodiki_Admitad_Test_Harness::assert_true( true === $actions->schedule_snapshot_apply( $snapshot['id'], $nonce ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				$first_schedule,
				wp_next_scheduled( 'promokodiki_admitad_apply_classification', array( $snapshot['id'], 0 ) )
			);
			wp_clear_scheduled_hook( 'promokodiki_admitad_apply_classification', array( $snapshot['id'], 0 ) );

			$state               = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot['id'] ) );
			$state['expires_at'] = time() - 1;
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot['id'] ), $state, false );
			Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $service->get_snapshot( $snapshot['id'] )['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'applying', $service->snapshot_progress( $snapshot['id'] )['status'] );
			wp_set_current_user( $other_admin_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'foreign_snapshot', $service->snapshot_progress( $snapshot['id'] )->get_error_code() );
			wp_set_current_user( $admin_id );

			$state['status'] = 'previewed';
			update_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot['id'] ), $state, false );
			Promokodiki_Admitad_Test_Harness::assert_same( null, $service->get_snapshot( $snapshot['id'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_snapshot', $service->snapshot_progress( $snapshot['id'] )->get_error_code() );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'history actions require stored snapshot IDs and render escaped rows',
		static function () use ( $admin_id ): void {
			wp_set_current_user( $admin_id );
			$actions = new Promokodiki_Admitad_Admin_Actions();
			$result  = $actions->schedule_snapshot_apply( 'not-a-stored-snapshot', wp_create_nonce( 'promokodiki_admitad_history_action' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $result ) );
			ob_start();
			( new Promokodiki_Admitad_History_Page() )->render();
			$html = (string) ob_get_clean();
			Promokodiki_Admitad_Test_Harness::assert_true( '' !== $html );
			Promokodiki_Admitad_Test_Harness::assert_true( false !== has_action( 'admin_post_promokodiki_admitad_history_action', array( 'Promokodiki_Admitad_Admin_Actions', 'handle_history_action' ) ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'validation report calculates 95, 85, 100, and 0 acceptance metrics',
		static function () use ( $admin_id, $new_id, $other_id, $validation_campaign, &$post_ids, &$sample_ids ): void {
			wp_set_current_user( $admin_id );
			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$profiles->save_profile( $validation_campaign, $new_id, array( $new_id, $other_id ), 40, 'Validation A' );
			$profiles->save_profile( $validation_campaign + 1, $new_id, array( $new_id, $other_id ), 40, 'Validation B' );
			for ( $index = 0; $index < 20; ++$index ) {
				$term_id = $index < 17 ? $new_id : $other_id;
				$id      = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Validation ' . $index ) );
				$post_ids[] = $id;
				Promokodiki_Admitad_Import_Context::run( static fn() => wp_set_post_terms( $id, array( $term_id ), 'promocode_category', false ) );
				update_post_meta( $id, '_admitad_primary_term_id', $term_id );
				update_post_meta( $id, '_admitad_classification_confidence', 'high' );
				update_post_meta( $id, '_admitad_category_locked', 'yes' );
				update_post_meta( $id, '_admitad_locked_term_ids', array( $term_id ) );
				update_post_meta( $id, 'campaign_id', (string) ( $validation_campaign + ( $index % 2 ) ) );
			}
			$validation = new Promokodiki_Admitad_Validation_Service();
			$sample_id  = $validation->create_sample( 20 );
			$sample_ids[] = $sample_id;
			$sample = $validation->sample( $sample_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 20, count( $sample['rows'] ) );
			foreach ( $sample['rows'] as $index => $row ) {
				$expected = 19 === $index ? array( $new_id === $row['term_ids'][0] ? $other_id : $new_id ) : $row['term_ids'];
				$validation->record_review( $sample_id, (int) $row['post_id'], $expected );
			}
			$report = $validation->report( $sample_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 95.0, $report['high_confidence_accuracy'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 85.0, $report['non_other_coverage'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 100.0, $report['lock_preservation'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0.0, $report['out_of_profile_rate'] );
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
	$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
	$wpdb->query( "DELETE FROM {$table} WHERE algorithm_version = 'admin-history-test'" );
	foreach ( $snapshot_ids as $snapshot_id ) {
		wp_clear_scheduled_hook( 'promokodiki_admitad_apply_classification', array( $snapshot_id, 0 ) );
		delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id ) );
	}
	foreach ( $sample_ids as $sample_id ) {
		delete_option( 'promokodiki_admitad_validation_' . sanitize_key( $sample_id ) );
	}
	foreach ( array( $validation_campaign, $validation_campaign + 1 ) as $campaign_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
