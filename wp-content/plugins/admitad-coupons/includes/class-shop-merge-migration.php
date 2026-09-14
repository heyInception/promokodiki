<?php
/** One-time verified shop merge migrations. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Admitad_Shop_Merge_Migration {
	private const VERSION = 1;
	private const OPTION = 'promokodiki_admitad_shop_merge_version';
	private const REDIRECT_OPTION = 'promokodiki_admitad_shop_redirects';

	/** Merge the confirmed Moulinex duplicate once, only while its identity still matches. */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		$old = self::term_for_campaign( 15488 );
		$new = self::term_for_campaign( 182754 );
		if ( ! $old || ! $new || 'moulinex' !== $old->slug || 'moulinexru' !== $new->slug || ! self::same_moulinex_site( $old, $new ) ) {
			update_option( self::OPTION, self::VERSION, false );
			return;
		}

		$objects = get_objects_in_term( $new->term_id, 'shops_category' );
		if ( is_wp_error( $objects ) ) {
			return;
		}
		foreach ( $objects as $object_id ) {
			$added = wp_set_object_terms( (int) $object_id, array( $old->term_id ), 'shops_category', true );
			if ( is_wp_error( $added ) ) {
				return;
			}
			$removed = wp_remove_object_terms( (int) $object_id, array( $new->term_id ), 'shops_category' );
			if ( is_wp_error( $removed ) ) {
				return;
			}
		}

		foreach ( array( '_admitad_shop_description', '_admitad_shop_source_description', '_admitad_shop_summary', '_admitad_shop_image_url', '_admitad_shop_logo_id', '_admitad_shop_website', '_admitad_shop_synced_at' ) as $key ) {
			$value = get_term_meta( $new->term_id, $key, true );
			if ( '' !== $value ) {
				update_term_meta( $old->term_id, $key, $value );
			}
		}
		update_term_meta( $old->term_id, 'admitad_campaign_id', '182754' );
		update_term_meta( $old->term_id, '_admitad_shop_campaign_name', $new->name );
		update_term_meta( $old->term_id, '_admitad_shop_previous_campaign_ids', array( '15488' ) );
		update_option( self::REDIRECT_OPTION, array( 'moulinexru' => 'moulinex' ), false );
		$deleted = wp_delete_term( $new->term_id, 'shops_category' );
		if ( is_wp_error( $deleted ) || false === $deleted ) {
			return;
		}
		update_option( self::OPTION, self::VERSION, false );
	}

	/** Redirect the retired public taxonomy URL after the source term is deleted. */
	public static function maybe_redirect(): void {
		$path = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		$redirects = (array) get_option( self::REDIRECT_OPTION, array() );
		if ( 'shops-category/moulinexru' === $path && 'moulinex' === ( $redirects['moulinexru'] ?? '' ) ) {
			wp_safe_redirect( home_url( '/shops-category/moulinex/' ), 301 );
			exit;
		}
	}

	private static function term_for_campaign( int $campaign_id ): ?WP_Term {
		$terms = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'number' => 2, 'meta_key' => 'admitad_campaign_id', 'meta_value' => (string) $campaign_id ) );
		return ! is_wp_error( $terms ) && 1 === count( $terms ) ? $terms[0] : null;
	}

	private static function same_moulinex_site( WP_Term $old, WP_Term $new ): bool {
		$hosts = array();
		foreach ( array( $old, $new ) as $term ) {
			$url = (string) get_term_meta( $term->term_id, '_admitad_shop_website', true );
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			$hosts[] = preg_replace( '/^www\./', '', $host );
		}
		return array( 'moulinex.ru', 'moulinex.ru' ) === $hosts;
	}
}
