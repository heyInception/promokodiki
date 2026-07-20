<?php
/** Secure Admitad connection settings. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=promocode',
			'Admitad',
			'Admitad',
			'manage_options',
			'admitad-settings',
			'admitad_render_settings_page'
		);
	}
);

function admitad_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = '';
	if ( isset( $_POST['admitad_save_settings'] ) ) {
		check_admin_referer( 'admitad_save_settings' );
		update_option( 'promokodiki_admitad_client_id', sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ), false );
		update_option( 'promokodiki_admitad_website_id', sanitize_text_field( wp_unslash( $_POST['website_id'] ?? '' ) ), false );
		$secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );
		if ( '' !== $secret ) {
			update_option( 'promokodiki_admitad_client_secret', $secret, false );
		}
		admitad_clear_cached_token();
		$message = '<div class="notice notice-success"><p>Настройки сохранены.</p></div>';
	}
	if ( isset( $_POST['admitad_refresh_token'] ) ) {
		check_admin_referer( 'admitad_refresh_token' );
		$token   = get_admitad_token( true );
		$message = is_wp_error( $token )
			? '<div class="notice notice-error"><p>' . esc_html( $token->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-success"><p>Соединение успешно. Токен обновлён.</p></div>';
	}

	$constant_mode = defined( 'PROMOKODIKI_ADMITAD_CLIENT_ID' ) || defined( 'PROMOKODIKI_ADMITAD_CLIENT_SECRET' );
	echo '<div class="wrap"><h1>Admitad</h1>' . wp_kses_post( $message );
	if ( $constant_mode ) {
		echo '<p>Credentials заданы константами в wp-config.php. Секреты в интерфейсе не отображаются.</p>';
	} else {
		echo '<form method="post">';
		wp_nonce_field( 'admitad_save_settings' );
		echo '<table class="form-table">';
		echo '<tr><th><label for="client_id">Client ID</label></th><td><input class="regular-text" id="client_id" name="client_id" value="' . esc_attr( get_option( 'promokodiki_admitad_client_id', '' ) ) . '"></td></tr>';
		echo '<tr><th><label for="client_secret">Client secret</label></th><td><input class="regular-text" type="password" id="client_secret" name="client_secret" autocomplete="new-password" placeholder="Оставьте пустым, чтобы не менять"></td></tr>';
		echo '<tr><th><label for="website_id">Website ID</label></th><td><input class="regular-text" id="website_id" name="website_id" value="' . esc_attr( get_option( 'promokodiki_admitad_website_id', '' ) ) . '"></td></tr>';
		echo '</table><p><button class="button button-primary" name="admitad_save_settings" value="1">Сохранить</button></p></form>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'admitad_refresh_token' );
	echo '<p><button class="button" name="admitad_refresh_token" value="1">Проверить соединение</button></p></form>';
	$expires = (int) get_option( 'admitad_token_expires', 0 );
	echo '<p>Кэшированный токен: ' . ( $expires > time() ? 'действует до ' . esc_html( wp_date( 'Y-m-d H:i:s', $expires ) ) : 'отсутствует или истёк' ) . '.</p></div>';
}

