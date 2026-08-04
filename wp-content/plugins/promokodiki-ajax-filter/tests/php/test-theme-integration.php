<?php
/**
 * Verify that the theme delegates promocode filtering to the plugin.
 *
 * @package PromokodikiAjaxFilter
 */

require_once dirname( __DIR__ ) . '/harness.php';

$theme_dir = dirname( __DIR__, 4 ) . '/themes/promokodiki';

if ( ! function_exists( 'promokodiki_scripts' ) ) {
	$worktree_theme_root = static fn(): string => dirname( $theme_dir );
	add_filter( 'theme_root', $worktree_theme_root );
	require $theme_dir . '/functions.php';
}

Promokodiki_Filter_Test_Harness::run(
	'theme footer renders required markup and delegates script output to WordPress',
	static function () use ( $theme_dir ): void {
		$original_query   = $GLOBALS['wp_query'] ?? null;
		$original_scripts = $GLOBALS['wp_scripts'] ?? null;
		$original_footer  = $GLOBALS['wp_filter']['wp_footer'] ?? null;
		$marker           = '<!-- promokodiki-footer-hook-marker -->';
		$print_marker     = static function () use ( $marker ): void {
			echo $marker;
		};

		$GLOBALS['wp_query']   = new WP_Query();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_filter']['wp_footer'] = new WP_Hook();
		add_action( 'wp_footer', $print_marker, 999 );
		ob_start();
		try {
			require $theme_dir . '/footer.php';
			$footer = (string) ob_get_clean();
		} finally {
			remove_action( 'wp_footer', $print_marker, 999 );
			$GLOBALS['wp_query']   = $original_query;
			$GLOBALS['wp_scripts'] = $original_scripts;
			if ( null === $original_footer ) {
				unset( $GLOBALS['wp_filter']['wp_footer'] );
			} else {
				$GLOBALS['wp_filter']['wp_footer'] = $original_footer;
			}
			if ( ob_get_level() ) {
				ob_end_clean();
			}
		}

		Promokodiki_Filter_Test_Harness::assert_contains( $marker, $footer );
		Promokodiki_Filter_Test_Harness::assert_contains( 'id="promocodeModal"', $footer );
		Promokodiki_Filter_Test_Harness::assert_contains( 'footer__button_up', $footer );
		Promokodiki_Filter_Test_Harness::assert_not_contains( '<script', $footer );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'load_more_promocodes', $footer );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'load_more_search_results', $footer );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'DOMContentLoaded', $footer );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'window.openPromoModal', $footer );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'theme enqueues global UI scripts and limits search pagination to search requests',
	static function (): void {
		$original_query   = $GLOBALS['wp_query'] ?? null;
		$original_scripts = $GLOBALS['wp_scripts'] ?? null;

		try {
			$GLOBALS['wp_query']   = new WP_Query();
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			promokodiki_scripts();
			$non_search_scripts = wp_scripts();

			foreach ( array( 'promokodiki-footer-ui', 'promokodiki-navigation', 'promokodiki-promo-modal' ) as $handle ) {
				Promokodiki_Filter_Test_Harness::assert_true( $non_search_scripts->query( $handle, 'enqueued' ), $handle . ' was not enqueued globally' );
			}
			Promokodiki_Filter_Test_Harness::assert_true( ! $non_search_scripts->query( 'promokodiki-search-load-more', 'enqueued' ), 'search pagination was enqueued outside search' );

			$GLOBALS['wp_query']            = new WP_Query();
			$GLOBALS['wp_query']->is_search = true;
			$GLOBALS['wp_scripts']          = new WP_Scripts();
			promokodiki_scripts();
			$search_scripts = wp_scripts();

			Promokodiki_Filter_Test_Harness::assert_true( $search_scripts->query( 'promokodiki-search-load-more', 'enqueued' ), 'search pagination was not enqueued for search' );
			$localized_data = (string) $search_scripts->get_data( 'promokodiki-search-load-more', 'data' );
			Promokodiki_Filter_Test_Harness::assert_contains( 'PromokodikiSearchConfig', $localized_data );
			Promokodiki_Filter_Test_Harness::assert_contains( wp_create_nonce( 'promokodiki_search' ), $localized_data );
		} finally {
			$GLOBALS['wp_query']   = $original_query;
			$GLOBALS['wp_scripts'] = $original_scripts;
		}
	}
);

Promokodiki_Filter_Test_Harness::run(
	'search pagination rejects missing or invalid nonces before request processing',
	static function (): void {
		$original_post    = $_POST;
		$original_request = $_REQUEST;
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
		$die_handler = static function (): callable {
			return static function ( mixed $message = '', string $title = '', array $args = array() ): void {
				throw new RuntimeException( 'wp_die:' . (string) $message );
			};
		};

		add_filter( 'wp_die_handler', $die_handler );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		try {
			foreach ( array( null, 'invalid-nonce' ) as $nonce ) {
				$_POST = array(
					'page'         => 2,
					'search_query' => array( 'must-not-be-processed' ),
				);
				if ( null !== $nonce ) {
					$_POST['nonce'] = $nonce;
				}
				$_REQUEST = $_POST;

				ob_start();
				$exception = null;
				try {
					load_more_search_results();
				} catch ( Throwable $throwable ) {
					$exception = $throwable;
				}
				$output = (string) ob_get_clean();

				Promokodiki_Filter_Test_Harness::assert_true( null !== $exception, 'Invalid nonce did not terminate the request' );
				Promokodiki_Filter_Test_Harness::assert_same( 'wp_die:-1', $exception->getMessage() );
				Promokodiki_Filter_Test_Harness::assert_same( '', $output, 'Invalid nonce produced result markup' );
			}
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			$_POST    = $original_post;
			$_REQUEST = $original_request;
			if ( ob_get_level() ) {
				ob_end_clean();
			}
		}
	}
);

Promokodiki_Filter_Test_Harness::run(
	'search pagination accepts its nonce and reaches empty-query termination',
	static function (): void {
		$original_post    = $_POST;
		$original_request = $_REQUEST;
		$die_handler = static function (): callable {
			return static function ( mixed $message = '', string $title = '', array $args = array() ): void {
				throw new RuntimeException( 'wp_die:' . (string) $message );
			};
		};

		add_filter( 'wp_die_handler', $die_handler );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		try {
			$_POST = array(
				'nonce'        => wp_create_nonce( 'promokodiki_search' ),
				'search_query' => '',
			);
			$_REQUEST = $_POST;
			$exception = null;
			try {
				load_more_search_results();
			} catch ( RuntimeException $runtime_exception ) {
				$exception = $runtime_exception;
			}

			Promokodiki_Filter_Test_Harness::assert_true( null !== $exception, 'Empty query did not reach normal termination' );
			Promokodiki_Filter_Test_Harness::assert_same( 'wp_die:', $exception->getMessage() );
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			$_POST    = $original_post;
			$_REQUEST = $original_request;
		}
	}
);

$templates = array(
	'home'     => $theme_dir . '/template-parts/partials/promocodes.php',
	'category' => $theme_dir . '/taxonomy-promocode_category.php',
	'shop'     => $theme_dir . '/taxonomy-shops_category.php',
);

foreach ( $templates as $context => $path ) {
	Promokodiki_Filter_Test_Harness::run(
		'theme delegates the ' . $context . ' context to the filter plugin',
		static function () use ( $context, $path ): void {
			$contents = file_get_contents( $path );

			Promokodiki_Filter_Test_Harness::assert_true( false !== $contents, 'Could not read ' . $path );
			Promokodiki_Filter_Test_Harness::assert_contains( 'promokodiki_filter_render', $contents );
			Promokodiki_Filter_Test_Harness::assert_contains( "'context' => '" . $context . "'", $contents );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'fe_widget', $contents );
			Promokodiki_Filter_Test_Harness::assert_not_contains( 'fe_sort', $contents );
		}
	);
}

Promokodiki_Filter_Test_Harness::run(
	'theme no longer registers legacy filter ajax handlers',
	static function () use ( $theme_dir ): void {
		$contents = file_get_contents( $theme_dir . '/functions.php' );

		Promokodiki_Filter_Test_Harness::assert_not_contains( 'increment_promocode_used_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'wp_ajax_increment_promocode_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'function load_more_promocodes', $contents );
		Promokodiki_Filter_Test_Harness::assert_not_contains( 'wp_ajax_load_more_promocodes', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'theme modal script leaves click tracking to the plugin',
	static function () use ( $theme_dir ): void {
		$contents = file_get_contents( $theme_dir . '/js/promocodes-ajax.js' );

		Promokodiki_Filter_Test_Harness::assert_not_contains( 'increment_promocode_count', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'openPromoModal', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'mobile filter keeps controls in a horizontal scroll row',
	static function (): void {
		$contents = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/css/filter.css' );

		Promokodiki_Filter_Test_Harness::assert_contains( '@media (max-width: 767px)', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'overflow-x: auto', $contents );
		Promokodiki_Filter_Test_Harness::assert_contains( 'flex-flow: row nowrap', $contents );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'filter assets synchronize dropdowns and display the loader',
	static function (): void {
		$script = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/js/filter.js' );
		$styles = file_get_contents( PROMOKODIKI_FILTER_DIR . 'assets/css/filter.css' );

		Promokodiki_Filter_Test_Harness::assert_true( false !== $script );
		Promokodiki_Filter_Test_Harness::assert_true( false !== $styles );
		Promokodiki_Filter_Test_Harness::assert_contains( 'replaceSelectOptions', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'data-filter-loader', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'stateOverride', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'historyMode', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( "request(1, false, 'replace', state)", $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'window.history.replaceState', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'prepareResultsPayload', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( 'prepareSelectOptions', $script );
		Promokodiki_Filter_Test_Harness::assert_contains( '@keyframes promokodiki-filter-spin', $styles );
	}
);

$discounts_helper = $theme_dir . '/inc/discounts.php';
if ( file_exists( $discounts_helper ) ) {
	require_once $discounts_helper;
}

$discount_ids    = array();
$page_id         = 0;
$regular_page_id = 0;

try {
	$usage_values    = array( 10, 70, 20, 60, 30, 50, 40 );
	$reaction_values = array( 5, 3, 12, 9, 1, 14, 7 );
	for ( $index = 0; $index < 7; $index++ ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 'PAF fallback active ' . $index,
				'post_date'   => wp_date( 'Y-m-d H:i:s', time() - ( $index * HOUR_IN_SECONDS ) ),
			)
		);
		$discount_ids[] = $post_id;
		update_post_meta( $post_id, '_promocode_used_count', $usage_values[ $index ] );
		if ( 0 === $index % 2 ) {
			update_post_meta( $post_id, '_promocode_votes_total', $reaction_values[ $index ] );
		} else {
			update_post_meta( $post_id, '_promocode_likes', $reaction_values[ $index ] - 1 );
			update_post_meta( $post_id, '_promocode_dislikes', 1 );
		}
		if ( 5 === $index ) {
			update_post_meta( $post_id, '_promocode_expiry_date', '' );
		} elseif ( 6 !== $index ) {
			update_post_meta( $post_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
		}
	}

	$inactive_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'PAF fallback inactive' ) );
	$discount_ids[] = $inactive_id;
	update_post_meta( $inactive_id, '_promocode_is_active', 'no' );
	update_post_meta( $inactive_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	update_post_meta( $inactive_id, '_promocode_used_count', 99999 );
	update_post_meta( $inactive_id, '_promocode_votes_total', 99999 );

	$expired_id     = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'PAF fallback expired' ) );
	$discount_ids[] = $expired_id;
	update_post_meta( $expired_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) );
	update_post_meta( $expired_id, '_promocode_used_count', 99998 );
	update_post_meta( $expired_id, '_promocode_votes_total', 99998 );

	$restrict_query = static function ( WP_Query $query ) use ( &$discount_ids ): void {
		if ( 'promocode' === $query->get( 'post_type' ) ) {
			$query->set( 'post__in', $discount_ids );
		}
	};

	Promokodiki_Filter_Test_Harness::run(
		'discounts fallback returns six newest active unexpired posts and retains undated offers',
		static function () use ( $discount_ids, $inactive_id, $expired_id, $restrict_query ): void {
			Promokodiki_Filter_Test_Harness::assert_true( function_exists( 'promokodiki_discounts_fallback_query' ) );
			add_action( 'pre_get_posts', $restrict_query );
			try {
				$query = promokodiki_discounts_fallback_query( 'newest' );
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
			}

			$actual = array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) );
			Promokodiki_Filter_Test_Harness::assert_same( array_slice( $discount_ids, 0, 6 ), $actual );
			Promokodiki_Filter_Test_Harness::assert_same( 6, $query->post_count );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $inactive_id, $actual, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $expired_id, $actual, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $discount_ids[5], $actual, true ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts fallback orders popular by lifetime usage totals',
		static function () use ( $discount_ids, $restrict_query ): void {
			Promokodiki_Filter_Test_Harness::assert_true( function_exists( 'promokodiki_discounts_fallback_query' ) );
			add_action( 'pre_get_posts', $restrict_query );
			try {
				$query = promokodiki_discounts_fallback_query( 'popular' );
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
			}

			$expected = array( $discount_ids[1], $discount_ids[3], $discount_ids[5], $discount_ids[6], $discount_ids[4], $discount_ids[2] );
			Promokodiki_Filter_Test_Harness::assert_same( $expected, array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts fallback lazily orders discussed by stored or derived reaction totals',
		static function () use ( $discount_ids, $restrict_query ): void {
			Promokodiki_Filter_Test_Harness::assert_true( function_exists( 'promokodiki_discounts_fallback_query' ) );
			add_action( 'pre_get_posts', $restrict_query );
			try {
				$query = promokodiki_discounts_fallback_query( 'discussed' );
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
			}

			$expected = array( $discount_ids[5], $discount_ids[2], $discount_ids[3], $discount_ids[6], $discount_ids[0], $discount_ids[1] );
			Promokodiki_Filter_Test_Harness::assert_same( $expected, array_map( 'intval', wp_list_pluck( $query->posts, 'ID' ) ) );
		}
	);

	$page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'PAF canonical discounts page',
		)
	);
	update_post_meta( $page_id, '_wp_page_template', 'page-discounts.php' );
	$regular_page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'PAF canonical regular page',
		)
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts canonical strips GET sort through core without changing other templates',
		static function () use ( $page_id, $regular_page_id ): void {
			$inject_sort = static fn( string $url, WP_Post $post ): string => add_query_arg( 'paf_sort', 'newest', $url );
			add_filter( 'get_canonical_url', $inject_sort, 5, 2 );
			try {
				$discounts_url = wp_get_canonical_url( $page_id );
				$regular_url   = wp_get_canonical_url( $regular_page_id );
			} finally {
				remove_filter( 'get_canonical_url', $inject_sort, 5 );
			}

			Promokodiki_Filter_Test_Harness::assert_same( get_permalink( $page_id ), $discounts_url );
			Promokodiki_Filter_Test_Harness::assert_same(
				add_query_arg( 'paf_sort', 'newest', get_permalink( $regular_page_id ) ),
				$regular_url
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'discounts partial renders a single plugin feed without duplicate tab panels',
		static function () use ( $theme_dir, $restrict_query ): void {
			$original_get = $_GET;
			$_GET         = array( 'paf_sort' => 'newest' );
			add_action( 'pre_get_posts', $restrict_query );
			ob_start();
			try {
				require $theme_dir . '/template-parts/partials/promocodes-discounts.php';
				$html = (string) ob_get_clean();
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
				$_GET = $original_get;
				if ( ob_get_level() ) {
					ob_end_clean();
				}
			}

			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-promokodiki-filter' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-filter-results' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 1, substr_count( $html, 'data-filter-more' ) );
			Promokodiki_Filter_Test_Harness::assert_same( 0, substr_count( $html, 'tabs__panel' ) );
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	if ( $regular_page_id ) {
		wp_delete_post( $regular_page_id, true );
	}
	if ( $page_id ) {
		wp_delete_post( $page_id, true );
	}
	foreach ( $discount_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}
