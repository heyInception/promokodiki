<?php
/**
 * Mapping, rule, company, and review administration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user = get_current_user_id();
$user_ids = array();
$post_ids = array();
$term_ids = array();
$queue_ids = array();
$external_id = random_int( 960000, 969999 );
$campaign_id = random_int( 970000, 979999 );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$term     = wp_insert_term( 'Admin mapping ' . $external_id, 'promocode_category' );
	$term_id  = (int) $term['term_id'];
	$term_ids[] = $term_id;
	$admin_id = wp_insert_user(
		array(
			'user_login' => 'admitad-map-admin-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$editor_id = wp_insert_user(
		array(
			'user_login' => 'admitad-map-editor-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'editor',
		)
	);
	$user_ids = array( $admin_id, $editor_id );

	Promokodiki_Admitad_Test_Harness::run(
		'repositories provide bounded searchable administration lists',
		static function () use ( $external_id, $campaign_id, $term_id ): void {
			$maps = new Promokodiki_Admitad_Category_Map_Repository();
			$maps->save( 'coupon', $external_id, $term_id, 100 );
			$map_page = $maps->list_rows( (string) $external_id, 1, 10 );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $map_page['items'] ) );

			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$profiles->save_profile( $campaign_id, $term_id, array( $term_id ), 40, 'Admin Campaign' );
			$company_page = $profiles->list_rows( 'Admin Campaign', 1, 10 );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $company_page['items'] ) );

			$rules = new Promokodiki_Admitad_Rule_Repository();
			$rules->save( 'точная безопасная фраза', $term_id, 20, 'candidate', 'phrase', 'test' );
			$rule_page = $rules->list_rows( 'безопасная', 1, 10 );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $rule_page['items'] ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'editor resolves one coupon but cannot create global mappings',
		static function () use ( $editor_id, $external_id, $term_id, &$post_ids, &$queue_ids ): void {
			$post_id    = wp_insert_post(
				array(
					'post_type'   => 'promocode',
					'post_status' => 'publish',
					'post_title'  => 'Queue coupon',
				)
			);
			$post_ids[] = $post_id;
			update_post_meta( $post_id, 'admitad_coupon_id', (string) $external_id );
			$queue      = new Promokodiki_Admitad_Review_Queue_Repository();
			$queue_id   = $queue->enqueue( 'coupon', (string) $external_id, 'low_confidence', array( 'unsafe' => '<script>alert(1)</script>' ) );
			$queue_ids[] = $queue_id;

			wp_set_current_user( $editor_id );
			$actions = new Promokodiki_Admitad_Admin_Actions();
			$result  = $actions->resolve_coupon_only( $queue_id, array( $term_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( true === $result );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_admitad_category_locked', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $term_id ), wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $actions->create_global_category_map( 'coupon', $external_id + 1, $term_id ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( null, $queue->get_open( $queue_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				$queue_id,
				$queue->enqueue( 'coupon', (string) $external_id, 'low_confidence', array( 'unsafe' => '<script>alert(1)</script>' ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( null !== $queue->get_open( $queue_id ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'administrator creates global state and pages escape queue evidence',
		static function () use ( $admin_id, $external_id, $campaign_id, $term_id ): void {
			wp_set_current_user( $admin_id );
			$actions = new Promokodiki_Admitad_Admin_Actions();
			Promokodiki_Admitad_Test_Harness::assert_true( true === $actions->create_global_category_map( 'coupon', $external_id + 1, $term_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( $term_id ),
				( new Promokodiki_Admitad_Category_Map_Repository() )->terms_for_external( 'coupon', $external_id + 1 )
			);
			foreach ( array( 'Promokodiki_Admitad_Category_Map_Page', 'Promokodiki_Admitad_Company_Page', 'Promokodiki_Admitad_Rule_Page', 'Promokodiki_Admitad_Review_Page' ) as $class_name ) {
				ob_start();
				( new $class_name() )->render();
				$html = (string) ob_get_clean();
				Promokodiki_Admitad_Test_Harness::assert_true( '' !== $html );
				Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $html, '<script>alert(1)</script>' ) );
			}
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action( 'admin_post_promokodiki_admitad_mapping_action', array( 'Promokodiki_Admitad_Admin_Actions', 'handle_mapping_action' ) )
			);
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
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	foreach ( $queue_ids as $queue_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'review_queue' ), array( 'id' => $queue_id ), array( '%d' ) );
	}
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'external_category_id' => $external_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'external_category_id' => $external_id + 1 ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	$wpdb->query( "DELETE FROM " . Promokodiki_Admitad_Schema::table( 'rule' ) . " WHERE source = 'test'" );
}

Promokodiki_Admitad_Test_Harness::finish();
