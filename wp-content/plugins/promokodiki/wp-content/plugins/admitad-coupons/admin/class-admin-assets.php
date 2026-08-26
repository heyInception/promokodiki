<?php
/**
 * Admitad administrative assets.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the shared Admitad administration interaction shell.
 */
final class Promokodiki_Admitad_Admin_Assets {
	/**
	 * Asset handle.
	 */
	private const HANDLE = 'promokodiki-admitad-admin';

	/**
	 * Register administrative asset hooks.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Enqueue assets only for Admitad sections of the promocode admin area.
	 */
	public static function enqueue(): void {
		$post_type = self::query_value( 'post_type' );
		$page      = self::query_value( 'page' );

		if ( 'promocode' !== $post_type || ! str_starts_with( $page, 'admitad-' ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			ADMITAD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ADMITAD_PLUGIN_VERSION
		);
		wp_enqueue_script(
			self::HANDLE,
			ADMITAD_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			ADMITAD_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			self::HANDLE,
			'PromokodikiAdmitadAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				'i18n'    => array(
					'loading' => 'Загрузка…',
					'retry'   => 'Повторить',
				),
			)
		);
	}

	/**
	 * Read a safe scalar value from the current query string.
	 *
	 * @param string $key Query variable name.
	 * @return string Sanitized query value.
	 */
	private static function query_value( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( (string) $_GET[ $key ] ) );
	}
}
