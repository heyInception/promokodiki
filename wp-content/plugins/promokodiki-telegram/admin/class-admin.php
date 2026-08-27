<?php
/** Telegram importer administration. */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Admin {
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_promokodiki_telegram_save', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_promokodiki_telegram_test_sync', array( self::class, 'handle_test_sync' ) );
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=promocode',
			'Telegram промокоды',
			'Telegram',
			'manage_options',
			'promokodiki-telegram',
			array( self::class, 'render' )
		);
	}

	/** @param array<string, mixed> $data Submitted settings. @return true|WP_Error */
	public static function save( array $data, string $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'telegram_forbidden', 'Недостаточно прав.' );
		}
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_telegram_settings' ) ) {
			return new WP_Error( 'telegram_invalid_nonce', 'Проверка формы не пройдена.' );
		}

		Promokodiki_Telegram_Config::save_settings( array( 'card_count' => (int) ( $data['card_count'] ?? 4 ) ) );
		$current  = Promokodiki_Telegram_Config::channels();
		$channels = array();
		foreach ( is_array( $data['channels'] ?? null ) ? $data['channels'] : array() as $username => $enabled ) {
			$username = ltrim( strtolower( trim( (string) $username ) ), '@' );
			if ( ! preg_match( '/^[a-z0-9_]{5,32}$/', $username ) ) {
				continue;
			}
			$row             = is_array( $current[ $username ] ?? null ) ? $current[ $username ] : array();
			$row['username'] = $username;
			$row['enabled']  = ! empty( $enabled );
			$channels[ $username ] = $row;
		}
		$new_channel = ltrim( strtolower( trim( (string) ( $data['new_channel'] ?? '' ) ) ), '@' );
		if ( preg_match( '/^[a-z0-9_]{5,32}$/', $new_channel ) ) {
			$channels[ $new_channel ] = array(
				'username' => $new_channel,
				'enabled'  => ! empty( $data['new_channel_enabled'] ),
			);
		}
		foreach ( is_array( $data['remove_channels'] ?? null ) ? $data['remove_channels'] : array() as $username ) {
			unset( $channels[ ltrim( strtolower( trim( (string) $username ) ), '@' ) ] );
		}
		Promokodiki_Telegram_Config::save_channels( $channels );
		return true;
	}

	public static function handle_save(): void {
		$result = self::save( wp_unslash( $_POST ), sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ) );
		$status = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( 'telegram_status', $status, admin_url( 'edit.php?post_type=promocode&page=promokodiki-telegram' ) ) );
		exit;
	}

	public static function handle_test_sync(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'promokodiki_telegram_test_sync' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-telegram' ) );
		}
		update_option( 'promokodiki_telegram_sync_requested_at', time(), false );
		wp_safe_redirect( add_query_arg( 'telegram_status', 'sync_requested', admin_url( 'edit.php?post_type=promocode&page=promokodiki-telegram' ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Promokodiki_Telegram_Config::settings();
		$channels = Promokodiki_Telegram_Config::channels();
		?>
		<div class="wrap"><h1>Telegram промокоды</h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="promokodiki_telegram_save">
			<?php wp_nonce_field( 'promokodiki_telegram_settings' ); ?>
			<table class="form-table"><tr><th><label for="telegram-card-count">Карточек в блоке</label></th><td><input id="telegram-card-count" type="number" min="4" max="20" name="card_count" value="<?php echo esc_attr( (string) $settings['card_count'] ); ?>"></td></tr>
			<tr><th>Секрет worker</th><td><code><?php echo esc_html( str_repeat( '•', 16 ) . substr( (string) $settings['secret'], -4 ) ); ?></code></td></tr></table>
			<h2>Каналы</h2><table class="widefat striped"><thead><tr><th>Канал</th><th>Включён</th><th>Последняя синхронизация</th><th>Статус</th><th>Импортировано / пропущено</th><th>Удалить</th></tr></thead><tbody>
			<?php foreach ( $channels as $channel ) : ?><tr><td><input name="channels[<?php echo esc_attr( (string) $channel['username'] ); ?>]" type="hidden" value="0"><label><code>@<?php echo esc_html( (string) $channel['username'] ); ?></code></label></td><td><input name="channels[<?php echo esc_attr( (string) $channel['username'] ); ?>]" type="checkbox" value="1" <?php checked( ! empty( $channel['enabled'] ) ); ?>></td><td><?php echo esc_html( (string) ( $channel['last_synced_at'] ?: '—' ) ); ?></td><td><?php echo esc_html( (string) $channel['last_status'] ); ?></td><td><?php echo esc_html( (string) $channel['imported_count'] . ' / ' . (string) $channel['skipped_count'] ); ?></td><td><input type="checkbox" name="remove_channels[]" value="<?php echo esc_attr( (string) $channel['username'] ); ?>"></td></tr><?php endforeach; ?>
			<tr><td><input name="new_channel" placeholder="username" pattern="[A-Za-z0-9_]{5,32}"></td><td><input type="checkbox" name="new_channel_enabled" value="1" checked></td><td colspan="4">Добавить новый публичный канал</td></tr></tbody></table>
			<?php submit_button( 'Сохранить' ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="promokodiki_telegram_test_sync"><?php wp_nonce_field( 'promokodiki_telegram_test_sync' ); ?><?php submit_button( 'Запросить тестовую синхронизацию', 'secondary' ); ?></form>
		<h2>Журнал</h2><table class="widefat striped"><thead><tr><th>Время UTC</th><th>Канал</th><th>Статус</th><th>Импортировано</th><th>Пропущено</th></tr></thead><tbody><?php foreach ( Promokodiki_Telegram_Log::entries() as $entry ) : ?><tr><td><?php echo esc_html( (string) $entry['timestamp'] ); ?></td><td><?php echo esc_html( (string) $entry['channel'] ); ?></td><td><?php echo esc_html( (string) $entry['status'] ); ?></td><td><?php echo esc_html( (string) $entry['imported'] ); ?></td><td><?php echo esc_html( (string) $entry['skipped'] ); ?></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}
}
