<?php
/**
 * Resumable coordinator and reference repository integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

/**
 * Build a WordPress HTTP response fixture.
 *
 * @param int          $status  HTTP status.
 * @param string       $body    Response body.
 * @param array<mixed> $headers Response headers.
 * @return array<string, mixed>
 */
function promokodiki_admitad_sync_http_response( int $status, string $body, array $headers = array() ): array {
	return array(
		'headers'  => $headers,
		'body'     => $body,
		'response' => array( 'code' => $status, 'message' => '' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Build a complete raw coupon fixture.
 *
 * @param int $id          Coupon ID.
 * @param int $campaign_id Test-owned campaign ID.
 * @return array<string, mixed>
 */
function promokodiki_admitad_sync_coupon( int $id, int $campaign_id ): array {
	return array(
		'id'                 => $id,
		'status'             => 'active',
		'name'               => 'Sync coupon ' . $id,
		'description'        => '',
		'short_name'         => 'Sync',
		'campaign'           => array(
			'id'       => $campaign_id,
			'name'     => 'Coordinator Test Shop',
			'site_url' => 'https://example.test/',
		),
		'categories'         => array( array( 'id' => 4, 'name' => 'Обувь' ) ),
		'types'              => array( array( 'id' => 2, 'name' => 'Скидка' ) ),
		'species'            => 'promocode',
		'promocode'          => 'SYNC' . $id,
		'goto_link'          => 'https://example.test/affiliate/' . $id,
		'date_start'         => '2026-01-01T00:00:00',
		'date_end'           => '2027-12-31T23:59:00',
		'language'           => 'ru',
		'regions'            => array( 'RU' ),
		'has_affiliate_link' => true,
	);
}

/**
 * Snapshot the tables and options touched by coordinator tests before setup.
 *
 * @return array{tables:array<string,array{exists:bool,rows:array<int,array<string,mixed>>,auto_increment:int}>,options:array<string,array{exists:bool,value:string,autoload:string}>,cron:array<int,array{timestamp:int,hook:string,schedule:string|false,args:array<int,mixed>}>}
 */
function promokodiki_admitad_sync_snapshot(): array {
	global $wpdb;

	$tables = array();
	foreach ( array( 'category_map', 'company_profile', 'company_category', 'rule', 'review_queue', 'sync_run', 'classification_history' ) as $suffix ) {
		$table  = Promokodiki_Admitad_Schema::table( $suffix );
		$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		$tables[ $table ] = array(
			'exists'         => $exists,
			'rows'           => $exists ? $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ) : array(),
			'auto_increment' => $exists ? (int) $wpdb->get_var( "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" ) : 0,
		);
	}

	$options = array();
	foreach ( array( 'promokodiki_admitad_db_version', 'promokodiki_admitad_settings', 'promokodiki_admitad_website_id', 'admitad_access_token', 'admitad_token_expires', 'promokodiki_admitad_lock_coupon', 'admitad_import_lock' ) as $option ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option ), ARRAY_A );
		$options[ $option ] = array(
			'exists'   => is_array( $row ),
			'value'    => (string) ( $row['option_value'] ?? '' ),
			'autoload' => (string) ( $row['autoload'] ?? 'no' ),
		);
	}

	return array( 'tables' => $tables, 'options' => $options, 'cron' => promokodiki_admitad_sync_snapshot_cron() );
}

/**
 * Snapshot every scheduled event for the coordinator hooks.
 *
 * @return array<int,array{timestamp:int,hook:string,schedule:string|false,args:array<int,mixed>}>
 */
function promokodiki_admitad_sync_snapshot_cron(): array {
	$events = array();
	foreach ( _get_cron_array() as $timestamp => $hooks ) {
		foreach ( array( 'promokodiki_admitad_coupon_batch', 'promokodiki_admitad_reference_batch' ) as $hook ) {
			foreach ( $hooks[ $hook ] ?? array() as $event ) {
				$events[] = array( 'timestamp' => (int) $timestamp, 'hook' => $hook, 'schedule' => $event['schedule'] ?: false, 'args' => $event['args'] );
			}
		}
	}
	return $events;
}

/**
 * Restore pre-existing coordinator state and remove only tables created by a test.
 *
 * @param array{tables:array<string,array{exists:bool,rows:array<int,array<string,mixed>>,auto_increment:int}>,options:array<string,array{exists:bool,value:string,autoload:string}>,cron:array<int,array{timestamp:int,hook:string,schedule:string|false,args:array<int,mixed>}>} $snapshot Original state.
 */
function promokodiki_admitad_sync_restore( array $snapshot ): void {
	global $wpdb;

	foreach ( $snapshot['tables'] as $table => $table_snapshot ) {
		$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if ( ! $table_snapshot['exists'] ) {
			if ( $exists ) {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $table ); // The table was created after the snapshot.
			}
			continue;
		}
		if ( ! $exists ) {
			throw new RuntimeException( 'A pre-existing Admitad table disappeared during the coordinator test.' );
		}
		$wpdb->query( "DELETE FROM {$table}" );
		foreach ( $table_snapshot['rows'] as $row ) {
			$wpdb->insert( $table, $row );
		}
		if ( $table_snapshot['auto_increment'] > 0 ) {
			$wpdb->query( 'ALTER TABLE ' . $table . ' AUTO_INCREMENT = ' . $table_snapshot['auto_increment'] );
		}
	}

	foreach ( $snapshot['options'] as $option => $option_snapshot ) {
		promokodiki_admitad_sync_restore_option( $option, $option_snapshot );
	}
	promokodiki_admitad_sync_restore_cron( $snapshot['cron'] );
}

/**
 * Restore one option exactly as it existed before a coordinator test.
 *
 * @param string                                      $option Option name.
 * @param array{exists:bool,value:string,autoload:string} $snapshot Original option row.
 */
function promokodiki_admitad_sync_restore_option( string $option, array $snapshot ): void {
	global $wpdb;

	if ( $snapshot['exists'] ) {
		$wpdb->replace( $wpdb->options, array( 'option_name' => $option, 'option_value' => $snapshot['value'], 'autoload' => $snapshot['autoload'] ) );
	} else {
		delete_option( $option );
	}
	wp_cache_delete( $option, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

/**
 * Restore the exact pre-test coordinator cron events after clearing test events.
 *
 * @param array<int,array{timestamp:int,hook:string,schedule:string|false,args:array<int,mixed>}> $events Original events.
 */
function promokodiki_admitad_sync_restore_cron( array $events ): void {
	foreach ( array( 'promokodiki_admitad_coupon_batch', 'promokodiki_admitad_reference_batch' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	foreach ( $events as $event ) {
		if ( $event['schedule'] ) {
			wp_schedule_event( $event['timestamp'], $event['schedule'], $event['hook'], $event['args'] );
		} else {
			wp_schedule_single_event( $event['timestamp'], $event['hook'], $event['args'] );
		}
	}
}

/**
 * Remove records created by coordinator tests and restore the original state.
 *
 * @param int[] $post_ids Imported post IDs.
 * @param int[]   $term_ids    Test-owned shop term IDs.
 * @param int|null $campaign_id Generated campaign ID for independent cleanup.
 */
function promokodiki_admitad_sync_cleanup( array $post_ids, array $term_ids = array(), ?int $campaign_id = null, ?array $snapshot = null ): void {
	global $wpdb;
	global $promokodiki_admitad_sync_snapshot;

	if ( null === $snapshot ) {
		$snapshot = $promokodiki_admitad_sync_snapshot;
	}
	if ( null !== $campaign_id ) {
		$post_ids = array_merge(
			$post_ids,
			get_posts(
				array(
					'post_type'      => 'promocode',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'meta_key'       => 'campaign_id',
					'meta_value'     => (string) $campaign_id,
				)
			)
		);
		$found_terms = get_terms(
			array(
				'taxonomy'   => 'shops_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => 'admitad_campaign_id',
				'meta_value' => (string) $campaign_id,
			)
		);
		$term_ids = array_merge( $term_ids, is_wp_error( $found_terms ) ? array() : $found_terms );
	}

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'shops_category' );
	}
	promokodiki_admitad_sync_restore( $snapshot );
}

$promokodiki_admitad_sync_snapshot = promokodiki_admitad_sync_snapshot();

Promokodiki_Admitad_Test_Harness::run(
	'coupon coordinator resumes three bounded pages and completes only the last',
	static function (): void {
		Promokodiki_Admitad_Plugin::register();
		Promokodiki_Admitad_Schema::install();
		update_option( 'promokodiki_admitad_settings', array( 'batch_size' => 2 ), false );
		update_option( 'promokodiki_admitad_website_id', '2811611', false );
		update_option( 'admitad_access_token', 'test-token', false );
		update_option( 'admitad_token_expires', time() + HOUR_IN_SECONDS, false );

		$campaign_id = wp_rand( 700000000, 799999999 );
		$coupon_base = $campaign_id * 10;
		$pages = array(
			0 => array( promokodiki_admitad_sync_coupon( $coupon_base + 1, $campaign_id ), promokodiki_admitad_sync_coupon( $coupon_base + 2, $campaign_id ) ),
			2 => array( promokodiki_admitad_sync_coupon( $coupon_base + 3, $campaign_id ), promokodiki_admitad_sync_coupon( $coupon_base + 4, $campaign_id ) ),
			4 => array( promokodiki_admitad_sync_coupon( $coupon_base + 5, $campaign_id ) ),
		);
		$http  = static function ( $preempt, array $args, string $url ) use ( $pages ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$offset = (int) ( $query['offset'] ?? 0 );
			return promokodiki_admitad_sync_http_response(
				200,
				wp_json_encode(
					array(
						'_meta'   => array( 'count' => 5, 'limit' => 2, 'offset' => $offset ),
						'results' => $pages[ $offset ],
					)
				)
			);
		};
		$scheduled = array();
		$scheduler = static function ( int $timestamp, string $hook, array $args ) use ( &$scheduled ): bool {
			$scheduled[] = compact( 'timestamp', 'hook', 'args' );
			return true;
		};
		$post_ids = array();
		$term_ids = array();

		add_filter( 'pre_http_request', $http, 10, 3 );
		try {
			$coordinator = new Promokodiki_Admitad_Sync_Coordinator( null, null, null, null, $scheduler );
			$run_id      = $coordinator->start_coupon_sync();
			$first       = $coordinator->run_coupon_batch( $run_id, 0 );
			$second      = $coordinator->run_coupon_batch( $run_id, 2 );
			$last        = $coordinator->run_coupon_batch( $run_id, 4 );

			Promokodiki_Admitad_Test_Harness::assert_same( 2, $first['next_offset'] );
			Promokodiki_Admitad_Test_Harness::assert_same( false, $first['complete'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 4, $second['next_offset'] );
			Promokodiki_Admitad_Test_Harness::assert_same( true, $last['complete'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 5, $last['counters']['created'] );

			$run = ( new Promokodiki_Admitad_Sync_Run_Repository() )->get( $run_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'completed', $run['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '5', (string) $run['processed_count'] );
			$post_ids = get_posts(
				array(
					'post_type'      => 'promocode',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'meta_key'       => 'campaign_id',
					'meta_value'     => (string) $campaign_id,
				)
			);
			$term_ids = get_terms(
				array(
					'taxonomy'   => 'shops_category',
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_key'   => 'admitad_campaign_id',
					'meta_value' => (string) $campaign_id,
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $http, 10 );
			promokodiki_admitad_sync_cleanup( $post_ids, is_wp_error( $term_ids ) ? array() : $term_ids, $campaign_id );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'retryable pages keep runs incomplete and schedule the same cursor',
	static function (): void {
		Promokodiki_Admitad_Schema::install();
		update_option( 'promokodiki_admitad_website_id', '2811611', false );
		update_option( 'admitad_access_token', 'test-token', false );
		update_option( 'admitad_token_expires', time() + HOUR_IN_SECONDS, false );
		$http      = static fn() => promokodiki_admitad_sync_http_response( 429, '{}', array( 'retry-after' => '17' ) );
		$scheduled = array();
		$scheduler = static function ( int $timestamp, string $hook, array $args ) use ( &$scheduled ): bool {
			$scheduled[] = compact( 'timestamp', 'hook', 'args' );
			return true;
		};
		$run_id = 0;

		add_filter( 'pre_http_request', $http );
		try {
			$coordinator = new Promokodiki_Admitad_Sync_Coordinator( null, null, null, null, $scheduler );
			$run_id      = $coordinator->start_coupon_sync();
			$error       = $coordinator->run_coupon_batch( $run_id, 0 );
			$run         = ( new Promokodiki_Admitad_Sync_Run_Repository() )->get( $run_id );

			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $error ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'running', $run['status'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $scheduled[1]['args'][1] );
			Promokodiki_Admitad_Test_Harness::assert_true( $scheduled[1]['timestamp'] >= time() + 16 );
		} finally {
			remove_filter( 'pre_http_request', $http );
			delete_option( 'promokodiki_admitad_run_owner_' . $run_id );
			delete_option( 'promokodiki_admitad_retry_' . $run_id );
			promokodiki_admitad_sync_cleanup( array() );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'reference synchronization preserves taxonomy and uses stable IDs',
	static function (): void {
		global $wpdb;

		Promokodiki_Admitad_Plugin::register();
		Promokodiki_Admitad_Schema::install();
		$before     = wp_count_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false ) );
		$repository = new Promokodiki_Admitad_Reference_Repository();

		try {
			Promokodiki_Admitad_Test_Harness::assert_same(
				2,
				$repository->sync_coupon_categories(
					array(
						array( 'id' => 4, 'name' => 'Обувь', 'parent_id' => 1 ),
						array( 'id' => 5, 'name' => 'Одежда', 'parent_id' => 1 ),
					)
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				1,
				$repository->sync_campaigns(
					array(
						Promokodiki_Admitad_Campaign_Normalizer::normalize(
							array(
								'id'         => 7775,
								'name'       => 'Lacoste RU',
								'status'     => 'active',
								'categories' => array( array( 'id' => 5, 'name' => 'Одежда' ) ),
							)
						),
					)
				)
			);

			$map_table = Promokodiki_Admitad_Schema::table( 'category_map' );
			Promokodiki_Admitad_Test_Harness::assert_same(
				'2',
				(string) $wpdb->get_var( "SELECT COUNT(*) FROM {$map_table} WHERE site_term_id = 0" )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				$before,
				wp_count_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false ) )
			);
		} finally {
			promokodiki_admitad_sync_cleanup( array() );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'plugin hooks and legacy facade start the same resumable coordinator',
	static function (): void {
		Promokodiki_Admitad_Schema::install();
		Promokodiki_Admitad_Plugin::boot();
		$result = array( 'run_id' => 0 );

		try {
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action(
					'promokodiki_admitad_coupon_batch',
					array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_coupon_batch' )
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action(
					'promokodiki_admitad_reference_batch',
					array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_reference_batch' )
				)
			);
			$result = update_admitad_coupons_data();
			Promokodiki_Admitad_Test_Harness::assert_same( 'scheduled', $result['status'] );
			Promokodiki_Admitad_Test_Harness::assert_true( $result['run_id'] > 0 );
		} finally {
			delete_option( 'admitad_import_lock' );
			delete_option( 'promokodiki_admitad_run_owner_' . $result['run_id'] );
			promokodiki_admitad_sync_cleanup( array() );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
