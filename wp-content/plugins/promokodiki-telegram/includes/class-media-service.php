<?php
/**
 * Store one bounded worker-provided Telegram image.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Media_Service {
	private const MAX_BYTES = 8388608;
	private const TYPES     = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	/** @param array<string, mixed> $media Worker media object. */
	public function attach( int $post_id, array $media ): int {
		if ( $post_id < 1 ) {
			return 0;
		}
		$mime = strtolower( sanitize_mime_type( (string) ( $media['mime_type'] ?? '' ) ) );
		if ( ! isset( self::TYPES[ $mime ] ) ) {
			return 0;
		}
		$data = base64_decode( (string) ( $media['data'] ?? '' ), true );
		if ( false === $data || '' === $data || strlen( $data ) > self::MAX_BYTES ) {
			return 0;
		}

		$hash     = hash( 'sha256', $data );
		$existing = (int) get_post_thumbnail_id( $post_id );
		if ( $existing > 0 && hash_equals( (string) get_post_meta( $post_id, '_telegram_media_hash', true ), $hash ) ) {
			return $existing;
		}

		$base     = sanitize_file_name( pathinfo( (string) ( $media['filename'] ?? 'telegram-media' ), PATHINFO_FILENAME ) );
		$filename = ( $base ?: 'telegram-media' ) . '-' . substr( $hash, 0, 12 ) . '.' . self::TYPES[ $mime ];
		$upload   = wp_upload_bits( $filename, null, $data );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_text_field( $base ?: 'Telegram media' ),
				'post_status'    => 'inherit',
			),
			(string) $upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( (string) $upload['file'] );
			return 0;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, (string) $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		}
		set_post_thumbnail( $post_id, (int) $attachment_id );
		update_post_meta( $post_id, '_telegram_media_hash', $hash );

		if ( $existing > 0 && $existing !== (int) $attachment_id && (int) get_post_field( 'post_parent', $existing ) === $post_id ) {
			wp_delete_attachment( $existing, true );
		}
		return (int) $attachment_id;
	}
}
