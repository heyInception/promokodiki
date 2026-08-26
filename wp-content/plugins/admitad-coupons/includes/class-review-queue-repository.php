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
	 * Return a bounded queue page.
	 *
	 * @param string $search   Entity or reason search.
	 * @param int    $page     One-based page.
	 * @param int    $per_page Rows per page.
	 * @param array<string, mixed> $filters Allowlisted status, reason, and reason-group filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_rows( string $search = '', int $page = 1, int $per_page = 20, array $filters = array() ): array {
		global $wpdb;

		$table    = Promokodiki_Admitad_Schema::table( 'review_queue' );
		$page     = max( 1, $page );
		$per_page = $this->page_size( $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = sanitize_text_field( $search );
		$clauses  = array();
		$args     = array();
		$status   = $filters['status'] ?? 'open';
		if ( ! in_array( $status, array( 'open', 'resolved', 'archived' ), true ) ) { $status = 'open'; }
		$clauses[] = 'status = %s'; $args[] = $status;
		if ( '' !== $search ) {
			$clauses[] = '(entity_id LIKE %s OR reason_code LIKE %s)';
			$needle = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = $needle; $args[] = $needle;
		}
		$reasons = array();
		if ( isset( $filters['reasons'] ) && is_array( $filters['reasons'] ) ) {
			$reasons = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_slice( $filters['reasons'], 0, 5 ) ) ) ) );
		}
		$reason = sanitize_key( $filters['reason'] ?? '' );
		if ( $reasons ) {
			$clauses[] = 'reason_code IN (' . implode( ', ', array_fill( 0, count( $reasons ), '%s' ) ) . ')';
			$args = array_merge( $args, $reasons );
		} elseif ( '' !== $reason ) { $clauses[] = 'reason_code = %s'; $args[] = $reason; }
		$where = ' WHERE ' . implode( ' AND ', $clauses );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Identifier is plugin-owned and the optional prepared fragments contain only fixed SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned queue state.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) : "SELECT COUNT(*) FROM {$table}{$where}" );
		$query = "SELECT id, entity_type, entity_id, reason_code, severity, proposed_categories, explanation, evidence, status, created_at
			FROM {$table}{$where} ORDER BY CASE severity WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END ASC, id ASC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned queue state.
		$items = (array) $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( $items as &$item ) {
			foreach ( array( 'proposed_categories', 'explanation', 'evidence' ) as $field ) {
				$decoded        = json_decode( (string) $item[ $field ], true );
				$item[ $field ] = is_array( $decoded ) ? $decoded : array();
			}
		}
		unset( $item );
		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	private function page_size( int $per_page ): int {
		return in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
	}

	/**
	 * Read one open case.
	 *
	 * @param int $queue_id Queue ID.
	 * @return array<string,mixed>|null
	 */
	public function get_open( int $queue_id ): ?array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'review_queue' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue row is plugin-owned and ID is prepared.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND status = 'open'", $queue_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Resolve one queue case with a compact resolution code.
	 *
	 * @param int    $queue_id  Queue ID.
	 * @param string $resolution Resolution code.
	 */
	public function resolve( int $queue_id, string $resolution ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Durable queue mutation uses the plugin-owned table.
		$result = $wpdb->update(
			Promokodiki_Admitad_Schema::table( 'review_queue' ),
			array(
				'status'      => 'resolved',
				'assignee_id' => get_current_user_id(),
				'resolution'  => sanitize_key( $resolution ),
				'resolved_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'id'     => $queue_id,
				'status' => 'open',
			)
		);
		return false !== $result && $result > 0;
	}

	/** Archive an open queue case while retaining its evidence. */
	public function archive( int $queue_id ): bool {
		global $wpdb;
		$result = $wpdb->update( Promokodiki_Admitad_Schema::table( 'review_queue' ), array( 'status' => 'archived', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $queue_id, 'status' => 'open' ) );
		return false !== $result && $result > 0;
	}
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
		$now        = gmdate( 'Y-m-d H:i:s' );
		$sanitized  = $this->sanitize_evidence( $evidence );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue state uses a plugin-owned table with a validated identifier.
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$table} WHERE dedupe_key = %s LIMIT 1", $dedupe_key ), ARRAY_A );
		if ( is_array( $existing ) ) {
			if ( 'open' !== $existing['status'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A recurring issue reopens its deduplicated plugin-owned queue row.
				$wpdb->update(
					$table,
					array(
						'proposed_categories' => wp_json_encode( array_map( 'absint', (array) ( $sanitized['proposed_terms'] ?? array() ) ) ),
						'explanation'         => wp_json_encode( (array) ( $sanitized['explanation'] ?? array() ), JSON_UNESCAPED_UNICODE ),
						'evidence'            => wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
						'status'              => 'open',
						'assignee_id'         => 0,
						'resolution'          => '',
						'resolved_at'         => null,
						'updated_at'          => $now,
					),
					array( 'id' => (int) $existing['id'] )
				);
			}
			return (int) $existing['id'];
		}

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
