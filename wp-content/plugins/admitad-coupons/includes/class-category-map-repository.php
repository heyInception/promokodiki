<?php
/**
 * Stable external category mappings.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps namespaced Admitad category IDs to existing site taxonomy terms.
 */
final class Promokodiki_Admitad_Category_Map_Repository {
	/**
	 * Return a bounded administration page.
	 *
	 * @param string $search   External name or ID search.
	 * @param int    $page     One-based page.
	 * @param int    $per_page Rows per page.
	 * @param array<string, string> $filters Allowlisted source/status filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_rows( string $search = '', int $page = 1, int $per_page = 20, array $filters = array() ): array {
		global $wpdb;

		$table    = Promokodiki_Admitad_Schema::table( 'category_map' );
		$page     = max( 1, $page );
		$per_page = $this->page_size( $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = sanitize_text_field( $search );
		$clauses  = array();
		$args     = array();
		if ( '' !== $search ) {
			$clauses[] = '(external_name LIKE %s OR CAST(external_category_id AS CHAR) LIKE %s)';
			$needle = '%' . $wpdb->esc_like( $search ) . '%';
			$args   = array( $needle, $needle );
		}
		if ( in_array( $filters['source_namespace'] ?? '', array( 'coupon', 'campaign' ), true ) ) {
			$clauses[] = 'source_namespace = %s';
			$args[]    = $filters['source_namespace'];
		}
		if ( in_array( $filters['status'] ?? '', array( 'active', 'unmapped', 'inactive' ), true ) ) {
			$clauses[] = 'status = %s';
			$args[]    = $filters['status'];
		}
		$where = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Identifier is plugin-owned and the optional prepared fragments contain only fixed SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned mapping state.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) : "SELECT COUNT(*) FROM {$table}" );
		$query = "SELECT id, source_namespace, external_category_id, external_name, external_parent_id, site_term_id, weight, status
			FROM {$table}{$where} ORDER BY external_name ASC, external_category_id ASC, site_term_id ASC, id ASC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned mapping state.
		$items = $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return array(
			'items'    => (array) $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	private function page_size( int $per_page ): int {
		return in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
	}

	/**
	 * Return active site terms for an external category.
	 *
	 * @param string $source_namespace Source namespace.
	 * @param int    $external_id External category ID.
	 * @return array<int, int>
	 */
	public function terms_for_external( string $source_namespace, int $external_id ): array {
		return array_column( $this->signals_for_external( $source_namespace, $external_id ), 'term_id' );
	}

	/**
	 * Return active weighted site-term signals.
	 *
	 * @param string $source_namespace Source namespace.
	 * @param int    $external_id External category ID.
	 * @return array<int, array{term_id:int,weight:int,namespace:string,external_id:int}>
	 */
	public function signals_for_external( string $source_namespace, int $external_id ): array {
		global $wpdb;

		$source_namespace = $this->validate_namespace( $source_namespace );
		$table            = Promokodiki_Admitad_Schema::table( 'category_map' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table identifier comes from Promokodiki_Admitad_Schema; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state uses the plugin-owned table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT site_term_id, weight FROM {$table}
				WHERE source_namespace = %s AND external_category_id = %d
				AND site_term_id > 0 AND status = 'active'
				ORDER BY weight DESC, site_term_id ASC",
				$source_namespace,
				$external_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$signals = array();
		foreach ( (array) $rows as $row ) {
			$term_id = (int) $row['site_term_id'];
			if ( ! $this->is_valid_term( $term_id ) ) {
				continue;
			}
			$signals[] = array(
				'term_id'     => $term_id,
				'weight'      => (int) $row['weight'],
				'namespace'   => $source_namespace,
				'external_id' => $external_id,
			);
		}
		return $signals;
	}

	/**
	 * Save one active mapping without mutating taxonomy.
	 *
	 * @param string $source_namespace Source namespace.
	 * @param int    $external_id External category ID.
	 * @param int    $term_id     Existing site term ID.
	 * @param int    $weight      Signal weight.
	 * @return int Mapping row ID.
	 * @throws InvalidArgumentException For invalid IDs or terms.
	 * @throws RuntimeException When persistence fails.
	 */
	public function save( string $source_namespace, int $external_id, int $term_id, int $weight ): int {
		global $wpdb;

		$source_namespace = $this->validate_namespace( $source_namespace );
		if ( $external_id <= 0 || ! $this->is_valid_term( $term_id ) ) {
			throw new InvalidArgumentException( 'A positive external ID and existing promocode category are required.' );
		}
		$table = Promokodiki_Admitad_Schema::table( 'category_map' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table identifier comes from Promokodiki_Admitad_Schema; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state uses the plugin-owned table.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
				(source_namespace, external_category_id, site_term_id, weight, status, created_at, updated_at)
				VALUES (%s, %d, %d, %d, 'active', %s, %s)
				ON DUPLICATE KEY UPDATE weight = VALUES(weight), status = 'active', updated_at = VALUES(updated_at)",
				$source_namespace,
				$external_id,
				$term_id,
				max( 0, min( 1000, $weight ) ),
				$now,
				$now
			)
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to save Admitad category mapping.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A real mapping supersedes the synchronized zero-term placeholder.
		$wpdb->delete(
			$table,
			array(
				'source_namespace'     => $source_namespace,
				'external_category_id' => $external_id,
				'site_term_id'         => 0,
			),
			array( '%s', '%d', '%d' )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mapping state uses the plugin-owned table.
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_namespace = %s AND external_category_id = %d AND site_term_id = %d",
				$source_namespace,
				$external_id,
				$term_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $id;
	}

	/**
	 * Validate a supported ID namespace.
	 *
	 * @param string $source_namespace Namespace.
	 * @throws InvalidArgumentException For an unsupported namespace.
	 */
	private function validate_namespace( string $source_namespace ): string {
		$source_namespace = sanitize_key( $source_namespace );
		if ( ! in_array( $source_namespace, array( 'coupon', 'campaign' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported Admitad category namespace.' );
		}
		return $source_namespace;
	}

	/**
	 * Validate an existing site taxonomy term.
	 *
	 * @param int $term_id Term ID.
	 */
	private function is_valid_term( int $term_id ): bool {
		return get_term( $term_id, 'promocode_category' ) instanceof WP_Term;
	}
}
