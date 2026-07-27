<?php
/**
 * Main plugin hook registration.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers integration hooks.
 */
final class Promokodiki_Admitad_Plugin {
	/**
	 * Register WordPress hooks.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ), 0 );
		Promokodiki_Admitad_Editorial_Locks::register();
	}

	/**
	 * Register content types owned by the integration.
	 */
	public static function register(): void {
		admitad_register_content_types();
	}
}
