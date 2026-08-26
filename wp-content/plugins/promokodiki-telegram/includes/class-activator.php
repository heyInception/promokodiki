<?php
/**
 * Plugin lifecycle operations.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Activator {
	public static function activate(): void {
		if ( ! taxonomy_exists( 'promocode_category' ) && function_exists( 'admitad_register_content_types' ) ) {
			admitad_register_content_types();
		}

		Promokodiki_Telegram_Config::ensure_defaults();
		self::ensure_category();
		self::maybe_upgrade();

		if ( ! wp_next_scheduled( 'promokodiki_telegram_expire' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'promokodiki_telegram_expire' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'promokodiki_telegram_expire' );
	}

	public static function ensure_category(): int {
		$term_id = Promokodiki_Telegram_Config::category_term_id();
		if ( $term_id > 0 || ! taxonomy_exists( 'promocode_category' ) ) {
			return $term_id;
		}

		$created = wp_insert_term(
			'Промокоды из Telegram',
			'promocode_category',
			array( 'slug' => Promokodiki_Telegram_Config::category_slug() )
		);

		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	public static function maybe_upgrade(): void {
		if ( PROMOKODIKI_TELEGRAM_VERSION === get_option( 'promokodiki_telegram_db_version', '' ) ) {
			return;
		}

		$post_ids = get_posts(
			array(
				'post_type'                    => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'                  => 'any',
				'posts_per_page'               => -1,
				'fields'                       => 'ids',
				'no_found_rows'                => true,
				'suppress_filters'             => true,
				'update_post_meta_cache'       => false,
				'update_post_term_cache'       => false,
				'meta_key'                     => '_telegram_source_key',
			)
		);

		foreach ( $post_ids as $post_id ) {
			wp_update_post(
				array(
					'ID'        => (int) $post_id,
					'post_name' => 'yandex-market-' . (int) $post_id,
				)
			);
		}

		update_option( 'promokodiki_telegram_db_version', PROMOKODIKI_TELEGRAM_VERSION, false );
	}
}
