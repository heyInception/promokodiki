<?php
/**
 * Validated Admitad API client.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches bounded pages from the Admitad API.
 */
final class Promokodiki_Admitad_Api_Client {
	/**
	 * Token provider.
	 *
	 * @var callable
	 */
	private $token_provider;

	/**
	 * Constructor.
	 *
	 * @param callable|null $token_provider Optional testable token provider.
	 */
	public function __construct( ?callable $token_provider = null ) {
		$this->token_provider = null !== $token_provider
			? $token_provider
			: static fn( bool $force = false ) => get_admitad_token( $force );
	}

	/**
	 * Fetch one coupon page for the configured website.
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Page offset.
	 * @return array<string, mixed>|WP_Error
	 */
	public function coupon_page( int $limit, int $offset ) {
		$website_id = (string) Promokodiki_Admitad_Config::get( 'website_id' );
		if ( '' === $website_id ) {
			return new WP_Error( 'admitad_not_configured', 'Admitad website ID is not configured.' );
		}

		return $this->page(
			'coupons/website/' . rawurlencode( $website_id ) . '/',
			array(
				'limit'    => $this->limit( $limit ),
				'offset'   => max( 0, $offset ),
				'region'   => 'RU',
				'language' => 'ru',
			)
		);
	}

	/**
	 * Fetch one campaign page for the configured website.
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Page offset.
	 * @return array<string, mixed>|WP_Error
	 */
	public function campaign_page( int $limit, int $offset ) {
		$website_id = (string) Promokodiki_Admitad_Config::get( 'website_id' );
		if ( '' === $website_id ) {
			return new WP_Error( 'admitad_not_configured', 'Admitad website ID is not configured.' );
		}

		return $this->page(
			'advcampaigns/website/' . rawurlencode( $website_id ) . '/',
			array(
				'limit'    => $this->limit( $limit ),
				'offset'   => max( 0, $offset ),
				'language' => 'ru',
			)
		);
	}

	/**
	 * Generate an affiliate deeplink for a campaign landing page.
	 *
	 * @param int    $campaign_id Admitad campaign ID.
	 * @param string $ulp         Destination URL.
	 * @param string $subid       Tracking marker.
	 * @return array<string, mixed>|WP_Error
	 */
	public function deeplink( int $campaign_id, string $ulp, string $subid = 'shop_page' ) {
		$website_id = (string) Promokodiki_Admitad_Config::get( 'website_id' );
		$ulp        = esc_url_raw( $ulp, array( 'http', 'https' ) );
		if ( '' === $website_id || $campaign_id < 1 || '' === $ulp ) {
			return new WP_Error( 'admitad_invalid_deeplink', 'Website, campaign, and destination URL are required.' );
		}

		$result = $this->call(
			'deeplink/' . rawurlencode( $website_id ) . '/advcampaign/' . $campaign_id . '/',
			array(
				'ulp'   => $ulp,
				'subid' => sanitize_key( $subid ) ?: 'shop_page',
			),
			false
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$link = isset( $result[0]['link'] ) ? esc_url_raw( (string) $result[0]['link'], array( 'http', 'https' ) ) : '';
		if ( '' === $link ) {
			return new WP_Error( 'admitad_schema_error', 'Admitad deeplink response has an invalid shape.' );
		}

		return array_merge( $result[0], array( 'link' => $link ) );
	}

	/**
	 * Fetch one coupon-category reference page.
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Page offset.
	 * @return array<string, mixed>|WP_Error
	 */
	public function coupon_category_page( int $limit, int $offset ) {
		return $this->page(
			'coupons/categories/',
			array(
				'limit'    => $this->limit( $limit ),
				'offset'   => max( 0, $offset ),
				'language' => 'ru',
			)
		);
	}

	/**
	 * Fetch and validate a page.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Query arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	private function page( string $path, array $query ) {
		return $this->call( $path, $query, true );
	}

	/**
	 * Execute an authenticated request and decode its JSON response.
	 *
	 * @param string               $path    API path.
	 * @param array<string, mixed> $query   Query arguments.
	 * @param bool                 $is_page Whether to require the paginated response shape.
	 * @return array<mixed>|WP_Error
	 */
	private function call( string $path, array $query, bool $is_page ) {
		$token = call_user_func( $this->token_provider, false );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = $this->request( $path, $query, (string) $token );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			admitad_clear_cached_token();
			$token = call_user_func( $this->token_provider, true );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$response = $this->request( $path, $query, (string) $token );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		return $this->decode( $response, $is_page );
	}

	/**
	 * Send one HTTP request.
	 *
	 * @param string               $path  API path.
	 * @param array<string, mixed> $query Query arguments.
	 * @param string               $token Access token.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $path, array $query, string $token ) {
		$url = add_query_arg( $query, 'https://api.admitad.com/' . ltrim( $path, '/' ) );

		return wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout'   => 60,
				'sslverify' => true,
			)
		);
	}

	/**
	 * Validate response status and JSON page shape.
	 *
	 * @param array<string, mixed> $response HTTP response.
	 * @return array<string, mixed>|WP_Error
	 */
	private function decode( array $response, bool $is_page = true ) {
		$status = wp_remote_retrieve_response_code( $response );
		if ( 429 === $status || $status >= 500 ) {
			return new WP_Error(
				'admitad_retryable',
				'Admitad request must be retried.',
				array(
					'status'      => $status,
					'retry_after' => max( 1, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ),
				)
			);
		}

		if ( 200 !== $status ) {
			return new WP_Error(
				'admitad_http_error',
				'Admitad API request failed.',
				array( 'status' => $status )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'admitad_json_error', json_last_error_msg() );
		}
		if ( ! is_array( $data ) || ( $is_page && ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) ) ) {
			return new WP_Error( 'admitad_schema_error', 'Admitad page has an invalid response shape.' );
		}

		return $data;
	}

	/**
	 * Clamp an API page size.
	 *
	 * @param int $limit Requested size.
	 */
	private function limit( int $limit ): int {
		return max( 1, min( 500, $limit ) );
	}
}
