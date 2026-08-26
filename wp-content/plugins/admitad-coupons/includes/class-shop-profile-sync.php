<?php
/**
 * Admitad campaign to shop-term enrichment.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores source-owned shop fields on an exactly linked taxonomy term.
 */
final class Promokodiki_Admitad_Shop_Profile_Sync {
	/**
	 * Sanitize campaign HTML through a narrow editorial allowlist.
	 *
	 * @param string $html Source campaign HTML.
	 */
	public static function sanitize_description( string $html ): string {
		return Promokodiki_Admitad_Shop_Content_Service::sanitize( $html );
	}

	/**
	 * Build a customer-facing summary from at most two paragraphs.
	 *
	 * @param string $html  Already cleaned or raw campaign HTML.
	 * @param int    $limit Maximum Unicode characters.
	 */
	public static function summary( string $html, int $limit = 700 ): string {
		$clean = self::sanitize_description( $html );
		if ( '' === $clean || $limit <= 0 ) {
			return '';
		}

		preg_match_all( '/<(h[2-4]|p)\b[^>]*>(.*?)<\/\1>/isu', $clean, $matches, PREG_SET_ORDER );
		$paragraphs   = array();
		$skip_section = false;
		foreach ( $matches as $match ) {
			$tag  = strtolower( (string) $match[1] );
			$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $match[2], true ) ) );
			if ( str_starts_with( $tag, 'h' ) ) {
				$skip_section = self::is_partner_heading( $text );
				continue;
			}
			if ( $skip_section || '' === $text ) {
				continue;
			}
			$paragraphs[] = $text;
			if ( 2 === count( $paragraphs ) ) {
				break;
			}
		}

		if ( array() === $paragraphs ) {
			$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $clean, true ) ) );
			$paragraphs = '' === $plain ? array() : array( $plain );
		}
		return self::truncate_words( implode( "\n\n", $paragraphs ), $limit );
	}

	/**
	 * Synchronize one campaign only to its exact stable-ID term.
	 *
	 * @param array<string, mixed> $campaign Normalized campaign.
	 * @return array{updated:int,unlinked:int,term_id:int}
	 */
	public function sync_campaign( array $campaign ): array {
		$campaign_id = absint( $campaign['external_id'] ?? 0 );
		if ( $campaign_id <= 0 ) {
			return array( 'updated' => 0, 'unlinked' => 1, 'term_id' => 0 );
		}
		$term_ids = get_terms(
			array(
				'taxonomy'   => 'shops_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 2,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Stable campaign ID is the canonical link.
				'meta_query' => array(
					array(
						'key'   => 'admitad_campaign_id',
						'value' => (string) $campaign_id,
					),
				),
			)
		);
		if ( is_wp_error( $term_ids ) || 1 !== count( $term_ids ) ) {
			return array( 'updated' => 0, 'unlinked' => 1, 'term_id' => 0 );
		}

		$term_id     = (int) $term_ids[0];
		$raw         = trim( (string) ( $campaign['raw_description'] ?? '' ) );
		$plain       = trim( (string) ( $campaign['description'] ?? '' ) );
		$description = self::sanitize_description( '' !== $raw ? $raw : wpautop( esc_html( $plain ) ) );
		$values      = array(
			'_admitad_shop_description' => $description,
			'_admitad_shop_summary'     => self::summary( $description ),
			'_admitad_shop_image_url'   => esc_url_raw( (string) ( $campaign['image_url'] ?? '' ) ),
			'_admitad_shop_website'     => esc_url_raw( (string) ( $campaign['site_url'] ?? '' ) ),
		);
		$rating = $campaign['rating'] ?? null;
		if ( is_numeric( $rating ) && is_finite( (float) $rating ) && (float) $rating > 0 && (float) $rating <= 5 ) {
			$values['_admitad_shop_rating'] = (string) (float) $rating;
		}
		foreach ( $values as $key => $value ) {
			if ( '' !== $value ) {
				update_term_meta( $term_id, $key, $value );
			}
		}
		if ( '' !== $description ) {
			update_term_meta( $term_id, '_admitad_shop_source_description', $description );
		}
		Promokodiki_Admitad_Shop_Content_Service::fill_empty_contacts( $term_id, $campaign );
		update_term_meta( $term_id, '_admitad_shop_synced_at', time() );
		update_term_meta( $term_id, '_admitad_shop_audit', array( 'updated_at' => time(), 'user_id' => 0, 'source' => 'admitad' ) );

		return array( 'updated' => 1, 'unlinked' => 0, 'term_id' => $term_id );
	}

	/**
	 * Detect a partner-facing section heading.
	 *
	 * @param string $heading Plain heading text.
	 */
	private static function is_partner_heading( string $heading ): bool {
		return 1 === preg_match( '/^(?:минус[\s-]*слова|вебмастерам|условия программы|рекомендации по продвижению)/iu', $heading );
	}

	/**
	 * Truncate without cutting a Unicode word.
	 *
	 * @param string $text  Plain text.
	 * @param int    $limit Character limit.
	 */
	private static function truncate_words( string $text, int $limit ): string {
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}
		$truncated = mb_substr( $text, 0, $limit + 1 );
		$truncated = preg_replace( '/\s+\S*$/u', '', $truncated );
		return rtrim( (string) $truncated );
	}
}
