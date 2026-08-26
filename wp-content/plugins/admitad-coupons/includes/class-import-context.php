<?php
/**
 * Import save context.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Distinguishes automated writes from editorial saves.
 */
final class Promokodiki_Admitad_Import_Context {
	/**
	 * Nested import depth.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Whether an automated write is active.
	 */
	public static function active(): bool {
		return self::$depth > 0;
	}

	/**
	 * Run a callback inside the automated-write context.
	 *
	 * @param callable $callback Work callback.
	 * @return mixed
	 */
	public static function run( callable $callback ) {
		++self::$depth;
		try {
			return $callback();
		} finally {
			--self::$depth;
		}
	}
}
