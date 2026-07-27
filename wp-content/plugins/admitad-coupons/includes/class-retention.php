<?php
/**
 * Retention for completed operational detail.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes expired sync/history detail while preserving queues and active rollback snapshots.
 */
final class Promokodiki_Admitad_Retention {
	/**
	 * Run one bounded daily retention pass.
	 *
	 * @return array{sync_runs:int,history_rows:int}
	 */
	public function run(): array {
		global $wpdb;

		$days           = (int) Promokodiki_Admitad_Config::get( 'log_retention_days' );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$preview_cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$sync_table     = Promokodiki_Admitad_Schema::table( 'sync_run' );
		$history_table  = Promokodiki_Admitad_Schema::table( 'classification_history' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Both identifiers come from the validated plugin schema and values remain prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Daily retention mutates only expired plugin-owned completed runs.
		$sync_deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_table} WHERE completed_at < %s AND status IN ('completed','failed')",
				$cutoff
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Daily retention mutates only expired non-preview plugin history.
		$history_deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$history_table} WHERE created_at < %s AND trigger_name <> 'preview'",
				$cutoff
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Expired snapshot candidates come from plugin-owned immutable history.
		$snapshot_ids = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT snapshot_id FROM {$history_table} WHERE created_at < %s AND trigger_name = 'preview' LIMIT 500",
				$preview_cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $snapshot_ids as $snapshot_id ) {
			$state = get_option( 'promokodiki_admitad_snapshot_' . sanitize_key( (string) $snapshot_id ), null );
			if ( is_array( $state ) && (int) ( $state['expires_at'] ?? 0 ) >= time() ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Only expired snapshot history is removed.
			$history_deleted += (int) $wpdb->delete(
				$history_table,
				array(
					'snapshot_id'  => (string) $snapshot_id,
					'trigger_name' => 'preview',
				),
				array( '%s', '%s' )
			);
			delete_option( 'promokodiki_admitad_snapshot_' . sanitize_key( (string) $snapshot_id ) );
		}
		return array(
			'sync_runs'    => max( 0, $sync_deleted ),
			'history_rows' => max( 0, $history_deleted ),
		);
	}
}
