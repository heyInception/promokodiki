<?php
/**
 * Classification history persistence.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores immutable before/after classification records.
 */
final class Promokodiki_Admitad_Classification_History_Repository {
	/**
	 * Return a bounded administration history page.
	 *
	 * @param string               $search   Post, trigger, algorithm, or snapshot search.
	 * @param int                  $page     One-based page.
	 * @param int                  $per_page Rows per page.
	 * @param array<string,string> $filters  Allowlisted confidence, trigger, and snapshot filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_rows( string $search = '', int $page = 1, int $per_page = 20, array $filters = array() ): array {
		global $wpdb;

		if ( 2 === func_num_args() && ctype_digit( $search ) ) {
			$per_page = $page;
			$page     = (int) $search;
			$search   = '';
		}
		$table    = Promokodiki_Admitad_Schema::table( 'classification_history' );
		$page     = max( 1, $page );
		$per_page = $this->page_size( $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = sanitize_text_field( $search );
		$clauses  = array();
		$args     = array();
		if ( '' !== $search ) {
			$needle = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(CAST(post_id AS CHAR) LIKE %s OR trigger_name LIKE %s OR algorithm_version LIKE %s OR snapshot_id LIKE %s)';
			$args = array( $needle, $needle, $needle, $needle );
		}
		if ( in_array( $filters['confidence'] ?? '', array( 'low', 'medium', 'high' ), true ) ) { $clauses[] = 'confidence = %s'; $args[] = $filters['confidence']; }
		if ( '' !== sanitize_key( $filters['trigger_name'] ?? '' ) ) { $clauses[] = 'trigger_name = %s'; $args[] = sanitize_key( $filters['trigger_name'] ); }
		if ( '' !== sanitize_text_field( $filters['snapshot_id'] ?? '' ) ) { $clauses[] = 'snapshot_id = %s'; $args[] = sanitize_text_field( $filters['snapshot_id'] ); }
		$where = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Administration reads immutable plugin-owned history.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) : "SELECT COUNT(*) FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Administration reads immutable plugin-owned history.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", ...array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A );
		return array(
			'items'    => array_map( array( $this, 'decode' ), (array) $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	private function page_size( int $per_page ): int {
		return in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
	}

	/**
	 * Record one classification proposal or assignment.
	 *
	 * @param int                                       $post_id          Post ID.
	 * @param Promokodiki_Admitad_Classification_Result $result           Classification.
	 * @param array<int, int>                           $previous_terms   Previous terms.
	 * @param int                                       $previous_primary Previous primary.
	 * @param string                                    $trigger           Trigger name.
	 * @param string                                    $snapshot_id       Optional snapshot ID.
	 * @return int History row ID.
	 * @throws RuntimeException When persistence fails.
	 */
	public function record(
		int $post_id,
		Promokodiki_Admitad_Classification_Result $result,
		array $previous_terms,
		int $previous_primary,
		string $trigger,
		string $snapshot_id = ''
	): int {
		global $wpdb;

		$explanation   = $result->explanation();
		$rule_versions = array_map( 'intval', (array) ( $explanation['rule_versions'] ?? array() ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Immutable audit history is stored in the plugin-owned table.
		$inserted = $wpdb->insert(
			Promokodiki_Admitad_Schema::table( 'classification_history' ),
			array(
				'snapshot_id'              => '' !== $snapshot_id ? sanitize_text_field( $snapshot_id ) : wp_generate_uuid4(),
				'post_id'                  => $post_id,
				'algorithm_version'        => sanitize_text_field( (string) ( $explanation['algorithm_version'] ?? '1.0' ) ),
				'rule_version'             => $rule_versions ? max( $rule_versions ) : 1,
				'previous_terms'           => wp_json_encode( array_values( array_map( 'absint', $previous_terms ) ) ),
				'result_terms'             => wp_json_encode( $result->term_ids() ),
				'previous_primary_term_id' => absint( $previous_primary ),
				'result_primary_term_id'   => $result->primary_term_id(),
				'confidence'               => sanitize_key( $result->confidence() ),
				'explanation'              => wp_json_encode( $explanation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'trigger_name'             => sanitize_key( $trigger ),
				'actor_id'                 => get_current_user_id(),
				'created_at'               => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Unable to write Admitad classification history.' );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Return the latest history row for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function latest_for_post( int $post_id ): ?array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Immutable history uses a plugin-owned table with a validated identifier.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post_id ), ARRAY_A );
		return is_array( $row ) ? $this->decode( $row ) : null;
	}

	/**
	 * Return one history row by ID.
	 *
	 * @param int $history_id History row ID.
	 * @return array<string,mixed>|null
	 */
	public function get_by_id( int $history_id ): ?array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Immutable history uses a plugin-owned table with a validated identifier.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $history_id ), ARRAY_A );
		return is_array( $row ) ? $this->decode( $row ) : null;
	}

	/**
	 * Return all preview rows from a stored snapshot.
	 *
	 * @param string $snapshot_id Snapshot UUID.
	 * @return array<int, array<string, mixed>>
	 */
	public function snapshot_rows( string $snapshot_id ): array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'classification_history' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table identifier comes from Promokodiki_Admitad_Schema; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Immutable history uses a plugin-owned table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE snapshot_id = %s AND trigger_name = 'preview' ORDER BY id ASC",
				sanitize_text_field( $snapshot_id )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( array( $this, 'decode' ), (array) $rows );
	}

	/**
	 * Decode JSON columns and integer IDs.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function decode( array $row ): array {
		foreach ( array( 'id', 'post_id', 'previous_primary_term_id', 'result_primary_term_id', 'rule_version', 'actor_id' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}
		foreach ( array( 'previous_terms', 'result_terms', 'explanation' ) as $key ) {
			$decoded     = json_decode( (string) $row[ $key ], true );
			$row[ $key ] = is_array( $decoded ) ? $decoded : array();
		}
		$row['previous_terms'] = array_map( 'intval', $row['previous_terms'] );
		$row['result_terms']   = array_map( 'intval', $row['result_terms'] );
		return $row;
	}
}
