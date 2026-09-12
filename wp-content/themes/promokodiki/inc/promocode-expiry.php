<?php
/**
 * Shared promocode expiry rules used by theme templates.
 *
 * @package promokodiki
 */

defined( 'ABSPATH' ) || defined( 'PROMOKODIKI_EXPIRY_TEST' ) || exit;

/**
 * Resolve the lifecycle state for a calendar-date expiry.
 *
 * @param string $expiry_date Date in Y-m-d format, interpreted in the site timezone.
 * @param string $today Current site date in Y-m-d format.
 * @return string active, undated, grace or hidden.
 */
function promokodiki_promocode_expiry_state( string $expiry_date, string $today ): string {
	$expiry_date = promokodiki_normalize_promocode_expiry_date( $expiry_date );

	if ( '' === $expiry_date ) {
		return 'undated';
	}

	if ( $expiry_date >= $today ) {
		return 'active';
	}

	$expiry  = DateTimeImmutable::createFromFormat( '!Y-m-d', $expiry_date );
	$current = DateTimeImmutable::createFromFormat( '!Y-m-d', $today );
	if ( ! $expiry || ! $current ) {
		return 'hidden';
	}

	return $expiry->modify( '+7 days' ) >= $current ? 'grace' : 'hidden';
}

/** Extract and validate the calendar date from a date or datetime value. */
function promokodiki_normalize_promocode_expiry_date( string $expiry_date ): string {
	if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})/', trim( $expiry_date ), $matches ) ) {
		return '';
	}

	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $matches[1] );
	return $date && $date->format( 'Y-m-d' ) === $matches[1] ? $matches[1] : '';
}

/** Return whether an offer belongs in a general listing. */
function promokodiki_promocode_is_listing_visible( string $expiry_date, string $today ): bool {
	return 'hidden' !== promokodiki_promocode_expiry_state( $expiry_date, $today );
}

/** Return whether an offer can be promoted in recommendation slots. */
function promokodiki_promocode_is_recommendable( string $expiry_date, string $today ): bool {
	return in_array( promokodiki_promocode_expiry_state( $expiry_date, $today ), array( 'active', 'undated' ), true );
}

/** Format the expiry consistently for cards and dialogs. */
function promokodiki_promocode_expiry_label( string $expiry_date ): string {
	$expiry_date = promokodiki_normalize_promocode_expiry_date( $expiry_date );

	if ( '' === $expiry_date ) {
		return 'Срок не указан';
	}

	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $expiry_date );
	return $date ? $date->format( 'd.m.Y' ) : 'Срок не указан';
}

/** Build the expiry portion of a general-listing meta query. */
function promokodiki_promocode_listing_expiry_meta_query( string $today ): array {
	$cutoff = DateTimeImmutable::createFromFormat( '!Y-m-d', $today )->modify( '-7 days' )->format( 'Y-m-d' );
	return array(
		'relation' => 'OR',
		array( 'key' => '_promocode_expiry_date', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_promocode_expiry_date', 'value' => '' ),
		array( 'key' => '_promocode_expiry_date', 'value' => $cutoff, 'compare' => '>=', 'type' => 'DATE' ),
	);
}

/** Build the expiry portion of a recommendation meta query. */
function promokodiki_promocode_recommendation_expiry_meta_query( string $today ): array {
	return array(
		'relation' => 'OR',
		array( 'key' => '_promocode_expiry_date', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_promocode_expiry_date', 'value' => '' ),
		array( 'key' => '_promocode_expiry_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
	);
}

/** Decide whether an old expired detail page should leave the search index. */
function promokodiki_promocode_should_noindex( string $state, bool $has_active_alternatives ): bool {
	return 'hidden' === $state && ! $has_active_alternatives;
}

/** Find active alternatives from the same shop as a promocode. */
function promokodiki_promocode_active_shop_alternatives( int $post_id, int $limit = 4 ): array {
	$term_ids = wp_get_post_terms( $post_id, 'shops_category', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) || ! $term_ids ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'      => 'promocode',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
			'fields'         => 'ids',
			'tax_query'      => array(
				array( 'taxonomy' => 'shops_category', 'field' => 'term_id', 'terms' => $term_ids ),
			),
			'meta_query'     => promokodiki_promocode_recommendation_expiry_meta_query( current_time( 'Y-m-d' ) ),
		)
	);
}

/** Add noindex to old expired offers that have no active shop alternative. */
function promokodiki_expired_promocode_robots( array $robots ): array {
	if ( ! is_singular( 'promocode' ) ) {
		return $robots;
	}

	$post_id = get_queried_object_id();
	$state   = promokodiki_promocode_expiry_state( (string) get_post_meta( $post_id, '_promocode_expiry_date', true ), current_time( 'Y-m-d' ) );
	if ( promokodiki_promocode_should_noindex( $state, (bool) promokodiki_promocode_active_shop_alternatives( $post_id, 1 ) ) ) {
		$robots['noindex'] = true;
		unset( $robots['nofollow'] );
	}

	return $robots;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'wp_robots', 'promokodiki_expired_promocode_robots' );
}
