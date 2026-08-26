<?php
/**
 * Persist worker candidates as ordinary promocode posts.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Promocode_Repository {
	/** @var callable */
	private $link_resolver;
	/** @var callable */
	private $media_attacher;

	public function __construct( ?callable $link_resolver = null, ?callable $media_attacher = null ) {
		$this->link_resolver = $link_resolver ?: array( new Promokodiki_Telegram_Link_Service(), 'resolve' );
		$this->media_attacher = $media_attacher ?: array( new Promokodiki_Telegram_Media_Service(), 'attach' );
	}

	/**
	 * @param array<string, mixed> $item Validated worker candidate.
	 * @return array{status:string,post_id:int,media_status?:string}|WP_Error
	 */
	public function upsert( array $item ) {
		$validated = $this->validate( $item );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$item       = $validated;
		$source_key = $item['channel'] . ':' . $item['message_id'];
		$post_id    = $this->find_by_source_key( $source_key );

		if ( $post_id > 0 && 'yes' === get_post_meta( $post_id, '_telegram_manual_lock', true ) ) {
			return array( 'status' => 'locked', 'post_id' => $post_id );
		}

		$link = call_user_func( $this->link_resolver, $item['destination_url'] );
		$link = is_array( $link ) ? $link : array();
		$url  = esc_url_raw( (string) ( $link['url'] ?? '' ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			$url = $item['destination_url'];
		}

		$is_expired = $item['expires_at'] <= time();
		$post_data  = array(
			'ID'           => $post_id,
			'post_type'    => 'promocode',
			'post_status'  => $is_expired ? 'draft' : 'publish',
			'post_title'   => $item['title'],
			'post_excerpt' => $item['excerpt'],
			'post_content' => $item['excerpt'],
		);
		if ( '' !== $item['published_at'] ) {
			$published = strtotime( $item['published_at'] );
			if ( false !== $published ) {
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $published );
				$post_data['post_date']     = get_date_from_gmt( $post_data['post_date_gmt'] );
			}
		}

		$saved = $post_id > 0 ? wp_update_post( $post_data, true ) : wp_insert_post( $post_data, true );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$post_id = (int) $saved;
		$slug    = $this->ensure_stable_slug( $post_id );
		if ( is_wp_error( $slug ) ) {
			return $slug;
		}

		$term_id = Promokodiki_Telegram_Activator::ensure_category();
		if ( $term_id > 0 ) {
			wp_set_object_terms( $post_id, array( $term_id ), 'promocode_category', false );
		}

		$expiry_date = wp_date( 'Y-m-d', $item['expires_at'], wp_timezone() );
		$meta        = array(
			'_promocode_code'                  => $item['code'],
			'_promocode_link'                  => $url,
			'_promocode_expiry_date'           => $expiry_date,
			'_promocode_is_active'             => $is_expired ? 'no' : 'yes',
			'_telegram_source_key'             => $source_key,
			'_telegram_channel'                => $item['channel'],
			'_telegram_message_id'             => $item['message_id'],
			'_telegram_source_url'             => $item['source_url'],
			'_telegram_original_url'           => $item['destination_url'],
			'_telegram_raw_text'               => $item['raw_text'],
			'_telegram_published_at'           => $item['published_at'],
			'_telegram_edited_at'              => $item['edited_at'],
			'_telegram_views'                  => $item['views'],
			'_telegram_expires_at'             => $item['expires_at'],
			'_telegram_discount_label'         => $item['discount_label'],
			'_telegram_discount_value'         => $item['discount_value'],
			'_telegram_offer_type'             => $item['offer_type'],
			'_telegram_confidence'             => 'high',
			'_telegram_detected_code_count'    => $item['detected_code_count'],
			'_telegram_affiliate_status'       => sanitize_key( (string) ( $link['status'] ?? 'direct' ) ),
			'_telegram_affiliate_campaign_id'  => absint( $link['campaign_id'] ?? 0 ),
			'_telegram_inactive_reason'         => $is_expired ? 'expired' : '',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		$media_status = (string) get_post_meta( $post_id, '_telegram_media_status', true );
		$media_status = '' !== $media_status ? $media_status : 'absent';
		if ( is_array( $item['media'] ) ) {
			$attachment_id = (int) call_user_func( $this->media_attacher, $post_id, $item['media'] );
			$media_status  = $attachment_id > 0 ? 'attached' : 'failed';
		}
		update_post_meta( $post_id, '_telegram_media_status', $media_status );

		if ( 0 === (int) get_post_meta( $post_id, '_promocode_used_count', true ) ) {
			update_post_meta( $post_id, '_promocode_used_count', 0 );
		}
		if ( '' === get_post_meta( $post_id, '_promocode_likes', true ) ) {
			update_post_meta( $post_id, '_promocode_likes', 0 );
		}
		if ( '' === get_post_meta( $post_id, '_promocode_dislikes', true ) ) {
			update_post_meta( $post_id, '_promocode_dislikes', 0 );
		}

		return array(
			'status'       => $post_data['ID'] > 0 ? 'updated' : 'created',
			'post_id'      => $post_id,
			'media_status' => $media_status,
		);
	}

	public function deactivate_source( string $channel, int $message_id, string $reason ): bool {
		$source_key = $this->sanitize_channel( $channel ) . ':' . max( 0, $message_id );
		$post_id    = $this->find_by_source_key( $source_key );
		if ( $post_id < 1 ) {
			return false;
		}
		if ( 'yes' === get_post_meta( $post_id, '_telegram_manual_lock', true ) ) {
			return true;
		}

		$result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ), true );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		update_post_meta( $post_id, '_promocode_is_active', 'no' );
		update_post_meta( $post_id, '_telegram_inactive_reason', sanitize_key( $reason ) );
		return true;
	}

	private function find_by_source_key( string $source_key ): int {
		$posts = get_posts(
			array(
				'post_type'              => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_telegram_source_key',
				'meta_value'             => $source_key,
			)
		);
		return isset( $posts[0] ) ? (int) $posts[0] : 0;
	}

	/** @return true|WP_Error */
	private function ensure_stable_slug( int $post_id ) {
		$slug = 'yandex-market-' . $post_id;
		if ( $slug === get_post_field( 'post_name', $post_id ) ) {
			return true;
		}

		$updated = wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : true;
	}

	/** @param array<string, mixed> $item @return array<string, mixed>|WP_Error */
	private function validate( array $item ) {
		$count = (int) ( $item['detected_code_count'] ?? 0 );
		if ( $count > 1 ) {
			return new WP_Error( 'telegram_multiple_codes', 'Exactly one promocode is required.' );
		}
		if ( 'high' !== sanitize_key( (string) ( $item['confidence'] ?? '' ) ) ) {
			return new WP_Error( 'telegram_low_confidence', 'Only high-confidence items may be published.' );
		}

		$channel    = $this->sanitize_channel( (string) ( $item['channel'] ?? '' ) );
		$code       = trim( sanitize_text_field( (string) ( $item['code'] ?? '' ) ) );
		$url        = esc_url_raw( (string) ( $item['destination_url'] ?? '' ), array( 'http', 'https' ) );
		$offer_type = sanitize_key( (string) ( $item['offer_type'] ?? ( 1 === $count ? 'promocode' : '' ) ) );
		if ( '' === $channel || (int) ( $item['message_id'] ?? 0 ) < 1 ) {
			return new WP_Error( 'telegram_invalid_source', 'Telegram source is invalid.' );
		}
		if ( 'promocode' === $offer_type && ( 1 !== $count || mb_strlen( $code ) < 4 || mb_strlen( $code ) > 32 || ! preg_match( '/^[\p{L}\p{N}_-]+$/u', $code ) ) ) {
			return new WP_Error( 'telegram_invalid_code', 'Promocode is invalid.' );
		}
		if ( 'cart_discount' === $offer_type && ( 0 !== $count || '' !== $code || (float) ( $item['discount_value'] ?? 0 ) <= 0 ) ) {
			return new WP_Error( 'telegram_invalid_cart_discount', 'Cart discount is invalid.' );
		}
		if ( ! in_array( $offer_type, array( 'promocode', 'cart_discount' ), true ) ) {
			return new WP_Error( 'telegram_invalid_offer_type', 'Telegram offer type is invalid.' );
		}
		if ( '' === $url ) {
			return new WP_Error( 'telegram_missing_destination', 'Merchant destination is required.' );
		}

		return array(
			'channel'             => $channel,
			'message_id'          => (int) $item['message_id'],
			'offer_type'          => $offer_type,
			'detected_code_count' => $count,
			'confidence'          => 'high',
			'title'               => sanitize_text_field( (string) ( $item['title'] ?? '' ) ) ?: 'Промокод из Telegram',
			'excerpt'             => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
			'code'                => $code,
			'destination_url'     => $url,
			'source_url'          => esc_url_raw( (string) ( $item['source_url'] ?? '' ), array( 'http', 'https' ) ),
			'raw_text'            => sanitize_textarea_field( (string) ( $item['raw_text'] ?? '' ) ),
			'published_at'        => sanitize_text_field( (string) ( $item['published_at'] ?? '' ) ),
			'edited_at'           => sanitize_text_field( (string) ( $item['edited_at'] ?? '' ) ),
			'views'               => max( 0, (int) ( $item['views'] ?? 0 ) ),
			'expires_at'          => (int) ( $item['expires_at'] ?? ( time() + 72 * HOUR_IN_SECONDS ) ),
			'discount_label'      => sanitize_text_field( (string) ( $item['discount_label'] ?? '' ) ),
			'discount_value'      => max( 0, (float) ( $item['discount_value'] ?? 0 ) ),
			'media'               => is_array( $item['media'] ?? null ) ? $item['media'] : null,
		);
	}

	private function sanitize_channel( string $channel ): string {
		$channel = ltrim( strtolower( trim( $channel ) ), '@' );
		return preg_match( '/^[a-z0-9_]{5,32}$/', $channel ) ? $channel : '';
	}
}
