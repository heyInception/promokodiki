<?php
/**
 * Company classification profiles.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores a campaign default and its allowed taxonomy set.
 */
final class Promokodiki_Admitad_Company_Profile_Repository {
	/**
	 * Return a bounded administration page with allowed categories.
	 *
	 * @param string $search   Campaign name or ID search.
	 * @param int    $page     One-based page.
	 * @param int    $per_page Rows per page.
	 * @param array<string, string> $filters Allowlisted status filter.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_rows( string $search = '', int $page = 1, int $per_page = 20, array $filters = array() ): array {
		global $wpdb;

		$table    = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$page     = max( 1, $page );
		$per_page = $this->page_size( $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = sanitize_text_field( $search );
		$clauses  = array();
		$args     = array();
		if ( '' !== $search ) {
			$clauses[] = '(display_name LIKE %s OR CAST(campaign_id AS CHAR) LIKE %s)';
			$needle = '%' . $wpdb->esc_like( $search ) . '%';
			$args   = array( $needle, $needle );
		}
		if ( in_array( $filters['status'] ?? '', array( 'active', 'inactive', 'archived' ), true ) ) {
			$clauses[] = 'status = %s'; $args[] = $filters['status'];
		}
		$where = $clauses ? ' WHERE ' . implode( ' AND ', $clauses ) : '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Identifier is plugin-owned and the optional prepared fragments contain only fixed SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned company state.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) : "SELECT COUNT(*) FROM {$table}" );
		$query = "SELECT campaign_id, display_name, default_term_id, signal_weight, status, category_snapshot
			FROM {$table}{$where} ORDER BY display_name ASC, campaign_id ASC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned company state.
		$items = (array) $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( $items as &$item ) {
			$profile                  = $this->profile_for_campaign( (int) $item['campaign_id'] );
			$item['allowed_term_ids'] = $profile['allowed_term_ids'] ?? array();
		}
		unset( $item );
		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @return array<int, array{campaign_id:int,display_name:string}> */
	public function search_campaigns( string $search, int $limit = 20 ): array {
		global $wpdb;

		$table  = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$search = sanitize_text_field( $search );
		$limit  = max( 1, min( 20, $limit ) );
		$needle = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned identifier; every value is prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Autocomplete reads plugin-owned campaign references.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT campaign_id, display_name FROM {$table} WHERE display_name LIKE %s OR CAST(campaign_id AS CHAR) LIKE %s ORDER BY display_name ASC, campaign_id ASC LIMIT %d", $needle, $needle, $limit ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( static fn( array $row ): array => array( 'campaign_id' => (int) $row['campaign_id'], 'display_name' => (string) $row['display_name'] ), $rows );
	}

	/**
	 * Read one active campaign profile.
	 *
	 * @param int $campaign_id Admitad campaign ID.
	 * @return array{default_term_id:int,allowed_term_ids:array<int,int>,weight:int}|null
	 */
	public function profile_for_campaign( int $campaign_id ): ?array {
		global $wpdb;

		$profile_table  = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$category_table = Promokodiki_Admitad_Schema::table( 'company_category' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifiers come from Promokodiki_Admitad_Schema; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Company profiles use plugin-owned tables.
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT default_term_id, signal_weight FROM {$profile_table}
				WHERE campaign_id = %d AND status = 'active'",
				$campaign_id
			),
			ARRAY_A
		);
		if ( ! is_array( $profile ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Company profiles use plugin-owned tables.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT site_term_id FROM {$category_table}
				WHERE campaign_id = %d AND status = 'active' ORDER BY id ASC",
				$campaign_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$allowed = array_values(
			array_filter(
				array_map( 'intval', (array) $rows ),
				array( $this, 'is_valid_term' )
			)
		);
		$default = (int) $profile['default_term_id'];
		if ( ! in_array( $default, $allowed, true ) ) {
			$default = 0;
		}
		return array(
			'default_term_id'  => $default,
			'allowed_term_ids' => $allowed,
			'weight'           => (int) $profile['signal_weight'],
		);
	}

	/**
	 * Save a complete active campaign profile.
	 *
	 * @param int             $campaign_id     Admitad campaign ID.
	 * @param int             $default_term_id Preferred term or zero.
	 * @param array<int, int> $allowed_term_ids Allowed terms.
	 * @param int             $weight          Signal weight.
	 * @param string          $display_name    Campaign label.
	 * @throws InvalidArgumentException For an invalid campaign or term.
	 * @throws RuntimeException When persistence fails.
	 * @throws Throwable When a transactional database operation fails.
	 */
	public function save_profile(
		int $campaign_id,
		int $default_term_id,
		array $allowed_term_ids,
		int $weight,
		string $display_name = ''
	): void {
		global $wpdb;

		if ( $campaign_id <= 0 ) {
			throw new InvalidArgumentException( 'A positive campaign ID is required.' );
		}
		$allowed = array_values( array_unique( array_map( 'absint', $allowed_term_ids ) ) );
		foreach ( $allowed as $term_id ) {
			if ( ! $this->is_valid_term( $term_id ) ) {
				throw new InvalidArgumentException( 'Every allowed category must exist.' );
			}
		}
		if ( ! in_array( $default_term_id, $allowed, true ) ) {
			$default_term_id = 0;
		}

		$profile_table  = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$category_table = Promokodiki_Admitad_Schema::table( 'company_category' );
		$now            = gmdate( 'Y-m-d H:i:s' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic profile replacement uses plugin-owned tables.
		$wpdb->query( 'START TRANSACTION' );
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned identifier; every editable value is prepared.
			$result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$profile_table} (campaign_id, display_name, default_term_id, signal_weight, status, category_snapshot, created_at, updated_at) VALUES (%d, %s, %d, %d, 'active', '[]', %s, %s) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), default_term_id = VALUES(default_term_id), signal_weight = VALUES(signal_weight), status = 'active', updated_at = VALUES(updated_at)", $campaign_id, sanitize_text_field( $display_name ), $default_term_id, max( 0, min( 1000, $weight ) ), $now, $now ) );
			if ( false === $result ) {
				throw new RuntimeException( 'Unable to save Admitad company profile.' );
			}
			$wpdb->delete( $category_table, array( 'campaign_id' => $campaign_id ), array( '%d' ) );
			foreach ( $allowed as $term_id ) {
				$inserted = $wpdb->insert(
					$category_table,
					array(
						'campaign_id'  => $campaign_id,
						'site_term_id' => $term_id,
						'is_default'   => $term_id === $default_term_id ? 1 : 0,
						'weight'       => max( 0, min( 1000, $weight ) ),
						'status'       => 'active',
						'created_at'   => $now,
						'updated_at'   => $now,
					)
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Unable to save an allowed company category.' );
				}
			}
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		}
		// phpcs:enable
	}

	private function page_size( int $per_page ): int {
		return in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
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
