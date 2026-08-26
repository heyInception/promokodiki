<?php
/**
 * Deterministic Unicode text normalization.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces stable Russian phrase and token forms without stemming.
 */
final class Promokodiki_Admitad_Text_Normalizer {
	/**
	 * Normalize text for safe phrase comparisons.
	 *
	 * @param string $text Source text.
	 */
	public static function normalize( string $text ): string {
		$text = mb_strtolower( str_replace( array( 'Ё', 'ё' ), 'е', $text ), 'UTF-8' );
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Split normalized text into whole Unicode tokens.
	 *
	 * @param string $text Source text.
	 * @return array<int, string>
	 */
	public static function tokens( string $text ): array {
		$normalized = self::normalize( $text );
		return '' === $normalized ? array() : explode( ' ', $normalized );
	}
}
