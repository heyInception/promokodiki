<?php
/**
 * Plugin activation and database schema.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Activator {
	private const DB_VERSION = '2';

	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'promokodiki_click_stats';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			promocode_id BIGINT(20) UNSIGNED NOT NULL,
			click_date DATE NOT NULL,
			clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (promocode_id, click_date),
			KEY click_date (click_date),
			KEY promocode_id (promocode_id)
		) {$charset_collate};";

		dbDelta( $sql );
		dbDelta( "CREATE TABLE {$wpdb->prefix}promokodiki_promo_usage (promocode_id BIGINT(20) UNSIGNED NOT NULL, visitor_hash CHAR(64) NOT NULL, used_at DATETIME NOT NULL, PRIMARY KEY (promocode_id, visitor_hash), KEY used_at (used_at)) {$charset_collate};" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}promokodiki_promo_votes (promocode_id BIGINT(20) UNSIGNED NOT NULL, visitor_hash CHAR(64) NOT NULL, reaction VARCHAR(7) NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (promocode_id, visitor_hash)) {$charset_collate};" );
		update_option( 'promokodiki_filter_db_version', self::DB_VERSION, false );
	}
	public static function maybe_upgrade(): void { if ( self::DB_VERSION !== get_option( 'promokodiki_filter_db_version' ) ) { self::activate(); } }
}
