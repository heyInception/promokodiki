<?php
/**
 * Per-coupon automation lock controls.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays reviewer-safe lock state and reset actions.
 */
final class Promokodiki_Admitad_Promocode_Lock_Metabox {
	/**
	 * Register metabox hook.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_promocode', array( self::class, 'add' ) );
	}

	/**
	 * Add the metabox.
	 */
	public static function add(): void {
		add_meta_box(
			'promokodiki-admitad-locks',
			__( 'Автоматизация Admitad', 'promokodiki-admitad' ),
			array( self::class, 'render' ),
			'promocode',
			'side'
		);
	}

	/**
	 * Render lock state and reset forms.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render( WP_Post $post ): void {
		if ( ! current_user_can( 'review_admitad_mapping' ) ) {
			return;
		}
		$states = array(
			'categories' => Promokodiki_Admitad_Editorial_Locks::category_locked( $post->ID ),
			'content'    => Promokodiki_Admitad_Editorial_Locks::content_locked( $post->ID ),
		);
		foreach ( $states as $scope => $locked ) {
			echo '<p><strong>' . esc_html( 'categories' === $scope ? __( 'Рубрики', 'promokodiki-admitad' ) : __( 'Контент', 'promokodiki-admitad' ) ) . ':</strong> ';
			echo esc_html( $locked ? __( 'защищены редактором', 'promokodiki-admitad' ) : __( 'управляются автоматически', 'promokodiki-admitad' ) ) . '</p>';
			if ( $locked ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="promokodiki_admitad_unlock_post">';
				echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post->ID ) . '">';
				echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '">';
				wp_nonce_field( 'promokodiki_admitad_unlock_' . $post->ID );
				submit_button( __( 'Вернуть автоматике', 'promokodiki-admitad' ), 'secondary small', 'submit', false );
				echo '</form>';
			}
		}
	}
}
