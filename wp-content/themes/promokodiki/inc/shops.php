<?php
/**
 * Shop catalogue and profile helpers.
 *
 * @package promokodiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read an optional ACF field without making ACF a theme dependency.
 *
 * @param string  $name Field name.
 * @param WP_Term $term Shop term.
 * @return mixed
 */
function promokodiki_shop_acf( string $name, WP_Term $term ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}

	return get_field( $name, 'shops_category_' . $term->term_id );
}

/**
 * Resolve a shop profile. Manually entered ACF values always win.
 */
function promokodiki_shop_profile( WP_Term $term ): array {
	$acf_description = promokodiki_shop_acf( 'shop_description', $term );
	$acf_about       = promokodiki_shop_acf( 'about_shop', $term );
	$rating = promokodiki_shop_acf( 'rating', $term );
	$rating = is_numeric( $rating ) && (float) $rating > 0 && (float) $rating <= 5
		? (float) $rating
		: (float) get_term_meta( $term->term_id, '_admitad_shop_rating', true );
	$rating = $rating > 0 && $rating <= 5 ? $rating : null;
	$image           = promokodiki_shop_acf( 'izobrazhenie_magazina', $term );
	$logo_id         = 0;
	$logo_url        = '';

	if ( is_array( $image ) ) {
		$logo_id = absint( $image['ID'] ?? $image['id'] ?? 0 );
	} elseif ( is_numeric( $image ) ) {
		$logo_id = absint( $image );
	} elseif ( is_string( $image ) ) {
		$logo_url = esc_url_raw( $image );
	}

	if ( ! $logo_id && ! $logo_url ) {
		$logo_id = absint( get_term_meta( $term->term_id, '_admitad_shop_logo_id', true ) );
	}
	if ( ! $logo_id && ! $logo_url ) {
		$logo_url = esc_url_raw( (string) get_term_meta( $term->term_id, '_admitad_shop_image_url', true ) );
	}
	if ( ! $logo_id && ! $logo_url ) {
		$logo_id = absint( get_term_meta( $term->term_id, 'shops-category-image-id', true ) );
	}

	$full_description = $acf_description ?: get_term_meta( $term->term_id, '_admitad_shop_manual_description', true );
	$full_description = $full_description ?: get_term_meta( $term->term_id, '_admitad_shop_source_description', true );
	$full_description = $full_description ?: get_term_meta( $term->term_id, '_admitad_shop_description', true );
	$full_description = $full_description ?: $term->description;
	$about            = $acf_about ?: get_term_meta( $term->term_id, '_admitad_shop_summary', true );
	$website          = promokodiki_shop_acf( 'website', $term ) ?: get_term_meta( $term->term_id, 'shop_website', true );
	$website          = $website ?: get_term_meta( $term->term_id, '_admitad_shop_website', true );
	$affiliate_url    = class_exists( 'Promokodiki_Admitad_Deeplink_Service' )
		? ( new Promokodiki_Admitad_Deeplink_Service() )->resolved_url( $term->term_id )
		: ( get_term_meta( $term->term_id, '_admitad_shop_manual_affiliate_url', true ) ?: get_term_meta( $term->term_id, '_admitad_shop_deeplink', true ) );
	$affiliate_url    = $affiliate_url ?: $website;

	return array(
		'name'             => $term->name,
		'full_description' => (string) $full_description,
		'about'            => (string) $about,
		'rating'           => $rating,
		'website'          => (string) $website,
		'affiliate_url'    => (string) $affiliate_url,
		'logo_id'          => $logo_id,
		'logo_url'         => $logo_url,
		'logo_alt'         => $term->name,
	);
}

/** Determine whether a term matches the catalogue search. */
function promokodiki_shop_matches_search( WP_Term $term, string $search ): bool {
	$search = mb_strtolower( trim( sanitize_text_field( $search ) ), 'UTF-8' );

	return '' === $search || false !== mb_strpos( mb_strtolower( $term->name, 'UTF-8' ), $search, 0, 'UTF-8' );
}

/** Return shop term IDs that currently have at least one eligible offer. */
function promokodiki_shop_active_term_ids( bool $force = false ): array {
	$cache_key = 'promokodiki_active_shop_ids_v1';
	$cached    = get_transient( $cache_key );
	if ( ! $force && false !== $cached ) {
		return array_map( 'intval', (array) $cached );
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'promocode',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
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
		)
	);

	$term_ids = wp_get_object_terms( $query->posts, 'shops_category', array( 'fields' => 'ids' ) );
	$term_ids = is_wp_error( $term_ids ) ? array() : array_values( array_unique( array_map( 'intval', $term_ids ) ) );
	set_transient( $cache_key, $term_ids, HOUR_IN_SECONDS );

	return $term_ids;
}

/** Flush the catalogue eligibility cache. */
function promokodiki_shop_flush_active_cache(): void {
	delete_transient( 'promokodiki_active_shop_ids_v1' );
}

/** Flush only when eligibility-related promocode metadata changes. */
function promokodiki_shop_flush_on_meta_change( $meta_id, $post_id, string $meta_key ): void {
	unset( $meta_id );
	if ( 'promocode' === get_post_type( $post_id ) && in_array( $meta_key, array( '_promocode_is_active', '_promocode_expiry_date' ), true ) ) {
		promokodiki_shop_flush_active_cache();
	}
}

add_action( 'save_post_promocode', 'promokodiki_shop_flush_active_cache' );
add_action( 'set_object_terms', 'promokodiki_shop_flush_active_cache' );
add_action( 'deleted_post', 'promokodiki_shop_flush_active_cache' );
add_action( 'added_post_meta', 'promokodiki_shop_flush_on_meta_change', 10, 3 );
add_action( 'updated_post_meta', 'promokodiki_shop_flush_on_meta_change', 10, 3 );
add_action( 'deleted_post_meta', 'promokodiki_shop_flush_on_meta_change', 10, 3 );

/** Prevent empty shop archives from entering the search index. */
function promokodiki_shop_robots( array $robots ): array {
	if ( ! is_tax( 'shops_category' ) ) {
		return $robots;
	}

	$term = get_queried_object();
	if ( $term instanceof WP_Term && ! in_array( $term->term_id, promokodiki_shop_active_term_ids(), true ) ) {
		$description = trim( wp_strip_all_tags( promokodiki_shop_profile( $term )['full_description'] ) );
		if ( '' === $description ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
	}

	return $robots;
}
add_filter( 'wp_robots', 'promokodiki_shop_robots' );

/** Load catalogue interactions only where they are used. */
function promokodiki_shop_assets(): void {
	if ( is_post_type_archive( 'shops' ) || is_page_template( 'page-shops.php' ) ) {
		wp_enqueue_script( 'promokodiki-shops', get_template_directory_uri() . '/js/shops.js', array(), '1.0.0', true );
	}
}
add_action( 'wp_enqueue_scripts', 'promokodiki_shop_assets' );
