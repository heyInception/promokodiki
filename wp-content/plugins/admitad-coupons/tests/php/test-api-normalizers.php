<?php
/**
 * API client and normalizer integration tests.
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
function promokodiki_admitad_test_http_response( int $status, string $body, array $headers = array() ): array {
	return array(
		'headers'  => $headers,
		'body'     => $body,
		'response' => array(
			'code'    => $status,
			'message' => '',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Return the two supplied coupon shapes.
 *
 * @return array<string, array<string, mixed>>
 */
function promokodiki_admitad_coupon_fixtures(): array {
	return array(
		'empty_description' => array(
			'status'             => 'active',
			'campaign'           => array(
				'id'       => 7775,
				'name'     => 'Lacoste RU',
				'site_url' => 'https://lacoste.ru/',
			),
			'description'        => '',
			'short_name'         => 'Скидка -10%!',
			'date_end'           => '2026-12-31T23:59:00',
			'date_start'         => '2024-03-01T12:07:00',
			'id'                 => 330714,
			'regions'            => array( 'RU' ),
			'discount'           => '10%',
			'types'              => array( array( 'id' => 2, 'name' => 'Скидка на заказ' ) ),
			'species'            => 'promocode',
			'categories'         => array(
				array( 'id' => 5, 'name' => 'Одежда' ),
				array( 'id' => 4, 'name' => 'Обувь ' ),
			),
			'name'               => 'Скидка -10%!',
			'language'           => 'ru',
			'has_affiliate_link' => true,
			'promocode'          => 'LCST10ADM',
			'goto_link'          => 'https://example.test/lacoste',
		),
		'with_description'  => array(
			'status'             => 'active',
			'campaign'           => array(
				'id'       => 25224,
				'name'     => 'Яндекс.Путешествия',
				'site_url' => 'https://travel.yandex.ru/',
			),
			'description'        => 'Скидка 1000 р на заказ от 10 000 р.',
			'short_name'         => 'Скидка 1000 р',
			'date_end'           => '2026-07-31T23:59:00',
			'date_start'         => '2026-06-26T16:49:00',
			'id'                 => 901876,
			'regions'            => array( 'RU' ),
			'types'              => array( array( 'id' => 2, 'name' => 'Скидка на заказ' ) ),
			'species'            => 'promocode',
			'categories'         => array( array( 'id' => 12, 'name' => 'Туры и путешествия' ) ),
			'name'               => 'Скидка 1000 р на заказ от 10 000 р.',
			'language'           => 'ru',
			'has_affiliate_link' => true,
			'promocode'          => 'ADM-ALL-1000',
			'goto_link'          => 'https://example.test/travel',
		),
	);
}

Promokodiki_Admitad_Test_Harness::run(
	'coupon page uses website, RU, language, limit, and offset',
	static function (): void {
		$fixture  = array(
			'_meta'   => array( 'count' => 1, 'limit' => 1, 'offset' => 20 ),
			'results' => array( promokodiki_admitad_coupon_fixtures()['empty_description'] ),
		);
		$observed = '';
		$filter   = static function ( $preempt, array $args, string $url ) use ( $fixture, &$observed ) {
			$observed = $url;
			return promokodiki_admitad_test_http_response( 200, wp_json_encode( $fixture ) );
		};

		add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			$client = new Promokodiki_Admitad_Api_Client(
				static fn( bool $force = false ): string => $force ? 'refreshed-token' : 'cached-token'
			);
			update_option( 'promokodiki_admitad_website_id', '2811611', false );
			$page = $client->coupon_page( 1, 20 );

			Promokodiki_Admitad_Test_Harness::assert_same( 1, count( $page['results'] ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, '/coupons/website/2811611/' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, 'region=RU' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, 'language=ru' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, 'limit=1' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, 'offset=20' ) );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'client refreshes once after a 401 response',
	static function (): void {
		$requests = 0;
		$forces   = array();
		$filter   = static function () use ( &$requests ) {
			++$requests;
			return 1 === $requests
				? promokodiki_admitad_test_http_response( 401, '{}' )
				: promokodiki_admitad_test_http_response( 200, '{"_meta":{"count":0},"results":[]}' );
		};

		add_filter( 'pre_http_request', $filter );
		try {
			update_option( 'promokodiki_admitad_website_id', '2811611', false );
			$client = new Promokodiki_Admitad_Api_Client(
				static function ( bool $force = false ) use ( &$forces ): string {
					$forces[] = $force;
					return $force ? 'new-token' : 'old-token';
				}
			);
			$page = $client->coupon_page( 10, 0 );

			Promokodiki_Admitad_Test_Harness::assert_same( 2, $requests );
			Promokodiki_Admitad_Test_Harness::assert_same( array( false, true ), $forces );
			Promokodiki_Admitad_Test_Harness::assert_same( array(), $page['results'] );
		} finally {
			remove_filter( 'pre_http_request', $filter );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'retryable HTTP responses return scheduling data without sleeping',
	static function (): void {
		$filter = static fn() => promokodiki_admitad_test_http_response( 429, '{}', array( 'retry-after' => '17' ) );

		add_filter( 'pre_http_request', $filter );
		try {
			$client = new Promokodiki_Admitad_Api_Client( static fn(): string => 'token' );
			$error  = $client->coupon_category_page( 50, 0 );

			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $error ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'admitad_retryable', $error->get_error_code() );
			Promokodiki_Admitad_Test_Harness::assert_same( 17, $error->get_error_data()['retry_after'] );
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'invalid JSON is rejected',
	static function (): void {
		$filter = static fn() => promokodiki_admitad_test_http_response( 200, '{invalid' );

		add_filter( 'pre_http_request', $filter );
		try {
			$client = new Promokodiki_Admitad_Api_Client( static fn(): string => 'token' );
			$error  = $client->coupon_category_page( 50, 0 );

			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $error ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'admitad_json_error', $error->get_error_code() );
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'coupon normalization is deterministic with or without description',
	static function (): void {
		$fixtures   = promokodiki_admitad_coupon_fixtures();
		$empty      = Promokodiki_Admitad_Coupon_Normalizer::normalize( $fixtures['empty_description'] );
		$reordered  = $fixtures['empty_description'];
		$reordered['categories'] = array_reverse( $reordered['categories'] );
		$again      = Promokodiki_Admitad_Coupon_Normalizer::normalize( $reordered );
		$described  = Promokodiki_Admitad_Coupon_Normalizer::normalize( $fixtures['with_description'] );

		Promokodiki_Admitad_Test_Harness::assert_same( '330714', $empty['external_id'] );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $empty['description'] );
		Promokodiki_Admitad_Test_Harness::assert_same( array( 4, 5 ), array_column( $empty['categories'], 'id' ) );
		Promokodiki_Admitad_Test_Harness::assert_same( $empty['payload_hash'], $again['payload_hash'] );
		Promokodiki_Admitad_Test_Harness::assert_same( '901876', $described['external_id'] );
		Promokodiki_Admitad_Test_Harness::assert_true( '' !== $described['description'] );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'campaign normalization preserves hierarchical category snapshots',
	static function (): void {
		$campaign = Promokodiki_Admitad_Campaign_Normalizer::normalize(
			array(
				'id'         => 7775,
				'name'       => 'Lacoste RU',
				'status'     => 'active',
				'site_url'   => 'https://lacoste.ru/',
				'categories' => array(
					array( 'id' => 5, 'name' => 'Одежда', 'parent' => array( 'id' => 1, 'name' => 'Мода' ) ),
				),
			)
		);

		Promokodiki_Admitad_Test_Harness::assert_same( '7775', $campaign['external_id'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 1, $campaign['categories'][0]['parent_id'] );
		Promokodiki_Admitad_Test_Harness::assert_true( 64 === strlen( $campaign['payload_hash'] ) );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'deeplink request uses the configured website and shop page subid',
	static function (): void {
		$observed = '';
		$filter   = static function ( $preempt, array $args, string $url ) use ( &$observed ) {
			$observed = $url;
			return promokodiki_admitad_test_http_response( 200, '[{"link":"https://ad.admitad.com/g/example/"}]' );
		};

		add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			update_option( 'promokodiki_admitad_website_id', '2811611', false );
			$client = new Promokodiki_Admitad_Api_Client( static fn(): string => 'token' );
			$result = $client->deeplink( 7775, 'https://lacoste.ru/catalog/?sale=1', 'shop_page' );

			Promokodiki_Admitad_Test_Harness::assert_same( 'https://ad.admitad.com/g/example/', $result['link'] );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, '/deeplink/2811611/advcampaign/7775/' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( rawurldecode( $observed ), 'ulp=https://lacoste.ru/catalog/?sale=1' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $observed, 'subid=shop_page' ) );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'deeplink response must contain a valid URL',
	static function (): void {
		$filter = static fn() => promokodiki_admitad_test_http_response( 200, '[{"link":"javascript:alert(1)"}]' );

		add_filter( 'pre_http_request', $filter );
		try {
			update_option( 'promokodiki_admitad_website_id', '2811611', false );
			$client = new Promokodiki_Admitad_Api_Client( static fn(): string => 'token' );
			$error  = $client->deeplink( 7775, 'https://lacoste.ru/' );

			Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $error ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'admitad_schema_error', $error->get_error_code() );
		} finally {
			remove_filter( 'pre_http_request', $filter );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
