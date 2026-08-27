<?php
/** Telegram editorial controls on promocode posts. */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Metabox {
	public static function boot(): void {
		add_action( 'add_meta_boxes_promocode', array( self::class, 'add' ) );
		add_action( 'save_post_promocode', array( self::class, 'save' ), 20, 2 );
	}

	public static function add( WP_Post $post ): void {
		if ( '' === get_post_meta( $post->ID, '_telegram_source_key', true ) ) {
			return;
		}
		add_meta_box( 'promokodiki-telegram-source', 'Telegram', array( self::class, 'render' ), 'promocode', 'side', 'high' );
	}

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'promokodiki_telegram_metabox', 'promokodiki_telegram_metabox_nonce' );
		printf( '<p><label><input type="checkbox" name="_telegram_manual_lock" value="yes" %s> Не перезаписывать worker-ом</label></p>', checked( get_post_meta( $post->ID, '_telegram_manual_lock', true ), 'yes', false ) );
		printf( '<p><label><input type="checkbox" name="_telegram_pinned" value="yes" %s> Закрепить в топе</label></p>', checked( get_post_meta( $post->ID, '_telegram_pinned', true ), 'yes', false ) );
		printf( '<p><strong>Источник:</strong> <code>%s</code></p>', esc_html( (string) get_post_meta( $post->ID, '_telegram_source_key', true ) ) );
		printf( '<p><strong>Просмотры:</strong> %s</p>', esc_html( (string) get_post_meta( $post->ID, '_telegram_views', true ) ) );
		printf( '<p><strong>Партнёрская ссылка:</strong> %s</p>', esc_html( (string) get_post_meta( $post->ID, '_telegram_affiliate_status', true ) ) );
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( 'promocode' !== $post->post_type || '' === get_post_meta( $post_id, '_telegram_source_key', true ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['promokodiki_telegram_metabox_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_telegram_metabox' ) || ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		update_post_meta( $post_id, '_telegram_manual_lock', isset( $_POST['_telegram_manual_lock'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_telegram_pinned', isset( $_POST['_telegram_pinned'] ) ? 'yes' : 'no' );
	}
}
