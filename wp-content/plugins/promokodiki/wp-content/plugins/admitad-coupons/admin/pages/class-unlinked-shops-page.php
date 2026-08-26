<?php
/** Unlinked shops administration page. @package Promokodiki_Admitad */
defined( 'ABSPATH' ) || exit;
final class Promokodiki_Admitad_Unlinked_Shops_Page {
	public function render(): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) ); }
		$notice = '';
		$deeplink_preview = null;
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['deeplink_action'], $_POST['deeplink_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['deeplink_nonce'] ) ), 'promokodiki_admitad_deeplink_batch' ) ) {
			$queue = new Promokodiki_Admitad_Deeplink_Queue();
			if ( 'enqueue' === sanitize_key( wp_unslash( $_POST['deeplink_action'] ) ) ) {
				$notice = sprintf( __( 'В очередь deeplink добавлено магазинов: %d.', 'promokodiki-admitad' ), $queue->enqueue_all() );
			} else {
				$deeplink_preview = $queue->preview_all();
			}
		}
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['shop_link_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shop_link_nonce'] ) ), 'promokodiki_admitad_shop_link' ) ) {
			$result = ( new Promokodiki_Admitad_Shop_Link_Audit() )->assign( absint( $_POST['term_id'] ?? 0 ), absint( $_POST['campaign_id'] ?? 0 ), get_current_user_id() );
			$notice = is_wp_error( $result ) ? $result->get_error_message() : __( 'Магазин связан и обогащён.', 'promokodiki-admitad' );
		}
		$data = ( new Promokodiki_Admitad_Shop_Link_Audit() )->audit( array( 'paged' => absint( $_GET['paged'] ?? 1 ), 'per_page' => 50, 's' => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) ) );
		require ADMITAD_PLUGIN_DIR . 'admin/views/unlinked-shops.php';
	}
}
