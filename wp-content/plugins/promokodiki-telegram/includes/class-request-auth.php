<?php
/**
 * HMAC authentication for the external worker.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Request_Auth {
	private const MAX_CLOCK_SKEW = 300;

	/** @return true|WP_Error */
	public static function verify( WP_REST_Request $request ) {
		$timestamp = (int) $request->get_header( 'x-promokodiki-timestamp' );
		$nonce     = sanitize_text_field( (string) $request->get_header( 'x-promokodiki-nonce' ) );
		$signature = strtolower( sanitize_text_field( (string) $request->get_header( 'x-promokodiki-signature' ) ) );

		if ( $timestamp < 1 || abs( time() - $timestamp ) > self::MAX_CLOCK_SKEW ) {
			return new WP_Error( 'telegram_stale_request', 'Worker timestamp is outside the accepted window.', array( 'status' => 401 ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9._-]{8,128}$/', $nonce ) ) {
			return new WP_Error( 'telegram_invalid_nonce', 'Worker nonce is invalid.', array( 'status' => 401 ) );
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return new WP_Error( 'telegram_invalid_signature', 'Worker signature is invalid.', array( 'status' => 401 ) );
		}

		$secret = Promokodiki_Telegram_Config::secret();
		if ( '' === $secret ) {
			return new WP_Error( 'telegram_missing_secret', 'Worker secret is not configured.', array( 'status' => 503 ) );
		}

		$payload  = strtoupper( $request->get_method() ) . "\n";
		$payload .= $request->get_route() . "\n";
		$payload .= $timestamp . "\n";
		$payload .= $nonce . "\n";
		$payload .= (string) $request->get_body();
		$expected = hash_hmac( 'sha256', $payload, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'telegram_invalid_signature', 'Worker signature is invalid.', array( 'status' => 401 ) );
		}

		$transient = 'promokodiki_tg_nonce_' . hash( 'sha256', $nonce );
		if ( false !== get_transient( $transient ) ) {
			return new WP_Error( 'telegram_replay', 'Worker nonce has already been used.', array( 'status' => 409 ) );
		}
		set_transient( $transient, 1, 10 * MINUTE_IN_SECONDS );

		return true;
	}
}
