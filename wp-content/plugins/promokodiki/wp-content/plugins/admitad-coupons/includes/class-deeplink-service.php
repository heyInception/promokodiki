<?php
/** Admitad shop-page deeplink generation and persistence. @package Promokodiki_Admitad */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Promokodiki_Admitad_Deeplink_Service {
	/** @var callable */
	private $generator;

	public function __construct( ?callable $generator = null ) {
		$this->generator = $generator ?: static function ( int $campaign_id, string $site_url ) {
			return ( new Promokodiki_Admitad_Api_Client() )->deeplink( $campaign_id, $site_url, 'shop_page' );
		};
	}

	public static function fingerprint( string $website_id, int $campaign_id, string $site_url ): string {
		return hash( 'sha256', $website_id . '|' . $campaign_id . '|' . untrailingslashit( strtolower( trim( $site_url ) ) ) );
	}

	/** @return array<string, string>|WP_Error */
	public function process_term( int $term_id ) {
		$term = get_term( $term_id, 'shops_category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'invalid_shop', 'Shop does not exist.' );
		}

		$campaign_id = absint( get_term_meta( $term_id, 'admitad_campaign_id', true ) );
		$website_id  = (string) Promokodiki_Admitad_Config::get( 'website_id' );
		$site_url    = $this->direct_url( $term_id );
		if ( $campaign_id < 1 || '' === $website_id || '' === $site_url ) {
			update_term_meta( $term_id, '_admitad_shop_deeplink_status', 'invalid' );
			return new WP_Error( 'invalid_deeplink_source', 'Campaign, website, and shop URL are required.' );
		}

		$fingerprint = self::fingerprint( $website_id, $campaign_id, $site_url );
		$current     = (string) get_term_meta( $term_id, '_admitad_shop_deeplink', true );
		if ( '' !== $current && hash_equals( (string) get_term_meta( $term_id, '_admitad_shop_deeplink_fingerprint', true ), $fingerprint ) ) {
			return array( 'result' => 'unchanged', 'url' => $current );
		}

		update_term_meta( $term_id, '_admitad_shop_deeplink_attempted_at', current_time( 'mysql', true ) );
		$result = call_user_func( $this->generator, $campaign_id, $site_url );
		if ( is_wp_error( $result ) ) {
			$data        = (array) $result->get_error_data();
			$unsupported = 'admitad_http_error' === $result->get_error_code() && in_array( (int) ( $data['status'] ?? 0 ), array( 400, 403, 404, 405 ), true );
			$status      = $unsupported ? 'unsupported' : 'error';
			update_term_meta( $term_id, '_admitad_shop_deeplink_status', $status );
			update_term_meta( $term_id, '_admitad_shop_deeplink_error', sanitize_text_field( $result->get_error_message() ) );
			return array( 'result' => $status, 'url' => $this->resolved_url( $term_id ) );
		}

		$link = esc_url_raw( (string) ( $result['link'] ?? '' ), array( 'http', 'https' ) );
		if ( '' === $link ) {
			update_term_meta( $term_id, '_admitad_shop_deeplink_status', 'error' );
			return array( 'result' => 'error', 'url' => $this->resolved_url( $term_id ) );
		}

		$created = '' === $current;
		update_term_meta( $term_id, '_admitad_shop_deeplink', $link );
		update_term_meta( $term_id, '_admitad_shop_deeplink_fingerprint', $fingerprint );
		update_term_meta( $term_id, '_admitad_shop_deeplink_status', 'ready' );
		update_term_meta( $term_id, '_admitad_shop_deeplink_succeeded_at', current_time( 'mysql', true ) );
		delete_term_meta( $term_id, '_admitad_shop_deeplink_error' );

		return array( 'result' => $created ? 'created' : 'updated', 'url' => $link );
	}

	public function resolved_url( int $term_id ): string {
		foreach ( array( '_admitad_shop_manual_affiliate_url', 'shop_affiliate_url', '_admitad_shop_deeplink' ) as $key ) {
			$url = esc_url_raw( (string) get_term_meta( $term_id, $key, true ), array( 'http', 'https' ) );
			if ( '' !== $url ) { return $url; }
		}
		return $this->direct_url( $term_id );
	}

	public function preview_term( int $term_id ): string {
		$campaign_id = absint( get_term_meta( $term_id, 'admitad_campaign_id', true ) );
		$site_url    = $this->direct_url( $term_id );
		$website_id  = (string) Promokodiki_Admitad_Config::get( 'website_id' );
		if ( $campaign_id < 1 || '' === $site_url || '' === $website_id ) { return 'invalid'; }
		$status = (string) get_term_meta( $term_id, '_admitad_shop_deeplink_status', true );
		if ( 'unsupported' === $status ) { return 'unsupported'; }
		$current = (string) get_term_meta( $term_id, '_admitad_shop_deeplink', true );
		if ( '' === $current ) { return 'create'; }
		return hash_equals( (string) get_term_meta( $term_id, '_admitad_shop_deeplink_fingerprint', true ), self::fingerprint( $website_id, $campaign_id, $site_url ) ) ? 'unchanged' : 'update';
	}

	private function direct_url( int $term_id ): string {
		foreach ( array( 'shop_website', '_admitad_shop_website' ) as $key ) {
			$url = esc_url_raw( (string) get_term_meta( $term_id, $key, true ), array( 'http', 'https' ) );
			if ( '' !== $url ) { return $url; }
		}
		return '';
	}
}
