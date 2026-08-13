<?php
/**
 * Shop content sanitization and conservative contact extraction.
 *
 * @package Promokodiki_Admitad
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Admitad_Shop_Content_Service {
	/** Sanitize customer-facing HTML and remove links with their labels. */
	public static function sanitize( string $html ): string {
		$html = preg_replace( '#<(script|style|iframe|form|button|select|textarea|picture|svg)\b[^>]*>.*?</\1>#isu', '', $html );
		$html = preg_replace( '#<a\b[^>]*>.*?</a>#isu', '', (string) $html );
		$html = preg_replace( '#<(?:img|input|object|embed|video|audio|source)\b[^>]*\/?>#isu', '', (string) $html );
		$allowed = array(
			'p' => array(), 'br' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(), 'strong' => array(), 'em' => array(),
		);
		return trim( wp_kses( (string) $html, $allowed ) );
	}

	/** Extract only unique, unambiguous phone and email candidates. */
	public static function extract_contacts( string $html ): array {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $email_matches );
		preg_match_all( '/(?:\+7|8)\s*(?:\(\s*\d{3}\s*\)|\d{3})[\s\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}/u', $text, $phone_matches );
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $email_matches[0] ?? array() ) ) ) );
		$phones = array_values( array_unique( array_map( 'trim', $phone_matches[0] ?? array() ) ) );
		return array(
			'phone'           => 1 === count( $phones ) ? $phones[0] : '',
			'email'           => 1 === count( $emails ) ? $emails[0] : '',
			'phone_candidates' => $phones,
			'email_candidates' => $emails,
		);
	}

	/** Fill editable contact term-meta only when currently empty. */
	public static function fill_empty_contacts( int $term_id, array $campaign ): array {
		$raw      = (string) ( $campaign['raw_description'] ?? $campaign['description'] ?? '' );
		$contacts = self::extract_contacts( $raw );
		$values   = array(
			'website' => esc_url_raw( (string) ( $campaign['site_url'] ?? '' ) ),
			'phone'   => $contacts['phone'],
			'email'   => $contacts['email'],
		);
		$updated = array( 'website' => false, 'phone' => false, 'email' => false );
		foreach ( $values as $field => $value ) {
			$key = 'shop_' . $field;
			if ( '' !== $value && '' === trim( (string) get_term_meta( $term_id, $key, true ) ) ) {
				update_term_meta( $term_id, $key, $value );
				$updated[ $field ] = true;
			}
		}
		update_term_meta( $term_id, '_admitad_shop_contact_hints', array( 'phones' => $contacts['phone_candidates'], 'emails' => $contacts['email_candidates'] ) );
		return $updated;
	}
}
