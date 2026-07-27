<?php
/**
 * Resumable coordinator and reference repository integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
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
 * @param int $id Coupon ID.
 * @return array<string, mixed>
 */
function promokodiki_admitad_sync_coupon( int $id ): array {
	return array(
		'id'                 => $id,
		'status'             => 'active',
		'name'               => 'Sync coupon ' . $id,
		'description'        => '',
		'short_name'         => 'Sync',
		'campaign'           => array(
			'id'       => 991177,
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
 * Remove records created by coordinator tests.
 *
 * @param int[] $post_ids Imported post IDs.
 */
function promokodiki_admitad_sync_cleanup( array $post_ids ): void {
	global $wpdb;

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'shops_category',
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_key'   => 'admitad_campaign_id',
			'meta_value' => '991177',
		)
	);
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term_id ) {
		wp_delete_term( $term_id, 'shops_category' );
	}
	foreach ( array( 'category_map', 'company_profile', 'company_category', 'rule', 'review_queue', 'sync_run', 'classification_history' ) as $suffix ) {
		$table = $wpdb->prefix . 'admitad_' . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
	delete_option( 'promokodiki_admitad_db_version' );
	delete_option( 'promokodiki_admitad_settings' );
	delete_option( 'promokodiki_admitad_website_id' );
	delete_option( 'admitad_access_token' );
	delete_option( 'admitad_token_expires' );
	delete_option( 'promokodiki_admitad_lock_coupon' );
}

Promokodiki_Admitad_Test_Harness::run(
	'coupon coordinator resumes three bounded pages and completes only the last',
	static function (): void {
		Promokodiki_Admitad_Plugin::register();
		Promokodiki_Admitad_Schema::install();
		update_option( 'promokodiki_admitad_settings', array( 'batch_size' => 2 ), false );
		update_option( 'promokodiki_admitad_website_id', '2811611', false );
		update_option( 'admitad_access_token', 'test-token', false );
		update_option( 'admitad_token_expires', time() + HOUR_IN_SECONDS, false );

		$pages = array(
			0 => array( promokodiki_admitad_sync_coupon( 991001 ), promokodiki_admitad_sync_coupon( 991002 ) ),
			2 => array( promokodiki_admitad_sync_coupon( 991003 ), promokodiki_admitad_sync_coupon( 991004 ) ),
			4 => array( promokodiki_admitad_sync_coupon( 991005 ) ),
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
					'meta_value'     => '991177',
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $http, 10 );
			promokodiki_admitad_sync_cleanup( $post_ids );
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
			wp_clear_scheduled_hook( 'promokodiki_admitad_coupon_batch' );
			delete_option( 'admitad_import_lock' );
			delete_option( 'promokodiki_admitad_run_owner_' . $result['run_id'] );
			promokodiki_admitad_sync_cleanup( array() );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
