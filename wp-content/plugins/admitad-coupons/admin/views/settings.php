<?php
/**
 * Admitad settings form.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap promokodiki-admitad-admin">
	<h1><?php echo esc_html__( 'Автоматизация Admitad', 'promokodiki-admitad' ); ?></h1>
	<?php if ( isset( $_GET['admitad_saved'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html__( 'Настройки сохранены.', 'promokodiki-admitad' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="settings_save" data-admitad-page="admitad-settings" data-admitad-fragment="foundation">
		<input type="hidden" name="action" value="promokodiki_admitad_save_settings">
		<?php wp_nonce_field( 'promokodiki_admitad_save_settings' ); ?>
		<h2><?php echo esc_html__( 'Подключение', 'promokodiki-admitad' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="admitad-client-id">Client ID</label></th>
				<td>
					<?php if ( $constant_mode['client_id'] ) : ?>
						<code><?php echo esc_html__( 'Задан константой wp-config.php', 'promokodiki-admitad' ); ?></code>
					<?php else : ?>
						<input class="regular-text" id="admitad-client-id" name="credentials[client_id]" value="<?php echo esc_attr( (string) get_option( 'promokodiki_admitad_client_id', '' ) ); ?>">
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="admitad-client-secret">Client secret</label></th>
				<td>
					<?php if ( $constant_mode['client_secret'] ) : ?>
						<code><?php echo esc_html__( 'Задан константой wp-config.php', 'promokodiki-admitad' ); ?></code>
					<?php else : ?>
						<input class="regular-text" type="password" id="admitad-client-secret" name="credentials[client_secret]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr__( 'Оставьте пустым, чтобы не менять', 'promokodiki-admitad' ); ?>">
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="admitad-website-id">Website ID</label></th>
				<td>
					<?php if ( $constant_mode['website_id'] ) : ?>
						<code><?php echo esc_html__( 'Задан константой wp-config.php', 'promokodiki-admitad' ); ?></code>
					<?php else : ?>
						<input class="regular-text" id="admitad-website-id" name="credentials[website_id]" value="<?php echo esc_attr( (string) get_option( 'promokodiki_admitad_website_id', '' ) ); ?>">
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php echo esc_html__( 'Автоматизация и классификация', 'promokodiki-admitad' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $fields as $key => $field ) : ?>
				<tr>
					<th><label for="admitad-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<?php if ( 'checkbox' === $field['type'] ) : ?>
							<input type="hidden" name="settings[<?php echo esc_attr( $key ); ?>]" value="0">
							<input type="checkbox" id="admitad-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( (bool) $settings[ $key ] ); ?>>
						<?php else : ?>
							<input type="<?php echo esc_attr( $field['type'] ); ?>" id="admitad-<?php echo esc_attr( $key ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" class="regular-text">
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<p class="description">
			<?php echo esc_html__( 'Иерархия рубрик сайта неприкосновенна. Новые значения Admitad попадают в очередь; подозрительные дубликаты не объединяются автоматически. Максимум — три тематические рубрики.', 'promokodiki-admitad' ); ?>
		</p>
		<?php submit_button( __( 'Сохранить настройки', 'promokodiki-admitad' ) ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="promokodiki_admitad_refresh_token">
		<?php wp_nonce_field( 'promokodiki_admitad_refresh_token' ); ?>
		<?php submit_button( __( 'Проверить подключение', 'promokodiki-admitad' ), 'secondary' ); ?>
	</form>
</div>
