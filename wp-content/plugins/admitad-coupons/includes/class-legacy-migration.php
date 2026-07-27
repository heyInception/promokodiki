<?php
/**
 * Non-destructive migration of legacy mapping tables.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies legacy mapping intent into versioned repositories without deleting sources.
 */
final class Promokodiki_Admitad_Legacy_Migration {
	/**
	 * Migration state option.
	 */
	private const OPTION_NAME = 'promokodiki_admitad_legacy_migration';

	/**
	 * Legacy table names.
	 *
	 * @var array<string,string>
	 */
	private array $tables;

	/**
	 * Durable state option name.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Cached normalized phrases mapped to multiple terms.
	 *
	 * @var array<string,bool>|null
	 */
	private ?array $conflicts = null;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $tables      Optional legacy table overrides for testing.
	 * @param string               $option_name Optional isolated state option for testing.
	 * @throws InvalidArgumentException For a table outside the current WordPress prefix.
	 */
	public function __construct( array $tables = array(), string $option_name = self::OPTION_NAME ) {
		global $wpdb;

		$defaults          = array(
			'keywords'   => $wpdb->prefix . 'subcategory_keywords',
			'companies'  => $wpdb->prefix . 'admitad_companies_mapping',
			'categories' => $wpdb->prefix . 'admitad_category_mapping',
		);
		$this->tables      = array_merge( $defaults, $tables );
		$this->option_name = sanitize_key( $option_name );
		if ( '' === $this->option_name ) {
			throw new InvalidArgumentException( 'A migration state option name is required.' );
		}
		foreach ( $this->tables as $table ) {
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! str_starts_with( $table, $wpdb->prefix ) ) {
				throw new InvalidArgumentException( 'Legacy table names must use the current WordPress prefix.' );
			}
		}
	}

	/**
	 * Analyze source counts without changing any data.
	 *
	 * @return array<string,int|bool>
	 */
	public function analyze(): array {
		$keywords   = $this->count( 'keywords' );
		$companies  = $this->count( 'companies' );
		$categories = $this->count( 'categories' );
		return array(
			'legacy_keywords'       => $keywords,
			'legacy_companies'      => $companies,
			'legacy_category_names' => $categories,
			'total'                 => $keywords + $companies + $categories,
			'sources_present'       => $keywords + $companies + $categories > 0,
		);
	}

	/**
	 * Copy one stable offset range; reruns are idempotent.
	 *
	 * @param int $offset Global source offset.
	 * @param int $limit  Maximum source rows.
	 * @return array{processed:int,created:int,skipped:int,next_offset:int,complete:bool}
	 */
	public function migrate_batch( int $offset, int $limit ): array {
		$offset   = max( 0, $offset );
		$limit    = max( 1, min( 2000, $limit ) );
		$analysis = $this->analyze();
		$rows     = $this->batch_rows( $offset, $limit, $analysis );
		$state    = $this->state();
		$created  = 0;
		$skipped  = 0;
		foreach ( $rows as $item ) {
			$type = $item['type'];
			$row  = $item['row'];
			$id   = (int) $row['id'];
			if ( isset( $state[ $type ][ $id ] ) ) {
				++$skipped;
				continue;
			}
			$result = 'companies' === $type
				? $this->migrate_company( $row )
				: $this->migrate_rule( $row, $type );
			if ( $result['destination_id'] > 0 ) {
				$state[ $type ][ $id ] = $result['destination_id'];
			}
			if ( $result['created'] ) {
				++$created;
			} else {
				++$skipped;
			}
		}
		$seed                           = ( new Promokodiki_Admitad_Taxonomy_Rule_Seeder() )->seed_all_terms();
		$state['created_seed_rule_ids'] = array_values(
			array_unique(
				array_merge(
					$state['created_seed_rule_ids'],
					$seed['created_rule_ids']
				)
			)
		);
		$state['version']               = 1;
		$state['cursor']                = min( (int) $analysis['total'], $offset + count( $rows ) );
		$state['updated_at']            = time();
		update_option( $this->option_name, $state, false );
		return array(
			'processed'   => count( $rows ),
			'created'     => $created,
			'skipped'     => $skipped,
			'next_offset' => $state['cursor'],
			'complete'    => $state['cursor'] >= $analysis['total'],
		);
	}

	/**
	 * Verify destination coverage and source preservation.
	 *
	 * @return array<string,mixed>
	 */
	public function verify(): array {
		global $wpdb;

		$analysis = $this->analyze();
		$state    = $this->state();
		$rule     = Promokodiki_Admitad_Schema::table( 'rule' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verification reads plugin-owned migrated rule state.
		$suspended = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rule} WHERE source IN ('legacy_keyword','legacy_category') AND status = 'suspended'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verification reads plugin-owned migrated rule state.
		$conflicts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rule} WHERE source IN ('legacy_keyword','legacy_category') AND status = 'conflict'" );
		$missing   = 0;
		$terms     = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			$rules = new Promokodiki_Admitad_Rule_Repository();
			foreach ( $terms as $term ) {
				if ( $rules->find_id( $term->name, (int) $term->term_id ) <= 0 ) {
					++$missing;
				}
			}
		}
		return array(
			'migrated_keywords'           => count( array_filter( $state['keywords'] ) ),
			'migrated_companies'          => count( array_filter( $state['companies'] ) ),
			'migrated_category_names'     => count( array_filter( $state['categories'] ) ),
			'legacy_keywords_remaining'   => (int) $analysis['legacy_keywords'],
			'legacy_companies_remaining'  => (int) $analysis['legacy_companies'],
			'orphan_term_references'      => $this->orphan_count(),
			'taxonomy_terms_without_rule' => $missing,
			'suspended_unsafe'            => $suspended,
			'conflicting_rules'           => $conflicts,
			'created_seed_rule_ids'       => array_map( 'intval', $state['created_seed_rule_ids'] ),
			'cursor'                      => (int) $state['cursor'],
			'complete'                    => (int) $state['cursor'] >= (int) $analysis['total'],
		);
	}

	/**
	 * Return a global batch from the three ordered source tables.
	 *
	 * @param int                 $offset   Global offset.
	 * @param int                 $limit    Limit.
	 * @param array<string,mixed> $analysis Source counts.
	 * @return array<int,array{type:string,row:array<string,mixed>}>
	 */
	private function batch_rows( int $offset, int $limit, array $analysis ): array {
		$rows   = array();
		$ranges = array(
			'keywords'   => (int) $analysis['legacy_keywords'],
			'categories' => (int) $analysis['legacy_category_names'],
			'companies'  => (int) $analysis['legacy_companies'],
		);
		$start  = 0;
		foreach ( $ranges as $type => $count ) {
			$local_offset = max( 0, $offset - $start );
			if ( $offset < $start + $count && count( $rows ) < $limit ) {
				$take = min( $limit - count( $rows ), $count - $local_offset );
				foreach ( $this->fetch( $type, $local_offset, $take ) as $row ) {
					$rows[] = array(
						'type' => $type,
						'row'  => $row,
					);
				}
				$offset += $take;
			}
			$start += $count;
		}
		return $rows;
	}

	/**
	 * Copy one legacy keyword-like row.
	 *
	 * @param array<string,mixed> $row  Source row.
	 * @param string              $type keywords or categories.
	 * @return array{destination_id:int,created:bool}
	 */
	private function migrate_rule( array $row, string $type ): array {
		$phrase  = 'keywords' === $type ? (string) $row['keyword'] : (string) $row['admitad_category_name'];
		$term_id = absint( $row['site_subcategory_id'] ?? 0 );
		if ( ! get_term( $term_id, 'promocode_category' ) instanceof WP_Term ) {
			return array(
				'destination_id' => 0,
				'created'        => false,
			);
		}
		$rules      = new Promokodiki_Admitad_Rule_Repository();
		$normalized = Promokodiki_Admitad_Text_Normalizer::normalize( $phrase );
		$existing   = $rules->find_id( $normalized, $term_id );
		if ( $existing > 0 ) {
			return array(
				'destination_id' => $existing,
				'created'        => false,
			);
		}
		$status = $this->safe_phrase( $normalized ) ? 'active' : 'suspended';
		if ( isset( $this->conflict_phrases()[ $normalized ] ) ) {
			$status = 'conflict';
		}
		$mode = str_contains( $normalized, ' ' ) ? 'phrase' : 'token';
		$id   = $rules->save(
			$normalized,
			$term_id,
			absint( $row['weight'] ?? 20 ),
			$status,
			$mode,
			'keywords' === $type ? 'legacy_keyword' : 'legacy_category'
		);
		return array(
			'destination_id' => $id,
			'created'        => true,
		);
	}

	/**
	 * Copy a company name only when it resolves to exactly one stable campaign ID.
	 *
	 * @param array<string,mixed> $row Source row.
	 * @return array{destination_id:int,created:bool}
	 */
	private function migrate_company( array $row ): array {
		global $wpdb;

		$name          = sanitize_text_field( (string) $row['company_name'] );
		$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Stable campaign lookup uses the synchronized plugin-owned reference table.
		$campaigns = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT campaign_id FROM {$profile_table} WHERE display_name = %s ORDER BY campaign_id ASC LIMIT 2", $name ) ) );
		if ( 1 !== count( $campaigns ) ) {
			( new Promokodiki_Admitad_Review_Queue_Repository() )->enqueue(
				'campaign_name',
				$name,
				'missing_campaign_id',
				array( 'legacy_company_name' => $name )
			);
			return array(
				'destination_id' => 0,
				'created'        => false,
			);
		}
		$campaign_id = $campaigns[0];
		$term_id     = absint( $row['site_subcategory_id'] ?? 0 );
		if ( ! get_term( $term_id, 'promocode_category' ) instanceof WP_Term ) {
			return array(
				'destination_id' => 0,
				'created'        => false,
			);
		}
		$current = ( new Promokodiki_Admitad_Company_Profile_Repository() )->profile_for_campaign( $campaign_id );
		$allowed = array_values( array_unique( array_merge( $current['allowed_term_ids'] ?? array(), array( $term_id ) ) ) );
		$default = (int) ( $current['default_term_id'] ?? 0 );
		( new Promokodiki_Admitad_Company_Profile_Repository() )->save_profile(
			$campaign_id,
			$default > 0 ? $default : $term_id,
			$allowed,
			(int) Promokodiki_Admitad_Config::get( 'weight_company' ),
			$name
		);
		return array(
			'destination_id' => $campaign_id,
			'created'        => true,
		);
	}

	/**
	 * Return normalized source phrases that point to multiple terms.
	 *
	 * @return array<string,bool>
	 */
	private function conflict_phrases(): array {
		if ( null !== $this->conflicts ) {
			return $this->conflicts;
		}
		$terms = array();
		foreach ( array( 'keywords', 'categories' ) as $type ) {
			foreach ( $this->fetch( $type, 0, $this->count( $type ) ) as $row ) {
				$phrase = Promokodiki_Admitad_Text_Normalizer::normalize(
					(string) ( 'keywords' === $type ? $row['keyword'] : $row['admitad_category_name'] )
				);
				$terms[ $phrase ][ absint( $row['site_subcategory_id'] ?? 0 ) ] = true;
			}
		}
		$this->conflicts = array();
		foreach ( $terms as $phrase => $term_set ) {
			if ( count( $term_set ) > 1 ) {
				$this->conflicts[ $phrase ] = true;
			}
		}
		return $this->conflicts;
	}

	/**
	 * Reject fragments too short or containing legacy wildcard syntax.
	 *
	 * @param string $phrase Normalized phrase.
	 */
	private function safe_phrase( string $phrase ): bool {
		if ( '' === $phrase || preg_match( '/[%_*?]/u', $phrase ) ) {
			return false;
		}
		foreach ( Promokodiki_Admitad_Text_Normalizer::tokens( $phrase ) as $token ) {
			if ( mb_strlen( $token ) < 3 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Count source rows with absent taxonomy terms.
	 */
	private function orphan_count(): int {
		$count = 0;
		foreach ( array( 'keywords', 'categories', 'companies' ) as $type ) {
			foreach ( $this->fetch( $type, 0, $this->count( $type ) ) as $row ) {
				if ( ! get_term( absint( $row['site_subcategory_id'] ?? 0 ), 'promocode_category' ) instanceof WP_Term ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Read a table count, treating a missing legacy table as empty.
	 *
	 * @param string $type Source type.
	 */
	private function count( string $type ): int {
		global $wpdb;

		$table = $this->tables[ $type ];
		if ( ! $this->exists( $table ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated legacy identifier; analysis is intentionally uncached.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Fetch ordered source rows.
	 *
	 * @param string $type   Source type.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch( string $type, int $offset, int $limit ): array {
		global $wpdb;

		$table = $this->tables[ $type ];
		if ( $limit <= 0 || ! $this->exists( $table ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated legacy identifier and prepared bounds.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	/**
	 * Check for an exact source table.
	 *
	 * @param string $table Validated table name.
	 */
	private function exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy discovery cannot use the plugin schema.
		return $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Return normalized durable migration state.
	 *
	 * @return array<string,mixed>
	 */
	private function state(): array {
		$state = (array) get_option( $this->option_name, array() );
		return array_merge(
			array(
				'version'               => 1,
				'cursor'                => 0,
				'keywords'              => array(),
				'categories'            => array(),
				'companies'             => array(),
				'created_seed_rule_ids' => array(),
				'updated_at'            => 0,
			),
			$state
		);
	}
}
