<?php
/**
 * Minimal WP-CLI integration-test harness.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Test_Harness {
	/** @var string[] */
	private static array $failures = array();

	public static function run( string $name, callable $test ): void {
		try {
			$test();
			WP_CLI::log( 'PASS ' . $name );
		} catch ( Throwable $throwable ) {
			self::$failures[] = $name . ': ' . $throwable->getMessage();
			WP_CLI::warning( 'FAIL ' . $name . ': ' . $throwable->getMessage() );
		}
	}

	public static function assert_same( mixed $expected, mixed $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException( $message ?: 'Expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) );
		}
	}

	public static function assert_true( bool $condition, string $message = 'Expected condition to be true' ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	public static function finish(): void {
		if ( self::$failures ) {
			throw new RuntimeException( implode( PHP_EOL, self::$failures ) );
		}
	}
}
