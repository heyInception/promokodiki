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
	private const DB_VERSION = '1';

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
		update_option( 'promokodiki_filter_db_version', self::DB_VERSION, false );
	}
}
