<?php
/**
 * Convert merchant URLs to Admitad deeplinks when possible.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Link_Service {
	/** @var callable */
	private $profile_provider;
	/** @var callable */
	private $deeplink_provider;

	public function __construct( ?callable $profile_provider = null, ?callable $deeplink_provider = null ) {
		$this->profile_provider  = $profile_provider ?: array( $this, 'active_profiles' );
		$this->deeplink_provider = $deeplink_provider ?: array( $this, 'admitad_deeplink' );
	}

	/** @return array{url:string,status:string,campaign_id:int} */
	public function resolve( string $destination_url ): array {
		$url = $this->clean_url( $destination_url );
		if ( '' === $url ) {
			return array( 'url' => '', 'status' => 'invalid', 'campaign_id' => 0 );
		}

		$destination_host = $this->normalize_host( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$campaign_id      = 0;
		$profiles         = call_user_func( $this->profile_provider );
		foreach ( is_array( $profiles ) ? $profiles : array() as $profile ) {
			if ( ! is_array( $profile ) || 'active' !== sanitize_key( (string) ( $profile['status'] ?? '' ) ) ) {
				continue;
			}
			$profile_host = $this->normalize_host( (string) wp_parse_url( (string) ( $profile['site_url'] ?? '' ), PHP_URL_HOST ) );
			if ( '' !== $profile_host && $profile_host === $destination_host ) {
				$campaign_id = absint( $profile['campaign_id'] ?? 0 );
				break;
			}
		}

		if ( $campaign_id < 1 ) {
			return array( 'url' => $url, 'status' => 'direct', 'campaign_id' => 0 );
		}

		$result = call_user_func( $this->deeplink_provider, $campaign_id, $url );
		$link   = is_array( $result ) ? esc_url_raw( (string) ( $result['link'] ?? '' ), array( 'http', 'https' ) ) : '';
		if ( is_wp_error( $result ) || '' === $link ) {
			return array( 'url' => $url, 'status' => 'direct_api_error', 'campaign_id' => $campaign_id );
		}

		return array( 'url' => $link, 'status' => 'admitad', 'campaign_id' => $campaign_id );
	}

	/** @return array<int, array<string, mixed>> */
	public function active_profiles(): array {
		global $wpdb;
		if ( ! class_exists( 'Promokodiki_Admitad_Schema' ) ) {
			return array();
		}
		$table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$rows  = $wpdb->get_results( "SELECT campaign_id, site_url, status FROM {$table} WHERE status = 'active'", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string, mixed>|WP_Error */
	public function admitad_deeplink( int $campaign_id, string $url ) {
		if ( ! class_exists( 'Promokodiki_Admitad_Api_Client' ) ) {
			return new WP_Error( 'admitad_unavailable', 'Admitad API client is unavailable.' );
		}
		return ( new Promokodiki_Admitad_Api_Client() )->deeplink( $campaign_id, $url, 'telegram' );
	}

	private function clean_url( string $url ): string {
		$url   = esc_url_raw( $url, array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			if ( 'market.yandex.ru' !== $this->normalize_host( (string) $parts['host'] ) ) {
				parse_str( $parts['query'], $query );
				foreach ( array_keys( $query ) as $key ) {
					if ( str_starts_with( strtolower( (string) $key ), 'utm_' ) || in_array( strtolower( (string) $key ), array( 'fbclid', 'gclid', 'yclid' ), true ) ) {
						unset( $query[ $key ] );
					}
				}
			}
		}
		$clean  = strtolower( (string) $parts['scheme'] ) . '://' . (string) $parts['host'];
		$clean .= isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$clean .= (string) ( $parts['path'] ?? '' );
		$clean .= $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '';
		return esc_url_raw( $clean, array( 'http', 'https' ) );
	}

	private function normalize_host( string $host ): string {
		$host = strtolower( trim( $host, ". \t\n\r\0\x0B" ) );
		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
