<?php
/**
 * Versioned Admitad automation database schema.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs and names plugin-owned tables.
 */
final class Promokodiki_Admitad_Schema {
	/**
	 * Current database schema version.
	 */
	public const VERSION = 4;

	/**
	 * Allowed table suffixes.
	 *
	 * @var string[]
	 */
	private const TABLES = array(
		'category_map',
		'company_profile',
		'company_category',
		'rule',
		'review_queue',
		'sync_run',
		'classification_history',
	);

	/**
	 * Return a prefixed plugin table name.
	 *
	 * @param string $name Table suffix.
	 * @throws InvalidArgumentException When the suffix is not plugin-owned.
	 */
	public static function table( string $name ): string {
		global $wpdb;

		if ( ! in_array( $name, self::TABLES, true ) ) {
			throw new InvalidArgumentException( 'Unknown Admitad schema table.' );
		}

		return $wpdb->prefix . 'admitad_' . $name;
	}

	/**
	 * Install or update all plugin-owned tables.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$collate = $wpdb->get_charset_collate();
		$sql     = array(
			'CREATE TABLE ' . self::table( 'category_map' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_namespace varchar(32) NOT NULL,
				external_category_id bigint(20) unsigned NOT NULL,
				external_name varchar(255) NOT NULL DEFAULT '',
				external_parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
				site_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				weight smallint(5) unsigned NOT NULL DEFAULT 100,
				status varchar(20) NOT NULL DEFAULT 'unmapped',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY external_site (source_namespace,external_category_id,site_term_id),
				KEY external_lookup (source_namespace,external_category_id,status),
				KEY site_status (site_term_id,status)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'company_profile' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				display_name varchar(255) NOT NULL DEFAULT '',
				default_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				signal_weight smallint(5) unsigned NOT NULL DEFAULT 40,
				status varchar(20) NOT NULL DEFAULT 'active',
				category_snapshot longtext NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign (campaign_id),
				KEY default_status (default_term_id,status)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'company_category' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				campaign_id bigint(20) unsigned NOT NULL,
				site_term_id bigint(20) unsigned NOT NULL,
				is_default tinyint(1) unsigned NOT NULL DEFAULT 0,
				weight smallint(5) unsigned NOT NULL DEFAULT 40,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY campaign_term (campaign_id,site_term_id),
				KEY term_status (site_term_id,status)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'rule' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				normalized_phrase varchar(255) NOT NULL,
				match_mode varchar(20) NOT NULL DEFAULT 'phrase',
				site_term_id bigint(20) unsigned NOT NULL,
				weight smallint(5) unsigned NOT NULL DEFAULT 20,
				status varchar(20) NOT NULL DEFAULT 'candidate',
				source varchar(32) NOT NULL DEFAULT 'editorial',
				evidence_count int(10) unsigned NOT NULL DEFAULT 0,
				distinct_campaign_count int(10) unsigned NOT NULL DEFAULT 0,
				contradiction_count int(10) unsigned NOT NULL DEFAULT 0,
				rule_version int(10) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY phrase_mode_term (normalized_phrase,match_mode,site_term_id),
				KEY status_term (status,site_term_id),
				KEY phrase_status (normalized_phrase,status)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'review_queue' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				dedupe_key char(64) NOT NULL,
				entity_type varchar(32) NOT NULL,
				entity_id varchar(191) NOT NULL,
				reason_code varchar(64) NOT NULL,
				severity varchar(20) NOT NULL DEFAULT 'normal',
				proposed_categories longtext NOT NULL,
				explanation longtext NOT NULL,
				evidence longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'open',
				assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
				resolution longtext NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				resolved_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY unresolved_case (dedupe_key),
				KEY status_reason (status,reason_code),
				KEY entity_lookup (entity_type,entity_id)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'sync_run' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_type varchar(32) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'running',
				cursor_offset bigint(20) unsigned NOT NULL DEFAULT 0,
				started_at datetime NOT NULL,
				heartbeat_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				processed_count bigint(20) unsigned NOT NULL DEFAULT 0,
				created_count bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_count bigint(20) unsigned NOT NULL DEFAULT 0,
				unchanged_count bigint(20) unsigned NOT NULL DEFAULT 0,
				failed_count bigint(20) unsigned NOT NULL DEFAULT 0,
				deactivated_count bigint(20) unsigned NOT NULL DEFAULT 0,
				reactivated_count bigint(20) unsigned NOT NULL DEFAULT 0,
				error_summary longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY status_heartbeat (status,heartbeat_at),
				KEY job_started (job_type,started_at)
			) {$collate};",
			'CREATE TABLE ' . self::table( 'classification_history' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				snapshot_id char(36) NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				algorithm_version varchar(32) NOT NULL,
				rule_version int(10) unsigned NOT NULL DEFAULT 1,
				previous_terms longtext NOT NULL,
				result_terms longtext NOT NULL,
				previous_primary_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				result_primary_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				confidence varchar(20) NOT NULL,
				explanation longtext NOT NULL,
				trigger_name varchar(32) NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY post_created (post_id,created_at),
				KEY snapshot (snapshot_id)
			) {$collate};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'promokodiki_admitad_db_version', (string) self::VERSION, false );
	}
}
