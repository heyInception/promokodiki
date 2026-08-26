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
		if ( false === has_action( 'set_object_terms', array( self::class, 'on_set_terms' ) ) ) {
			add_action( 'set_object_terms', array( self::class, 'on_set_terms' ), 10, 6 );
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

	/**
	 * Record an editorial category lock after manual taxonomy changes.
	 *
	 * @param int          $object_id   Post ID.
	 * @param array|string $terms       Requested terms.
	 * @param array<int>   $term_tt_ids Term-taxonomy IDs.
	 * @param string       $taxonomy    Taxonomy.
	 * @param bool         $append      Append mode.
	 * @param array<int>   $old_tt_ids  Previous term-taxonomy IDs.
	 */
	public static function on_set_terms( int $object_id, $terms, array $term_tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		unset( $terms, $term_tt_ids, $append, $old_tt_ids );
		if (
			'promocode_category' !== $taxonomy
			|| 'promocode' !== get_post_type( $object_id )
			|| Promokodiki_Admitad_Import_Context::active()
		) {
			return;
		}
		$term_ids = array_map( 'intval', wp_get_object_terms( $object_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
		update_post_meta( $object_id, '_admitad_category_locked', 'yes' );
		update_post_meta( $object_id, '_admitad_locked_term_ids', $term_ids );
	}

	/**
	 * Whether categories are protected from automated assignment.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function category_locked( int $post_id ): bool {
		return 'yes' === get_post_meta( $post_id, '_admitad_category_locked', true );
	}
}
