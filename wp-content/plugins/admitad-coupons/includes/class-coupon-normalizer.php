<?php
/**
 * Admitad coupon normalizer.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces deterministic coupon payloads.
 */
final class Promokodiki_Admitad_Coupon_Normalizer {
	/**
	 * Normalize one raw coupon.
	 *
	 * @param array<string, mixed> $raw Raw API coupon.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $raw ): array {
		$campaign  = is_array( $raw['campaign'] ?? null ) ? $raw['campaign'] : array();
		$canonical = array(
			'external_id'        => sanitize_text_field( (string) ( $raw['id'] ?? '' ) ),
			'source_status'      => sanitize_key( (string) ( $raw['status'] ?? '' ) ),
			'title'              => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'description'        => wp_kses_post( (string) ( $raw['description'] ?? '' ) ),
			'short_name'         => sanitize_text_field( (string) ( $raw['short_name'] ?? '' ) ),
			'campaign'           => array(
				'id'       => sanitize_text_field( (string) ( $campaign['id'] ?? '' ) ),
				'name'     => sanitize_text_field( (string) ( $campaign['name'] ?? '' ) ),
				'site_url' => esc_url_raw( (string) ( $campaign['site_url'] ?? '' ) ),
			),
			'categories'         => self::references( $raw['categories'] ?? array() ),
			'types'              => self::references( $raw['types'] ?? array() ),
			'species'            => sanitize_key( (string) ( $raw['species'] ?? '' ) ),
			'promocode'          => sanitize_text_field( (string) ( $raw['promocode'] ?? '' ) ),
			'goto_link'          => esc_url_raw( (string) ( $raw['goto_link'] ?? '' ) ),
			'frameset_link'      => esc_url_raw( (string) ( $raw['frameset_link'] ?? '' ) ),
			'image_url'          => esc_url_raw( (string) ( $raw['image'] ?? '' ) ),
			'date_start'         => sanitize_text_field( (string) ( $raw['date_start'] ?? '' ) ),
			'date_end'           => sanitize_text_field( (string) ( $raw['date_end'] ?? '' ) ),
			'discount'           => sanitize_text_field( (string) ( $raw['discount'] ?? '' ) ),
			'language'           => sanitize_key( (string) ( $raw['language'] ?? '' ) ),
			'regions'            => self::regions( $raw['regions'] ?? array() ),
			'has_affiliate_link' => ! empty( $raw['has_affiliate_link'] ),
		);

		$canonical['payload_hash'] = hash(
			'sha256',
			wp_json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		return $canonical;
	}

	/**
	 * Normalize ID/name references.
	 *
	 * @param mixed $items Raw references.
	 * @return array<int, array{id:int,name:string}>
	 */
	private static function references( $items ): array {
		$normalized = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			$normalized[] = array(
				'id'   => absint( $item['id'] ),
				'name' => trim( sanitize_text_field( (string) ( $item['name'] ?? '' ) ) ),
			);
		}
		usort( $normalized, static fn( array $left, array $right ): int => $left['id'] <=> $right['id'] );
		return $normalized;
	}

	/**
	 * Normalize region codes.
	 *
	 * @param mixed $regions Raw regions.
	 * @return string[]
	 */
	private static function regions( $regions ): array {
		$normalized = array_map( 'sanitize_key', is_array( $regions ) ? $regions : array() );
		$normalized = array_values( array_unique( array_filter( $normalized ) ) );
		sort( $normalized );
		return $normalized;
	}
}
