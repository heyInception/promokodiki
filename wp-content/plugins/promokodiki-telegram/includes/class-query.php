<?php
/**
 * Telegram top queries, expiry, and collection visibility.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Query {
	/** @return int[] */
	public static function top_ids( int $limit ): array {
		$limit   = max( 4, min( 20, $limit ) );
		$term_id = Promokodiki_Telegram_Config::category_term_id();
		if ( $term_id < 1 ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'              => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array( 'taxonomy' => 'promocode_category', 'field' => 'term_id', 'terms' => array( $term_id ) ),
				),
				'meta_query'             => array(
					array( 'key' => '_promocode_is_active', 'value' => 'yes' ),
					array( 'key' => '_telegram_expires_at', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
				),
			)
		);
		$ids = array_map( 'intval', $ids );
		usort(
			$ids,
			static function ( int $left, int $right ): int {
				$score = Promokodiki_Telegram_Ranking::score( $right ) <=> Promokodiki_Telegram_Ranking::score( $left );
				return 0 !== $score ? $score : $right <=> $left;
			}
		);
		return array_slice( $ids, 0, $limit );
	}

	public static function expire_posts(): void {
		$ids = get_posts(
			array(
				'post_type'      => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array( 'key' => '_telegram_source_key', 'compare' => 'EXISTS' ),
					array( 'key' => '_telegram_expires_at', 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC' ),
				),
			)
		);
		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( 'yes' === get_post_meta( $post_id, '_telegram_manual_lock', true ) ) {
				continue;
			}
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			update_post_meta( $post_id, '_promocode_is_active', 'no' );
			update_post_meta( $post_id, '_telegram_inactive_reason', 'expired' );
		}
	}

	public static function exclude_from_general_query( WP_Query $query ): void {
		if ( is_admin() || $query->is_search() || $query->is_singular() || $query->is_tax( 'promocode_category', Promokodiki_Telegram_Config::category_slug() ) || $query->get( 'promokodiki_include_telegram' ) ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		if ( 'promocode' !== $post_type && ! ( is_array( $post_type ) && in_array( 'promocode', $post_type, true ) ) ) {
			return;
		}
		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array( 'key' => '_telegram_source_key', 'compare' => 'NOT EXISTS' );
		$query->set( 'meta_query', $meta_query );
	}
}
