<?php
/**
 * Plugin lifecycle operations.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Activator {
	public static function activate(): void {
		if ( ! taxonomy_exists( 'promocode_category' ) && function_exists( 'admitad_register_content_types' ) ) {
			admitad_register_content_types();
		}

		Promokodiki_Telegram_Config::ensure_defaults();
		self::ensure_category();
		self::maybe_upgrade();

		if ( ! wp_next_scheduled( 'promokodiki_telegram_expire' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'promokodiki_telegram_expire' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'promokodiki_telegram_expire' );
	}

	public static function ensure_category(): int {
		$term_id = Promokodiki_Telegram_Config::category_term_id();
		if ( $term_id > 0 || ! taxonomy_exists( 'promocode_category' ) ) {
			return $term_id;
		}

		$created = wp_insert_term(
			'Промокоды из Telegram',
			'promocode_category',
			array( 'slug' => Promokodiki_Telegram_Config::category_slug() )
		);

		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	public static function maybe_upgrade(): void {
		if ( PROMOKODIKI_TELEGRAM_VERSION === get_option( 'promokodiki_telegram_db_version', '' ) ) {
			return;
		}

		$post_ids = get_posts(
			array(
				'post_type'                    => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'                  => 'any',
				'posts_per_page'               => -1,
				'fields'                       => 'ids',
				'no_found_rows'                => true,
				'suppress_filters'             => true,
				'update_post_meta_cache'       => false,
				'update_post_term_cache'       => false,
				'meta_key'                     => '_telegram_source_key',
			)
		);

		foreach ( $post_ids as $post_id ) {
			$post_id   = (int) $post_id;
			$post_data = array(
				'ID'        => $post_id,
				'post_name' => 'yandex-market-' . $post_id,
			);
			$code       = (string) get_post_meta( $post_id, '_promocode_code', true );
			$offer_type = (string) get_post_meta( $post_id, '_telegram_offer_type', true );
			$offer_type = $offer_type ?: ( '' !== $code ? 'promocode' : 'cart_discount' );
			update_post_meta( $post_id, '_telegram_offer_type', $offer_type );
			if ( 'yes' !== get_post_meta( $post_id, '_telegram_manual_lock', true ) ) {
				$post_data['post_title'] = self::telegram_title(
					(string) get_post_meta( $post_id, '_telegram_raw_text', true ),
					$offer_type,
					(int) get_post_meta( $post_id, '_telegram_discount_value', true )
				);
			}
			wp_update_post(
				$post_data
			);
		}

		$channels = Promokodiki_Telegram_Config::channels();
		foreach ( $channels as &$channel ) {
			$channel['last_message_id'] = 0;
		}
		unset( $channel );
		Promokodiki_Telegram_Config::save_channels( $channels );

		update_option( 'promokodiki_telegram_db_version', PROMOKODIKI_TELEGRAM_VERSION, false );
	}

	private static function telegram_title( string $raw_text, string $offer_type, int $discount ): string {
		$candidate = '';
		$lines     = preg_split( '/\R/u', $raw_text ) ?: array();
		foreach ( $lines as $line ) {
			$line = preg_replace( '~https?://\S+~u', '', $line ) ?? '';
			if ( preg_match( '/(?:скидка\s*)?-?\s*\d{1,3}\s*%\s*в\s+корзине|(?:промо\s*[-–—]?\s*код|промокод|промо)\b/iu', $line, $marker, PREG_OFFSET_CAPTURE ) ) {
				$line = substr( $line, 0, (int) $marker[0][1] );
			}
			$line = preg_replace( '/[*_~`\[\]()]+/u', ' ', $line ) ?? '';
			$line = preg_replace( '/\s+/u', ' ', trim( $line ) ) ?? '';
			$line = preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $line ) ?? '';
			if ( mb_strlen( $line ) < 4 || preg_match( '/^(?:(?:виу|вау|срочно|шок|огонь)\s*)+$|^(?:разбира\w*|налета\w*|успева\w*)!?$/iu', $line ) ) {
				continue;
			}
			$candidate = rtrim( mb_substr( $line, 0, 90 ), " .,;:!—-" );
			break;
		}

		if ( 'cart_discount' === $offer_type ) {
			$benefit = sprintf( 'скидка %d%% в корзине', $discount );
			$fallback = sprintf( 'Скидка %d%% в корзине на Яндекс Маркете', $discount );
		} elseif ( $discount > 0 ) {
			$benefit = sprintf( 'скидка %d%% по промокоду', $discount );
			$fallback = sprintf( 'Скидка %d%% по промокоду на Яндекс Маркете', $discount );
		} else {
			$benefit = 'предложение по промокоду';
			$fallback = 'Промокод на Яндекс Маркете';
		}

		return $candidate ? $candidate . ' — ' . $benefit : $fallback;
	}
}
