<?php
/**
 * Admitad reference snapshot persistence.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes stable external IDs without mutating site taxonomies.
 */
final class Promokodiki_Admitad_Reference_Repository {
	/**
	 * Synchronize coupon category references.
	 *
	 * @param array<int, array<string, mixed>> $items API categories.
	 */
	public function sync_coupon_categories( array $items ): int {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'category_map' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$count = 0;
		foreach ( $items as $item ) {
			$external_id = absint( $item['id'] ?? 0 );
			if ( ! $external_id ) {
				continue;
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot use a value placeholder.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state lives in the custom table.
			$mapped = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE source_namespace = %s AND external_category_id = %d AND site_term_id > 0",
					'coupon',
					$external_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fields = array(
				'external_name'      => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'external_parent_id' => absint( $item['parent_id'] ?? 0 ),
				'updated_at'         => $now,
			);
			if ( $mapped ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state lives in the custom table.
				$wpdb->update(
					$table,
					$fields,
					array(
						'source_namespace'     => 'coupon',
						'external_category_id' => $external_id,
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state lives in the custom table.
				$wpdb->replace(
					$table,
					array_merge(
						$fields,
						array(
							'source_namespace'     => 'coupon',
							'external_category_id' => $external_id,
							'site_term_id'         => 0,
							'weight'               => 100,
							'status'               => 'unmapped',
							'created_at'           => $now,
						)
					)
				);
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Synchronize campaign snapshots.
	 *
	 * @param array<int, array<string, mixed>> $items Normalized campaigns.
	 */
	public function sync_campaigns( array $items ): int {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		$count = 0;
		foreach ( $items as $item ) {
			$campaign_id = absint( $item['external_id'] ?? 0 );
			if ( ! $campaign_id ) {
				continue;
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot use a value placeholder.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Campaign state lives in the custom table.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table}
					(campaign_id, display_name, default_term_id, signal_weight, status, category_snapshot, created_at, updated_at)
					VALUES (%d, %s, 0, %d, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), status = VALUES(status),
					category_snapshot = VALUES(category_snapshot), updated_at = VALUES(updated_at)",
					$campaign_id,
					sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
					(int) Promokodiki_Admitad_Config::get( 'weight_company' ),
					'active' === ( $item['source_status'] ?? '' ) ? 'active' : 'inactive',
					wp_json_encode( $item['categories'] ?? array(), JSON_UNESCAPED_UNICODE ),
					$now,
					$now
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			++$count;
		}
		return $count;
	}
}
