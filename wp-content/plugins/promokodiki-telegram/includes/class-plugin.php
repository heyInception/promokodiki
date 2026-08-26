<?php
/**
 * Plugin hook registration.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( 'Promokodiki_Telegram_Activator', 'ensure_category' ), 30 );
		add_action( 'init', array( 'Promokodiki_Telegram_Activator', 'maybe_upgrade' ), 31 );
		add_action( 'rest_api_init', array( 'Promokodiki_Telegram_REST_Controller', 'register_routes' ) );
		add_action( 'promokodiki_telegram_expire', array( 'Promokodiki_Telegram_Query', 'expire_posts' ) );
		add_action( 'pre_get_posts', array( 'Promokodiki_Telegram_Query', 'exclude_from_general_query' ), 20 );
		Promokodiki_Telegram_Admin::boot();
		Promokodiki_Telegram_Metabox::boot();
	}
}
