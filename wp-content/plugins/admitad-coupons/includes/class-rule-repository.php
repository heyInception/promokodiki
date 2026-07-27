<?php
/**
 * Safe phrase-rule persistence and matching.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Every interpolated identifier comes from Promokodiki_Admitad_Schema, while values use placeholders.
/**
 * Stores versioned rules and matches only active, explicit modes.
 */
final class Promokodiki_Admitad_Rule_Repository {
	/**
	 * Active rules cached for the current request.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $active_cache = null;

	/**
	 * Return a bounded administration page.
	 *
	 * @param string $search   Phrase search.
	 * @param int    $page     One-based page.
	 * @param int    $per_page Rows per page.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_rows( string $search = '', int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table    = Promokodiki_Admitad_Schema::table( 'rule' );
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$search   = Promokodiki_Admitad_Text_Normalizer::normalize( $search );
		$where    = '';
		$args     = array();
		if ( '' !== $search ) {
			$where = ' WHERE normalized_phrase LIKE %s';
			$args  = array( '%' . $wpdb->esc_like( $search ) . '%' );
		}
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Optional prepared fragments contain only fixed SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned rule state.
		$total = (int) $wpdb->get_var( $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) : "SELECT COUNT(*) FROM {$table}" );
		$query = "SELECT id, normalized_phrase, match_mode, site_term_id, weight, status, source,
			evidence_count, distinct_campaign_count, contradiction_count, rule_version
			FROM {$table}{$where} ORDER BY normalized_phrase ASC, id ASC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administration reads plugin-owned rule state.
		$items = $wpdb->get_results( $wpdb->prepare( $query, ...array_merge( $args, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return array(
			'items'    => (array) $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Save or update one normalized rule.
	 *
	 * @param string $phrase  Source phrase.
	 * @param int    $term_id Site category term ID.
	 * @param int    $weight  Signal weight.
	 * @param string $status  Rule status.
	 * @param string $mode    Match mode.
	 * @param string $source  Rule source.
	 * @return int Rule ID.
	 * @throws InvalidArgumentException For invalid rule values.
	 * @throws RuntimeException When persistence fails.
	 */
	public function save(
		string $phrase,
		int $term_id,
		int $weight = 20,
		string $status = 'candidate',
		string $mode = 'phrase',
		string $source = 'editorial'
	): int {
		global $wpdb;

		$phrase = Promokodiki_Admitad_Text_Normalizer::normalize( $phrase );
		$status = sanitize_key( $status );
		$mode   = sanitize_key( $mode );
		if ( '' === $phrase || ! $this->is_valid_term( $term_id ) ) {
			throw new InvalidArgumentException( 'A non-empty phrase and valid promocode category are required.' );
		}
		if ( ! in_array( $status, array( 'active', 'candidate', 'suspended', 'conflict' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid Admitad rule status.' );
		}
		if ( ! in_array( $mode, array( 'phrase', 'token', 'prefix' ), true ) ) {
			throw new InvalidArgumentException( 'Invalid Admitad rule match mode.' );
		}

		$table = Promokodiki_Admitad_Schema::table( 'rule' );
		$now   = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Durable rule state uses the plugin-owned table; the validated identifier cannot be a placeholder.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
				(normalized_phrase, match_mode, site_term_id, weight, status, source, created_at, updated_at)
				VALUES (%s, %s, %d, %d, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE weight = VALUES(weight), status = VALUES(status),
				source = VALUES(source), rule_version = rule_version + 1, updated_at = VALUES(updated_at)",
				$phrase,
				$mode,
				$term_id,
				max( 0, min( 1000, $weight ) ),
				$status,
				sanitize_key( $source ),
				$now,
				$now
			)
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to save Admitad phrase rule.' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot be a placeholder.
		$rule_id            = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE normalized_phrase = %s AND match_mode = %s AND site_term_id = %d",
				$phrase,
				$mode,
				$term_id
			)
		);
		self::$active_cache = null;
		return $rule_id;
	}

	/**
	 * Match active rules against normalized text.
	 *
	 * @param string $normalized_text Source or normalized text.
	 * @return array<int, array<string, mixed>>
	 */
	public function match( string $normalized_text ): array {
		$text    = Promokodiki_Admitad_Text_Normalizer::normalize( $normalized_text );
		$tokens  = Promokodiki_Admitad_Text_Normalizer::tokens( $text );
		$padded  = ' ' . $text . ' ';
		$matches = array();
		foreach ( $this->active_rules() as $rule ) {
			$phrase  = (string) $rule['normalized_phrase'];
			$matched = match ( $rule['match_mode'] ) {
				'prefix' => $this->matches_prefix( $tokens, $phrase ),
				'token'  => in_array( $phrase, $tokens, true ),
				default  => str_contains( $padded, ' ' . $phrase . ' ' ),
			};
			if ( $matched ) {
				$matches[] = $rule;
			}
		}
		return $matches;
	}

	/**
	 * Set an allowed status and invalidate the request cache.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param string $status  New status.
	 */
	public function set_status( int $rule_id, string $status ): bool {
		global $wpdb;

		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'active', 'candidate', 'suspended', 'conflict' ), true ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Durable rule state uses the plugin-owned table.
		$result             = $wpdb->update(
			Promokodiki_Admitad_Schema::table( 'rule' ),
			array(
				'status'       => $status,
				'rule_version' => 1 + $this->version( $rule_id ),
				'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $rule_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		self::$active_cache = null;
		return false !== $result;
	}

	/**
	 * Find the status of a normalized phrase and term, regardless of mode.
	 *
	 * @param string $phrase  Phrase.
	 * @param int    $term_id Site term ID.
	 */
	public function find_status( string $phrase, int $term_id ): string {
		global $wpdb;

		$table  = Promokodiki_Admitad_Schema::table( 'rule' );
		$phrase = Promokodiki_Admitad_Text_Normalizer::normalize( $phrase );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot be a placeholder.
		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE normalized_phrase = %s AND site_term_id = %d ORDER BY id ASC LIMIT 1",
				$phrase,
				$term_id
			)
		);
	}

	/**
	 * Find the first rule ID for a phrase and term.
	 *
	 * @param string $phrase  Phrase.
	 * @param int    $term_id Site term ID.
	 */
	public function find_id( string $phrase, int $term_id ): int {
		global $wpdb;

		$table  = Promokodiki_Admitad_Schema::table( 'rule' );
		$phrase = Promokodiki_Admitad_Text_Normalizer::normalize( $phrase );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rules are request-cached; the validated table identifier cannot be a placeholder.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE normalized_phrase = %s AND site_term_id = %d ORDER BY id ASC LIMIT 1",
				$phrase,
				$term_id
			)
		);
	}

	/**
	 * Persist accumulated evidence counters.
	 *
	 * @param int $rule_id        Rule ID.
	 * @param int $observations   Observation count.
	 * @param int $campaigns      Distinct campaign count.
	 * @param int $contradictions Contradiction count.
	 */
	public function update_evidence( int $rule_id, int $observations, int $campaigns, int $contradictions ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Durable evidence counters use the plugin-owned table.
		$wpdb->update(
			Promokodiki_Admitad_Schema::table( 'rule' ),
			array(
				'evidence_count'          => max( 0, $observations ),
				'distinct_campaign_count' => max( 0, $campaigns ),
				'contradiction_count'     => max( 0, $contradictions ),
				'updated_at'              => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $rule_id ),
			array( '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Load active rules once per request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function active_rules(): array {
		global $wpdb;

		if ( null !== self::$active_cache ) {
			return self::$active_cache;
		}
		$table = Promokodiki_Admitad_Schema::table( 'rule' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Rules are request-cached; the validated table identifier cannot be a placeholder.
		$rows               = $wpdb->get_results( "SELECT id, normalized_phrase, match_mode, site_term_id, weight, source, rule_version FROM {$table} WHERE status = 'active' ORDER BY weight DESC, id ASC", ARRAY_A );
		self::$active_cache = array_values(
			array_filter(
				array_map(
					static function ( array $row ): array {
						$row['id']           = (int) $row['id'];
						$row['site_term_id'] = (int) $row['site_term_id'];
						$row['weight']       = (int) $row['weight'];
						$row['rule_version'] = (int) $row['rule_version'];
						return $row;
					},
					(array) $rows
				),
				fn( array $row ): bool => $this->is_valid_term( $row['site_term_id'] )
			)
		);
		return self::$active_cache;
	}

	/**
	 * Match an explicit prefix against complete tokens.
	 *
	 * @param array<int, string> $tokens Tokens.
	 * @param string             $prefix Prefix.
	 */
	private function matches_prefix( array $tokens, string $prefix ): bool {
		foreach ( $tokens as $token ) {
			if ( str_starts_with( $token, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate a taxonomy term.
	 *
	 * @param int $term_id Term ID.
	 */
	private function is_valid_term( int $term_id ): bool {
		$term = get_term( $term_id, 'promocode_category' );
		return $term instanceof WP_Term;
	}

	/**
	 * Return current rule version.
	 *
	 * @param int $rule_id Rule ID.
	 */
	private function version( int $rule_id ): int {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'rule' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot be a placeholder.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT rule_version FROM {$table} WHERE id = %d", $rule_id ) );
	}
}
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
