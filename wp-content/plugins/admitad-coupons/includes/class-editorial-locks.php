<?php
/**
 * Editorial lock detection.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves manually edited coupon content.
 */
final class Promokodiki_Admitad_Editorial_Locks {
	/**
	 * Register lock detection.
	 */
	public static function register(): void {
		if ( false === has_action( 'save_post_promocode', array( self::class, 'on_save' ) ) ) {
			add_action( 'save_post_promocode', array( self::class, 'on_save' ), 10, 3 );
		}
	}

	/**
	 * Record a manual content lock.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function on_save( int $post_id, WP_Post $post, bool $update ): void {
		unset( $post, $update );
		if ( Promokodiki_Admitad_Import_Context::active() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_admitad_content_locked', 'yes' );
	}

	/**
	 * Whether content is protected from importer overwrites.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function content_locked( int $post_id ): bool {
		return 'yes' === get_post_meta( $post_id, '_admitad_content_locked', true );
	}
}
