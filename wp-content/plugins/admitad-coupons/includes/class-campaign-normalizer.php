<?php
/**
 * Admitad campaign normalizer.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces deterministic campaign snapshots.
 */
final class Promokodiki_Admitad_Campaign_Normalizer {
	/**
	 * Normalize one raw campaign.
	 *
	 * @param array<string, mixed> $raw Raw API campaign.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $raw ): array {
		$canonical                 = array(
			'external_id'   => sanitize_text_field( (string) ( $raw['id'] ?? '' ) ),
			'name'          => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'source_status' => sanitize_key( (string) ( $raw['status'] ?? '' ) ),
			'site_url'      => esc_url_raw( (string) ( $raw['site_url'] ?? '' ) ),
			'categories'    => self::categories( $raw['categories'] ?? array() ),
		);
		$canonical['payload_hash'] = hash(
			'sha256',
			wp_json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		return $canonical;
	}

	/**
	 * Normalize campaign category hierarchy.
	 *
	 * @param mixed $items Raw categories.
	 * @return array<int, array{id:int,name:string,parent_id:int,parent_name:string}>
	 */
	private static function categories( $items ): array {
		$normalized = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			$parent       = is_array( $item['parent'] ?? null ) ? $item['parent'] : array();
			$normalized[] = array(
				'id'          => absint( $item['id'] ),
				'name'        => trim( sanitize_text_field( (string) ( $item['name'] ?? '' ) ) ),
				'parent_id'   => absint( $item['parent_id'] ?? ( $parent['id'] ?? 0 ) ),
				'parent_name' => trim( sanitize_text_field( (string) ( $parent['name'] ?? '' ) ) ),
			);
		}
		usort( $normalized, static fn( array $left, array $right ): int => $left['id'] <=> $right['id'] );
		return $normalized;
	}
}
