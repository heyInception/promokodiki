<?php
/**
 * Filtered administration repository contracts.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids      = array();
$rule_ids      = array();
$queue_ids     = array();
$history_ids   = array();
$fixture_token = wp_generate_uuid4();
$campaign_id   = random_int( 2000000000, 2100000000 );
$external_id   = random_int( 2100000001, 2140000000 );
$snapshots     = array();

/**
 * Snapshot an exact fixture scope before it is mutated.
 *
 * @param string               $table Plugin-owned table.
 * @param array<string, mixed> $where Exact fixture predicate.
 * @return array<int, array<string, mixed>>
 */
function promokodiki_admitad_list_test_snapshot( string $table, array $where ): array {
	global $wpdb;

	$clauses = array();
	$args    = array();
	foreach ( $where as $column => $value ) {
		$clauses[] = $column . ( is_int( $value ) ? ' = %d' : ' = %s' );
		$args[]    = $value;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Test-only query is limited to an exact fixture predicate on a plugin-owned table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE " . implode( ' AND ', $clauses ), ...$args ), ARRAY_A );
}

/**
 * Restore only an exact fixture scope to its pre-test rows.
 *
 * @param string               $table Plugin-owned table.
 * @param array<string, mixed> $where Exact fixture predicate.
 * @param array<int, array<string, mixed>> $rows Snapshotted rows.
 */
function promokodiki_admitad_list_test_restore( string $table, array $where, array $rows ): void {
	global $wpdb;

	$formats = array();
	foreach ( $where as $value ) {
		$formats[] = is_int( $value ) ? '%d' : '%s';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test-only deletion is constrained to the exact fixture scope.
	$wpdb->delete( $table, $where, $formats );
	foreach ( $rows as $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Restores only pre-test rows from the exact fixture snapshot.
		$wpdb->insert( $table, $row );
	}
}

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	$term = wp_insert_term( 'Admin list fixture ' . $fixture_token, 'promocode_category' );
	if ( is_wp_error( $term ) ) {
		throw new RuntimeException( 'Unable to create an isolated category fixture.' );
	}
	$term_id    = (int) $term['term_id'];
	$term_ids[] = $term_id;

	$map_table     = Promokodiki_Admitad_Schema::table( 'category_map' );
	$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
	$category_table = Promokodiki_Admitad_Schema::table( 'company_category' );
	$queue_table   = Promokodiki_Admitad_Schema::table( 'review_queue' );
	$history_table = Promokodiki_Admitad_Schema::table( 'classification_history' );
	$snapshots['map']       = promokodiki_admitad_list_test_snapshot( $map_table, array( 'source_namespace' => 'coupon', 'external_category_id' => $external_id ) );
	$snapshots['profile']   = promokodiki_admitad_list_test_snapshot( $profile_table, array( 'campaign_id' => $campaign_id ) );
	$snapshots['category']  = promokodiki_admitad_list_test_snapshot( $category_table, array( 'campaign_id' => $campaign_id ) );

	Promokodiki_Admitad_Test_Harness::run(
		'filtered lists clamp page sizes, order stably, and include archived rules',
		static function () use ( $external_id, $campaign_id, $term_id, $fixture_token, &$rule_ids ): void {
			global $wpdb;
			$now           = gmdate( 'Y-m-d H:i:s' );
			$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Creates an isolated profile fixture in a pre-snapshotted scope.
			$wpdb->insert(
				$profile_table,
				array(
					'campaign_id'       => $campaign_id,
					'display_name'      => 'Campaign ' . $fixture_token,
					'default_term_id'   => 0,
					'signal_weight'     => 17,
					'status'            => 'active',
					'category_snapshot' => '{"preserved":true}',
					'created_at'        => '2001-02-03 04:05:06',
					'updated_at'        => $now,
				)
			);
			$maps = new Promokodiki_Admitad_Category_Map_Repository();
			$maps->save( 'coupon', $external_id, $term_id, 100 );
			$map_page = $maps->list_rows( (string) $external_id, 1, 21, array( 'source_namespace' => 'coupon' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 20, $map_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $external_id, (int) $map_page['items'][0]['external_category_id'] );

			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$company_page = $profiles->list_rows( $fixture_token, 1, 50, array( 'status' => 'active' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $company_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $campaign_id, (int) $company_page['items'][0]['campaign_id'] );

			$rules   = new Promokodiki_Admitad_Rule_Repository();
			$rule_id = $rules->save( 'fixture ' . $fixture_token, $term_id, 20, 'candidate', 'phrase', 'admin_list_fixture' );
			$rule_ids[] = $rule_id;
			Promokodiki_Admitad_Test_Harness::assert_true( $rules->set_status( $rule_id, 'archived' ) );
			$rule_page = $rules->list_rows( $fixture_token, 1, 50, array( 'status' => 'archived' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $rule_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $rule_id, (int) $rule_page['items'][0]['id'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'campaign autocomplete returns only stable campaign pairs and profile saves preserve reference fields',
		static function () use ( $campaign_id, $term_id, $fixture_token ): void {
			global $wpdb;
			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$results  = $profiles->search_campaigns( $fixture_token, 99 );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( array( 'campaign_id' => $campaign_id, 'display_name' => 'Campaign ' . $fixture_token ) ),
				$results
			);
			$profiles->save_profile( $campaign_id, $term_id, array( $term_id ), 42, 'Editable ' . $fixture_token );
			$table = Promokodiki_Admitad_Schema::table( 'company_profile' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifies the isolated profile fixture.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT display_name, created_at, category_snapshot FROM {$table} WHERE campaign_id = %d", $campaign_id ), ARRAY_A );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Editable ' . $fixture_token, $row['display_name'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '2001-02-03 04:05:06', $row['created_at'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '{"preserved":true}', $row['category_snapshot'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'review filters change both items and totals while history filters remain bounded',
		static function () use ( $fixture_token, &$queue_ids, &$history_ids ): void {
			global $wpdb;
			$queue = new Promokodiki_Admitad_Review_Queue_Repository();
			$queue_ids[] = $queue->enqueue( 'coupon', 'review-low-' . $fixture_token, 'low_confidence', array() );
			$queue_ids[] = $queue->enqueue( 'coupon', 'review-missing-' . $fixture_token, 'missing_mapping', array() );
			$filtered = $queue->list_rows( $fixture_token, 1, 100, array( 'status' => 'open', 'reason' => 'low_confidence' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $filtered['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $filtered['items'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'low_confidence', $filtered['items'][0]['reason_code'] );

			$table       = Promokodiki_Admitad_Schema::table( 'classification_history' );
			$snapshot_id = wp_generate_uuid4();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Creates an isolated immutable-history fixture with its own UUID.
			$wpdb->insert(
				$table,
				array(
					'snapshot_id'              => $snapshot_id,
					'post_id'                  => 0,
					'algorithm_version'        => 'admin-list-fixture',
					'rule_version'             => 1,
					'previous_terms'           => '[]',
					'result_terms'             => '[]',
					'previous_primary_term_id' => 0,
					'result_primary_term_id'   => 0,
					'confidence'               => 'low',
					'explanation'              => '[]',
					'trigger_name'             => 'admin_list_fixture',
					'actor_id'                 => 0,
					'created_at'               => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			$history_ids[] = (int) $wpdb->insert_id;
			$history = ( new Promokodiki_Admitad_Classification_History_Repository() )->list_rows( '', 1, 21, array( 'snapshot_id' => $snapshot_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 20, $history['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $history['total'] );
		}
	);
} finally {
	global $wpdb;
	foreach ( $queue_ids as $queue_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes only recorded fixture IDs.
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'review_queue' ), array( 'id' => $queue_id ), array( '%d' ) );
	}
	foreach ( $history_ids as $history_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes only recorded fixture IDs.
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'classification_history' ), array( 'id' => $history_id ), array( '%d' ) );
	}
	foreach ( $rule_ids as $rule_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes only recorded fixture IDs.
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'rule' ), array( 'id' => $rule_id ), array( '%d' ) );
	}
	if ( isset( $snapshots['map'] ) ) {
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'source_namespace' => 'coupon', 'external_category_id' => $external_id ), $snapshots['map'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), $snapshots['category'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), $snapshots['profile'] );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
