<?php
/**
 * Canonical administrative request state.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses allowlisted state and produces canonical WordPress admin URLs.
 */
final class Promokodiki_Admitad_Admin_Request {
	/**
	 * Default page size.
	 */
	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Allowlisted Admitad administration page slugs.
	 *
	 * @var string[]
	 */
	private const PAGES = array(
		'admitad-overview',
		'admitad-sync',
		'admitad-category-map',
		'admitad-companies',
		'admitad-rules',
		'admitad-review',
		'admitad-history',
		'admitad-settings',
		'admitad-diagnostics',
	);

	/**
	 * Allowlisted page-specific filter keys.
	 *
	 * @var string[]
	 */
	private const FILTER_KEYS = array(
		'status',
		'reason',
		'tab',
		'snapshot',
		'sample',
	);

	/**
	 * Allowed page sizes.
	 *
	 * @var int[]
	 */
	private const PER_PAGE_OPTIONS = array( 20, 50, 100 );

	/**
	 * Current page slug.
	 *
	 * @var string
	 */
	private string $page;

	/**
	 * Current one-based page number.
	 *
	 * @var int
	 */
	private int $paged;

	/**
	 * Current page size.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Sanitized search query.
	 *
	 * @var string
	 */
	private string $search;

	/**
	 * Sanitized allowlisted filters.
	 *
	 * @var array<string,string>
	 */
	private array $filters;

	/**
	 * Create request state.
	 *
	 * @param string               $page     Current page slug.
	 * @param int                  $paged    One-based page number.
	 * @param int                  $per_page Allowed page size.
	 * @param string               $search   Sanitized search query.
	 * @param array<string,string> $filters  Sanitized allowlisted filters.
	 */
	private function __construct( string $page, int $paged, int $per_page, string $search, array $filters ) {
		$this->page     = $page;
		$this->paged    = $paged;
		$this->per_page = $per_page;
		$this->search   = $search;
		$this->filters  = $filters;
	}

	/**
	 * Build canonical request state from query input.
	 *
	 * @param array<mixed> $input Query input.
	 * @param string       $page  Current page slug.
	 * @return self Canonical request state.
	 */
	public static function from_array( array $input, string $page ): self {
		$page = sanitize_key( $page );
		if ( ! in_array( $page, self::PAGES, true ) ) {
			$page = 'admitad-overview';
		}

		$paged    = self::integer_value( $input, 'paged', 1 );
		$per_page = self::integer_value( $input, 'per_page', self::DEFAULT_PER_PAGE );
		$search   = self::text_value( $input, 's' );
		$filters  = array();

		if ( $paged < 1 ) {
			$paged = 1;
		}

		if ( ! in_array( $per_page, self::PER_PAGE_OPTIONS, true ) ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		foreach ( self::FILTER_KEYS as $key ) {
			$value = self::text_value( $input, $key );
			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		return new self( $page, $paged, $per_page, $search, $filters );
	}

	/**
	 * Get page slug.
	 *
	 * @return string Page slug.
	 */
	public function page(): string {
		return $this->page;
	}

	/**
	 * Get one-based page number.
	 *
	 * @return int Page number.
	 */
	public function paged(): int {
		return $this->paged;
	}

	/**
	 * Get page size.
	 *
	 * @return int Page size.
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * Get search query.
	 *
	 * @return string Search query.
	 */
	public function search(): string {
		return $this->search;
	}

	/**
	 * Get an allowlisted filter value.
	 *
	 * @param string $key Filter key.
	 * @return string Filter value or an empty string.
	 */
	public function filter( string $key ): string {
		return $this->filters[ $key ] ?? '';
	}

	/**
	 * Get canonical WordPress admin query arguments.
	 *
	 * @return array<string,int|string> Query arguments.
	 */
	public function query_args(): array {
		$args = array(
			'post_type' => 'promocode',
			'page'      => $this->page,
			'paged'     => $this->paged,
			'per_page'  => $this->per_page,
		);

		if ( '' !== $this->search ) {
			$args['s'] = $this->search;
		}

		foreach ( self::FILTER_KEYS as $key ) {
			if ( isset( $this->filters[ $key ] ) ) {
				$args[ $key ] = $this->filters[ $key ];
			}
		}

		return $args;
	}

	/**
	 * Get canonical WordPress admin URL.
	 *
	 * @return string Canonical URL.
	 */
	public function url(): string {
		return add_query_arg( $this->query_args(), admin_url( 'edit.php' ) );
	}

	/**
	 * Get an integer input value when it is scalar.
	 *
	 * @param array<mixed> $input   Query input.
	 * @param string       $key     Query key.
	 * @param int          $default Default value.
	 * @return int Parsed integer.
	 */
	private static function integer_value( array $input, string $key, int $default ): int {
		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
			return $default;
		}

		return absint( wp_unslash( (string) $input[ $key ] ) );
	}

	/**
	 * Get a sanitized text input value when it is scalar.
	 *
	 * @param array<mixed> $input Query input.
	 * @param string       $key   Query key.
	 * @return string Sanitized text.
	 */
	private static function text_value( array $input, string $key ): string {
		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $input[ $key ] ) );
	}
}
