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
$fixture_slug  = substr( str_replace( '-', '', $fixture_token ), 0, 12 );
$campaign_id   = random_int( 2000000000, 2100000000 );
$tie_campaign_id = random_int( 2140000001, 2147000000 );
$inactive_campaign_id = random_int( 2147000001, 2147480000 );
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
	$second_term = wp_insert_term( 'Admin list tie fixture ' . $fixture_token, 'promocode_category' );
	if ( is_wp_error( $second_term ) ) {
		throw new RuntimeException( 'Unable to create a second isolated category fixture.' );
	}
	$second_term_id = (int) $second_term['term_id'];
	$term_ids[]     = $second_term_id;
	$third_term = wp_insert_term( 'Admin list source fixture ' . $fixture_token, 'promocode_category' );
	if ( is_wp_error( $third_term ) ) {
		throw new RuntimeException( 'Unable to create a third isolated category fixture.' );
	}
	$third_term_id = (int) $third_term['term_id'];
	$term_ids[]    = $third_term_id;

	$map_table     = Promokodiki_Admitad_Schema::table( 'category_map' );
	$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
	$category_table = Promokodiki_Admitad_Schema::table( 'company_category' );
	$queue_table   = Promokodiki_Admitad_Schema::table( 'review_queue' );
	$history_table = Promokodiki_Admitad_Schema::table( 'classification_history' );
	$snapshots['map']       = promokodiki_admitad_list_test_snapshot( $map_table, array( 'source_namespace' => 'coupon', 'external_category_id' => $external_id ) );
	$snapshots['campaign_map'] = promokodiki_admitad_list_test_snapshot( $map_table, array( 'source_namespace' => 'campaign', 'external_category_id' => $external_id ) );
	$snapshots['profile']   = promokodiki_admitad_list_test_snapshot( $profile_table, array( 'campaign_id' => $campaign_id ) );
	$snapshots['tie_profile'] = promokodiki_admitad_list_test_snapshot( $profile_table, array( 'campaign_id' => $tie_campaign_id ) );
	$snapshots['inactive_profile'] = promokodiki_admitad_list_test_snapshot( $profile_table, array( 'campaign_id' => $inactive_campaign_id ) );
	$snapshots['category']  = promokodiki_admitad_list_test_snapshot( $category_table, array( 'campaign_id' => $campaign_id ) );
	$snapshots['tie_category'] = promokodiki_admitad_list_test_snapshot( $category_table, array( 'campaign_id' => $tie_campaign_id ) );
	$snapshots['inactive_category'] = promokodiki_admitad_list_test_snapshot( $category_table, array( 'campaign_id' => $inactive_campaign_id ) );

	Promokodiki_Admitad_Test_Harness::run(
		'filtered lists clamp page sizes, order stably, and include archived rules',
		static function () use ( $external_id, $campaign_id, $tie_campaign_id, $inactive_campaign_id, $term_id, $second_term_id, $third_term_id, $fixture_token, &$rule_ids ): void {
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
			$maps->save( 'campaign', $external_id, $term_id, 100 );
			$wpdb->update( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'status' => 'inactive' ), array( 'source_namespace' => 'campaign', 'external_category_id' => $external_id, 'site_term_id' => $term_id ), array( '%s' ), array( '%s', '%d', '%d' ) );
			$map_page = $maps->list_rows( (string) $external_id, 1, 21, array( 'status' => 'active' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 20, $map_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $map_page['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $external_id, (int) $map_page['items'][0]['external_category_id'] );
			$inactive_maps = $maps->list_rows( (string) $external_id, 1, 50, array( 'status' => 'inactive' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $inactive_maps['total'] );
			$campaign_maps = $maps->list_rows( (string) $external_id, 1, 50, array( 'source_namespace' => 'campaign' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $campaign_maps['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'campaign', $campaign_maps['items'][0]['source_namespace'] );
			$map_order = $maps->list_rows( (string) $external_id, 1, 50 );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $map_order['total'] );
			Promokodiki_Admitad_Test_Harness::assert_true( (int) $map_order['items'][0]['id'] < (int) $map_order['items'][1]['id'] );

			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			foreach ( array( array( $tie_campaign_id, 'active' ), array( $inactive_campaign_id, 'inactive' ) ) as $company_fixture ) {
				$wpdb->insert( $profile_table, array( 'campaign_id' => $company_fixture[0], 'display_name' => 'Campaign ' . $fixture_token, 'default_term_id' => 0, 'signal_weight' => 17, 'status' => $company_fixture[1], 'category_snapshot' => '[]', 'created_at' => $now, 'updated_at' => $now ) );
			}
			$company_page = $profiles->list_rows( $fixture_token, 1, 50, array( 'status' => 'active' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $company_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $company_page['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $campaign_id, $tie_campaign_id ), array_map( 'intval', array_column( $company_page['items'], 'campaign_id' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $profiles->list_rows( $fixture_token, 1, 50, array( 'status' => 'inactive' ) )['total'] );

			$rules   = new Promokodiki_Admitad_Rule_Repository();
			$rule_id = $rules->save( 'fixture ' . $fixture_token, $term_id, 20, 'candidate', 'phrase', 'admin_list_fixture' );
			$rule_ids[] = $rule_id;
			Promokodiki_Admitad_Test_Harness::assert_true( $rules->set_status( $rule_id, 'archived' ) );
			$token_rule_id = $rules->save( 'fixture ' . $fixture_token, $second_term_id, 20, 'candidate', 'token', 'admin_list_fixture_token' );
			$rule_ids[] = $token_rule_id;
			$status_competitor_id = $rules->save( 'fixture ' . $fixture_token, $second_term_id, 20, 'candidate', 'phrase', 'admin_list_fixture' );
			$mode_competitor_id = $rules->save( 'fixture ' . $fixture_token, $third_term_id, 20, 'archived', 'token', 'admin_list_fixture' );
			$source_competitor_id = $rules->save( 'fixture ' . $fixture_token, $third_term_id, 20, 'archived', 'phrase', 'other_admin_list_fixture' );
			$rule_ids = array_merge( $rule_ids, array( $status_competitor_id, $mode_competitor_id, $source_competitor_id ) );
			$rule_page = $rules->list_rows( $fixture_token, 1, 50, array( 'status' => 'archived', 'match_mode' => 'phrase', 'source' => 'admin_list_fixture' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 50, $rule_page['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $rule_page['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $rule_id, (int) $rule_page['items'][0]['id'] );
			$token_rules = $rules->list_rows( $fixture_token, 1, 50, array( 'match_mode' => 'token', 'source' => 'admin_list_fixture_token' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $token_rules['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $token_rule_id, (int) $token_rules['items'][0]['id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 3, $rules->list_rows( $fixture_token, 1, 50, array( 'status' => 'archived' ) )['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $rules->list_rows( $fixture_token, 1, 50, array( 'status' => 'archived', 'match_mode' => 'phrase' ) )['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $rules->list_rows( $fixture_token, 1, 50, array( 'status' => 'archived', 'match_mode' => 'phrase', 'source' => 'admin_list_fixture' ) )['total'] );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'campaign autocomplete returns only stable campaign pairs and profile saves preserve reference fields',
		static function () use ( $campaign_id, $tie_campaign_id, $inactive_campaign_id, $term_id, $fixture_token ): void {
			global $wpdb;
			$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
			$results  = $profiles->search_campaigns( $fixture_token, 99 );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(
					array( 'campaign_id' => $campaign_id, 'display_name' => 'Campaign ' . $fixture_token ),
					array( 'campaign_id' => $tie_campaign_id, 'display_name' => 'Campaign ' . $fixture_token ),
					array( 'campaign_id' => $inactive_campaign_id, 'display_name' => 'Campaign ' . $fixture_token ),
				),
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
		static function () use ( $fixture_token, $fixture_slug, &$queue_ids, &$history_ids ): void {
			global $wpdb;
			$queue = new Promokodiki_Admitad_Review_Queue_Repository();
			$high_id     = $queue->enqueue( 'coupon', 'review-high-' . $fixture_token, 'conflicting_signals', array() );
			$normal_id   = $queue->enqueue( 'coupon', 'review-low-' . $fixture_token, 'low_confidence', array() );
			$other_id    = $queue->enqueue( 'coupon', 'review-other-' . $fixture_token, 'missing_mapping', array() );
			$normal_tie_id = $queue->enqueue( 'coupon', 'review-normal-tie-' . $fixture_token, 'unknown_reason', array() );
			$resolved_id = $queue->enqueue( 'coupon', 'review-resolved-' . $fixture_token, 'missing_company', array() );
			$queue_ids   = array( $high_id, $normal_id, $other_id, $normal_tie_id, $resolved_id );
			$table        = Promokodiki_Admitad_Schema::table( 'review_queue' );
			$wpdb->update( $table, array( 'severity' => 'other' ), array( 'id' => $other_id ), array( '%s' ), array( '%d' ) );
			$wpdb->update( $table, array( 'status' => 'resolved' ), array( 'id' => $resolved_id ), array( '%s' ), array( '%d' ) );
			$open = $queue->list_rows( $fixture_token, 1, 100, array( 'status' => 'open' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 4, $open['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $high_id, $normal_id, $normal_tie_id, $other_id ), array_map( 'intval', array_column( $open['items'], 'id' ) ) );
			$filtered = $queue->list_rows( $fixture_token, 1, 100, array( 'status' => 'open', 'reason' => 'low_confidence' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $filtered['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $filtered['items'] ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'low_confidence', $filtered['items'][0]['reason_code'] );
			$resolved = $queue->list_rows( $fixture_token, 1, 100, array( 'status' => 'resolved', 'reason' => 'missing_company' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $resolved['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $resolved_id, (int) $resolved['items'][0]['id'] );

			$table       = Promokodiki_Admitad_Schema::table( 'classification_history' );
			$snapshot_id = wp_generate_uuid4();
			$other_snapshot_id = wp_generate_uuid4();
			$numeric_post_id = random_int( 900000000, 990000000 );
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Creates an isolated immutable-history fixture with its own UUID.
			$wpdb->insert(
				$table,
				array(
					'snapshot_id'              => $snapshot_id,
					'post_id'                  => $numeric_post_id,
					'algorithm_version'        => 'list-' . $fixture_slug,
					'rule_version'             => 1,
					'previous_terms'           => '[]',
					'result_terms'             => '[]',
					'previous_primary_term_id' => 0,
					'result_primary_term_id'   => 0,
					'confidence'               => 'low',
					'explanation'              => '[]',
					'trigger_name'             => 'admin_list_fixture',
					'actor_id'                 => 0,
					'created_at'               => $timestamp,
				)
			);
			$history_ids[] = (int) $wpdb->insert_id;
			$first_history_id = $history_ids[0];
			foreach (
				array(
					array( $snapshot_id, $numeric_post_id + 1, 'low', 'admin_list_fixture' ),
					array( $other_snapshot_id, $numeric_post_id + 2, 'high', 'other_fixture' ),
					array( $other_snapshot_id, $numeric_post_id + 3, 'low', 'admin_list_fixture' ),
				) as $fixture
			) {
				$wpdb->insert(
					$table,
					array(
						'snapshot_id' => $fixture[0], 'post_id' => $fixture[1], 'algorithm_version' => 'list-' . $fixture_slug,
						'rule_version' => 1, 'previous_terms' => '[]', 'result_terms' => '[]',
						'previous_primary_term_id' => 0, 'result_primary_term_id' => 0, 'confidence' => $fixture[2],
						'explanation' => '[]', 'trigger_name' => $fixture[3], 'actor_id' => 0, 'created_at' => $timestamp,
					)
				);
				$history_ids[] = (int) $wpdb->insert_id;
			}
			$history_repository = new Promokodiki_Admitad_Classification_History_Repository();
			$history = $history_repository->list_rows( $fixture_slug, 1, 21, array( 'snapshot_id' => $snapshot_id, 'confidence' => 'low', 'trigger_name' => 'admin_list_fixture' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 20, $history['per_page'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $history['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $history_ids[1], $first_history_id ), array_map( 'intval', array_column( $history['items'], 'id' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 3, $history_repository->list_rows( $fixture_slug, 1, 50, array( 'confidence' => 'low' ) )['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 3, $history_repository->list_rows( $fixture_slug, 1, 50, array( 'trigger_name' => 'admin_list_fixture' ) )['total'] );
			$numeric_history = $history_repository->list_rows( (string) $numeric_post_id, 1, 20, array() );
			Promokodiki_Admitad_Test_Harness::assert_same( 1, $numeric_history['total'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $first_history_id, (int) $numeric_history['items'][0]['id'] );
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
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'category_map' ), array( 'source_namespace' => 'campaign', 'external_category_id' => $external_id ), $snapshots['campaign_map'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $campaign_id ), $snapshots['category'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), $snapshots['profile'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $tie_campaign_id ), $snapshots['tie_category'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $tie_campaign_id ), $snapshots['tie_profile'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => $inactive_campaign_id ), $snapshots['inactive_category'] );
		promokodiki_admitad_list_test_restore( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $inactive_campaign_id ), $snapshots['inactive_profile'] );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
