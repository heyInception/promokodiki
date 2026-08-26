<?php
/**
 * Lightweight WP-CLI test harness for the Admitad plugin.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs small integration tests inside a loaded WordPress instance.
 */
final class Promokodiki_Admitad_Test_Harness {
	/**
	 * Number of failed tests.
	 *
	 * @var int
	 */
	private static int $failures = 0;

	/**
	 * Run one named test.
	 *
	 * @param string   $name     Test name.
	 * @param callable $callback Test callback.
	 */
	public static function run( string $name, callable $callback ): void {
		try {
			$callback();
			WP_CLI::log( 'PASS: ' . $name );
		} catch ( Throwable $error ) {
			++self::$failures;
			WP_CLI::warning( 'FAIL: ' . $name . ' — ' . $error->getMessage() );
		}
	}

	/**
	 * Assert strict equality.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Optional failure message.
	 */
	public static function assert_same( $expected, $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException(
				$message ?: sprintf(
					'Expected %s, got %s.',
					wp_json_encode( $expected ),
					wp_json_encode( $actual )
				)
			);
		}
	}

	/**
	 * Assert a truthy value.
	 *
	 * @param mixed  $actual  Value under test.
	 * @param string $message Optional failure message.
	 */
	public static function assert_true( $actual, string $message = '' ): void {
		if ( ! $actual ) {
			throw new RuntimeException( $message ?: 'Expected a truthy value.' );
		}
	}

	/**
	 * Finish the test process.
	 */
	public static function finish(): void {
		if ( self::$failures > 0 ) {
			WP_CLI::error( sprintf( '%d test(s) failed.', self::$failures ) );
		}

		WP_CLI::success( 'All tests passed.' );
	}
}
