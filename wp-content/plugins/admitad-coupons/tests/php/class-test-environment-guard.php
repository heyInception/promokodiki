<?php
/**
 * Shared destructive-integration-test environment boundary.
 *
 * @package Promokodiki_Admitad
 */

final class Promokodiki_Admitad_Test_Environment_Guard {
	/**
	 * Return whether a configured database is visibly dedicated to tests.
	 *
	 * @param string|null $sentinel Site-config sentinel value.
	 * @param string      $database WordPress DB_NAME value.
	 * @return bool
	 */
	public static function is_disposable_database( ?string $sentinel, string $database ): bool {
		return null !== $sentinel
			&& $sentinel === $database
			&& 1 === preg_match( '/(?:^|[_-])tests?(?:[_-]|$)/i', $database );
	}

	/**
	 * Emit the configured identity for runner preflight without mutation.
	 *
	 * @return string
	 */
	public static function configured_identity(): string {
		return defined( 'PROMOKODIKI_ADMITAD_TEST_DATABASE' )
			? (string) PROMOKODIKI_ADMITAD_TEST_DATABASE . '|' . (string) DB_NAME
			: 'MISSING';
	}

	/**
	 * Refuse a direct destructive eval-file test on an ordinary site database.
	 *
	 * @return void
	 */
	public static function assert_disposable_database(): void {
		$sentinel = defined( 'PROMOKODIKI_ADMITAD_TEST_DATABASE' ) ? (string) PROMOKODIKI_ADMITAD_TEST_DATABASE : null;
		if ( ! self::is_disposable_database( $sentinel, (string) DB_NAME ) ) {
			throw new RuntimeException( 'Refusing destructive Admitad integration test on a non-disposable database.' );
		}
	}
}
