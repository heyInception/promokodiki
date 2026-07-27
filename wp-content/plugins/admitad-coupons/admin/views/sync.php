<?php
/**
 * Synchronization operations view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Синхронизация Admitad', 'promokodiki-admitad' ); ?></h1>
	<div>
		<?php foreach ( array( 'coupon_sync' => 'Запустить купоны', 'reference_sync' => 'Обновить справочники', 'reconcile' => 'Запустить сверку', 'test_email' => 'Отправить тестовое письмо' ) as $operation => $label ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<input type="hidden" name="action" value="promokodiki_admitad_operation">
				<input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>">
				<?php wp_nonce_field( 'promokodiki_admitad_operation' ); ?>
				<?php submit_button( $label, 'secondary', 'submit', false ); ?>
			</form>
		<?php endforeach; ?>
	</div>
	<?php foreach ( array( 'coupon' => 'Купоны', 'reference' => 'Справочники' ) as $lock_name => $lock_label ) : ?>
		<?php if ( ! empty( $snapshot['locks'][ $lock_name ]['expired'] ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-top:8px;margin-right:8px">
				<input type="hidden" name="action" value="promokodiki_admitad_operation">
				<input type="hidden" name="operation" value="<?php echo esc_attr( 'recover_' . $lock_name . '_lock' ); ?>">
				<?php wp_nonce_field( 'promokodiki_admitad_operation' ); ?>
				<?php submit_button( sprintf( 'Снять просроченную блокировку: %s', $lock_label ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	<?php endforeach; ?>
	<h2><?php echo esc_html__( 'Последние запуски', 'promokodiki-admitad' ); ?></h2>
	<table class="widefat striped">
		<thead><tr><th>ID</th><th>Задача</th><th>Статус</th><th>Начало</th><th>Обработано</th></tr></thead>
		<tbody>
		<?php foreach ( $snapshot['recent_runs'] as $run ) : ?>
			<tr>
				<td><?php echo esc_html( (string) $run['id'] ); ?></td>
				<td><?php echo esc_html( (string) $run['job_type'] ); ?></td>
				<td><?php echo esc_html( (string) $run['status'] ); ?></td>
				<td><?php echo esc_html( (string) $run['started_at'] ); ?></td>
				<td><?php echo esc_html( (string) $run['processed_count'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
