<?php
/**
 * Deduplicated review queue persistence.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores explainable unresolved automation cases.
 */
final class Promokodiki_Admitad_Review_Queue_Repository {
	/**
	 * Enqueue or return an existing case.
	 *
	 * @param string               $type      Entity type.
	 * @param string               $entity_id Stable entity ID.
	 * @param string               $reason    Reason code.
	 * @param array<string, mixed> $evidence  Compact evidence.
	 * @return int Queue row ID.
	 * @throws RuntimeException When persistence fails.
	 */
	public function enqueue( string $type, string $entity_id, string $reason, array $evidence ): int {
		global $wpdb;

		$type       = sanitize_key( $type );
		$entity_id  = sanitize_text_field( $entity_id );
		$reason     = sanitize_key( $reason );
		$dedupe_key = hash( 'sha256', $type . '|' . $entity_id . '|' . $reason );
		$table      = Promokodiki_Admitad_Schema::table( 'review_queue' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue state uses a plugin-owned table with a validated identifier.
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE dedupe_key = %s LIMIT 1", $dedupe_key ) );
		if ( $existing > 0 ) {
			return $existing;
		}

		$now       = gmdate( 'Y-m-d H:i:s' );
		$sanitized = $this->sanitize_evidence( $evidence );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Durable queue state uses the plugin-owned table.
		$inserted = $wpdb->insert(
			$table,
			array(
				'dedupe_key'          => $dedupe_key,
				'entity_type'         => $type,
				'entity_id'           => $entity_id,
				'reason_code'         => $reason,
				'severity'            => 'conflicting_signals' === $reason ? 'high' : 'normal',
				'proposed_categories' => wp_json_encode( array_map( 'absint', (array) ( $sanitized['proposed_terms'] ?? array() ) ) ),
				'explanation'         => wp_json_encode( (array) ( $sanitized['explanation'] ?? array() ), JSON_UNESCAPED_UNICODE ),
				'evidence'            => wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'status'              => 'open',
				'assignee_id'         => 0,
				'resolution'          => '',
				'created_at'          => $now,
				'updated_at'          => $now,
			)
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to enqueue Admitad review case.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Count unresolved cases for a reason.
	 *
	 * @param string $reason Reason code.
	 */
	public function count_unresolved( string $reason ): int {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'review_queue' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table identifier comes from Promokodiki_Admitad_Schema; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue state uses a plugin-owned table with a validated identifier.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'open' AND reason_code = %s",
				sanitize_key( $reason )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $count;
	}

	/**
	 * Recursively sanitize and size-bound evidence.
	 *
	 * @param array<string, mixed> $evidence Evidence.
	 * @return array<string, mixed>
	 */
	private function sanitize_evidence( array $evidence ): array {
		$clean = array();
		foreach ( array_slice( $evidence, 0, 30, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_evidence( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = mb_substr( sanitize_text_field( (string) $value ), 0, 500 );
			}
		}
		return $clean;
	}
}
