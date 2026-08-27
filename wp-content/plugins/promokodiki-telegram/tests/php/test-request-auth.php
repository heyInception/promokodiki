<?php
/** Worker request authentication contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_Request_Auth' ) ) {
	throw new RuntimeException( 'Worker request authentication is not implemented.' );
}

$original_settings = get_option( 'promokodiki_telegram_settings', null );
$secret            = 'telegram-test-secret-1234567890';

/** Build one signed REST request. */
$signed_request = static function ( int $timestamp, string $nonce, string $body, string $signature_secret = '' ) use ( $secret ): WP_REST_Request {
	$request = new WP_REST_Request( 'POST', '/promokodiki-telegram/v1/import' );
	$request->set_body( $body );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_header( 'x-promokodiki-timestamp', (string) $timestamp );
	$request->set_header( 'x-promokodiki-nonce', $nonce );
	$payload   = "POST\n/promokodiki-telegram/v1/import\n{$timestamp}\n{$nonce}\n{$body}";
	$signature = hash_hmac( 'sha256', $payload, $signature_secret ?: $secret );
	$request->set_header( 'x-promokodiki-signature', $signature );
	return $request;
};

try {
	Promokodiki_Telegram_Config::save_settings( array( 'secret' => $secret, 'card_count' => 4 ) );

	Promokodiki_Telegram_Test_Harness::run(
		'valid signed worker request is accepted once',
		static function () use ( $signed_request ): void {
			$nonce   = 'valid-nonce-' . wp_generate_password( 8, false );
			$request = $signed_request( time(), $nonce, '{"channel":"tranzhiraru"}' );
			$result  = Promokodiki_Telegram_Request_Auth::verify( $request );
			Promokodiki_Telegram_Test_Harness::assert_same( true, $result );
			$replay = Promokodiki_Telegram_Request_Auth::verify( $request );
			Promokodiki_Telegram_Test_Harness::assert_true( is_wp_error( $replay ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'telegram_replay', $replay->get_error_code() );
			delete_transient( 'promokodiki_tg_nonce_' . hash( 'sha256', $nonce ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'stale and invalid signatures are rejected',
		static function () use ( $signed_request ): void {
			$stale = Promokodiki_Telegram_Request_Auth::verify(
				$signed_request( time() - 601, 'stale-nonce', '{}' )
			);
			Promokodiki_Telegram_Test_Harness::assert_true( is_wp_error( $stale ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'telegram_stale_request', $stale->get_error_code() );

			$invalid = Promokodiki_Telegram_Request_Auth::verify(
				$signed_request( time(), 'invalid-nonce', '{}', 'wrong-secret' )
			);
			Promokodiki_Telegram_Test_Harness::assert_true( is_wp_error( $invalid ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'telegram_invalid_signature', $invalid->get_error_code() );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	if ( null === $original_settings ) {
		delete_option( 'promokodiki_telegram_settings' );
	} else {
		update_option( 'promokodiki_telegram_settings', $original_settings, false );
	}
}
