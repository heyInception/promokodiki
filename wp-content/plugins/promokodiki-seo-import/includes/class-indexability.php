<?php
/** Yoast robots and canonical policy. */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_SEO_Indexability {
	private const FILTER_KEYS = array( 's', 'paf_category', 'paf_brand', 'paf_sort', 'paf_popular', 'paf_page' );

	public static function register(): void {
		add_filter( 'wpseo_robots_array', array( self::class, 'robots' ) );
		add_filter( 'wpseo_canonical', array( self::class, 'canonical' ) );
	}

	public static function has_filter_parameters( array $query ): bool {
		return array() !== array_intersect( self::FILTER_KEYS, array_keys( $query ) );
	}

	public static function robots( array $robots ): array {
		if ( self::has_filter_parameters( wp_unslash( $_GET ) ) || self::empty_category() ) {
			$robots['index'] = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	public static function canonical( string $canonical ): string {
		if ( ! self::has_filter_parameters( wp_unslash( $_GET ) ) ) { return $canonical; }
		return strtok( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), '?' );
	}

	private static function empty_category(): bool {
		if ( ! is_tax( 'promocode_category' ) ) { return false; }
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term || '' !== trim( wp_strip_all_tags( $term->description ) ) ) { return false; }
		$query = new WP_Query( array(
			'post_type' => 'promocode', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true,
			'tax_query' => array( array( 'taxonomy' => 'promocode_category', 'field' => 'term_id', 'terms' => $term->term_id ) ),
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array( 'key' => '_promocode_is_active', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_promocode_is_active', 'value' => 'no', 'compare' => '!=' ),
				),
				array(
					'relation' => 'OR',
					array( 'key' => '_promocode_expiry_date', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_promocode_expiry_date', 'value' => '' ),
					array( 'key' => '_promocode_expiry_date', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ),
				),
			),
		) );
		return ! $query->have_posts();
	}
}
