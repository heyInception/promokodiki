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
		add_action(
			'promokodiki_admitad_coupon_batch',
			array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_coupon_batch' ),
			10,
			2
		);
		add_action(
			'promokodiki_admitad_reference_batch',
			array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_reference_batch' ),
			10,
			3
		);
	}

	/**
	 * Register content types owned by the integration.
	 */
	public static function register(): void {
		admitad_register_content_types();
	}
}
