<?php
/** Telegram administration contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_Admin' ) || ! class_exists( 'Promokodiki_Telegram_Metabox' ) ) {
	throw new RuntimeException( 'Telegram administration is not implemented.' );
}

$original_settings = get_option( 'promokodiki_telegram_settings', null );
$original_channels = get_option( 'promokodiki_telegram_channels', null );
$user_id           = wp_insert_user( array( 'user_login' => 'telegram_admin_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
$post_id           = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'draft', 'post_title' => 'Telegram admin test' ) );

try {
	wp_set_current_user( (int) $user_id );
	Promokodiki_Telegram_Test_Harness::run(
		'admin save constrains count and sanitizes channel CRUD',
		static function (): void {
			$nonce  = wp_create_nonce( 'promokodiki_telegram_settings' );
			$result = Promokodiki_Telegram_Admin::save(
				array(
					'card_count' => 99,
					'channels'            => array( '@tranzhiraru' => '1', 'bad name' => '1' ),
					'new_channel'         => 'New_Channel',
					'new_channel_enabled' => '1',
				),
				$nonce
			);
			Promokodiki_Telegram_Test_Harness::assert_true( true === $result );
			Promokodiki_Telegram_Test_Harness::assert_same( 20, Promokodiki_Telegram_Config::card_count() );
			$channels = Promokodiki_Telegram_Config::channels();
			Promokodiki_Telegram_Test_Harness::assert_true( isset( $channels['tranzhiraru'], $channels['new_channel'] ) );
			Promokodiki_Telegram_Test_Harness::assert_true( ! isset( $channels['bad name'] ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'invalid nonce rejects settings changes',
		static function (): void {
			$result = Promokodiki_Telegram_Admin::save( array( 'card_count' => 4, 'channels' => array() ), 'invalid' );
			Promokodiki_Telegram_Test_Harness::assert_true( is_wp_error( $result ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'telegram_invalid_nonce', $result->get_error_code() );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'removed channel is not restored during init',
		static function (): void {
			Promokodiki_Telegram_Config::save_channels(
				array(
					'tranzhiraru' => array( 'username' => 'tranzhiraru', 'enabled' => true ),
				)
			);
			$result = Promokodiki_Telegram_Admin::save(
				array(
					'card_count'      => 4,
					'channels'        => array( 'tranzhiraru' => '1' ),
					'remove_channels' => array( 'tranzhiraru' ),
				),
				wp_create_nonce( 'promokodiki_telegram_settings' )
			);
			Promokodiki_Telegram_Test_Harness::assert_true( true === $result );
			Promokodiki_Telegram_Test_Harness::assert_true( ! isset( Promokodiki_Telegram_Config::channels()['tranzhiraru'] ) );
			Promokodiki_Telegram_Test_Harness::assert_true( false === has_action( 'init', array( 'Promokodiki_Telegram_Config', 'ensure_defaults' ) ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'metabox persists editorial lock and pin',
		static function () use ( $post_id ): void {
			update_post_meta( $post_id, '_telegram_source_key', 'tranzhiraru:1' );
			$_POST['promokodiki_telegram_metabox_nonce'] = wp_create_nonce( 'promokodiki_telegram_metabox' );
			$_POST['_telegram_manual_lock']             = 'yes';
			$_POST['_telegram_pinned']                  = 'yes';
			Promokodiki_Telegram_Metabox::save( $post_id, get_post( $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_telegram_manual_lock', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_telegram_pinned', true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	unset( $_POST['promokodiki_telegram_metabox_nonce'], $_POST['_telegram_manual_lock'], $_POST['_telegram_pinned'] );
	wp_delete_post( $post_id, true );
	wp_delete_user( (int) $user_id );
	if ( null === $original_settings ) { delete_option( 'promokodiki_telegram_settings' ); } else { update_option( 'promokodiki_telegram_settings', $original_settings, false ); }
	if ( null === $original_channels ) { delete_option( 'promokodiki_telegram_channels' ); } else { update_option( 'promokodiki_telegram_channels', $original_channels, false ); }
}
