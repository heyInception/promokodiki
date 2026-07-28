<?php
/**
 * Classification history and validation view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'История классификации и откат', 'promokodiki-admitad' ); ?></h1>
	<?php if ( current_user_can( 'manage_admitad_automation' ) ) : ?>
		<h2><?php echo esc_html__( 'Предварительный просмотр', 'promokodiki-admitad' ); ?></h2>
		<p><?php echo esc_html__( 'Укажите ID купонов. Просмотр не меняет рубрики и исключает купоны с редакционной блокировкой.', 'promokodiki-admitad' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="promokodiki_admitad_history_action"><input type="hidden" name="operation" value="preview">
			<?php wp_nonce_field( 'promokodiki_admitad_history_action' ); ?>
			<textarea name="post_ids" rows="3" class="large-text" required placeholder="123, 456, 789"></textarea>
			<?php submit_button( __( 'Создать неизменяемый просмотр', 'promokodiki-admitad' ) ); ?>
		</form>
		<h2><?php echo esc_html__( 'Контрольная выборка', 'promokodiki-admitad' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="promokodiki_admitad_history_action"><input type="hidden" name="operation" value="create_sample">
			<?php wp_nonce_field( 'promokodiki_admitad_history_action' ); ?>
			<input type="number" name="size" min="1" max="500" value="150">
			<?php submit_button( __( 'Создать выборку', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
		</form>
	<?php endif; ?>

	<?php if ( $snapshot ) : ?>
		<h2><?php echo esc_html( sprintf( 'Снимок %s — затронуто: %d', $snapshot['id'], count( $snapshot['rows'] ) ) ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Купон', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Было', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Станет', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Основная: было → станет', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Уверенность', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
		<?php foreach ( $snapshot['rows'] as $row ) : ?>
			<tr><td><?php echo esc_html( get_the_title( (int) $row['post_id'] ) . ' (#' . $row['post_id'] . ')' ); ?></td><td><?php echo esc_html( implode( ', ', $row['previous_terms'] ) ); ?></td><td><?php echo esc_html( implode( ', ', $row['result_terms'] ) ); ?></td><td><?php echo esc_html( $row['previous_primary_term_id'] . ' → ' . $row['result_primary_term_id'] ); ?></td><td><?php echo esc_html( (string) $row['confidence'] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( current_user_can( 'manage_admitad_automation' ) && get_current_user_id() === $snapshot['owner_id'] ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="promokodiki_admitad_history_action"><input type="hidden" name="snapshot_id" value="<?php echo esc_attr( $snapshot['id'] ); ?>">
				<?php wp_nonce_field( 'promokodiki_admitad_history_action' ); ?>
				<?php if ( 'previewed' === $snapshot['status'] ) : ?><input type="hidden" name="operation" value="apply"><?php submit_button( __( 'Подтвердить и применить пакетно', 'promokodiki-admitad' ) ); ?><?php elseif ( 'applied' === $snapshot['status'] ) : ?><input type="hidden" name="operation" value="rollback"><?php submit_button( __( 'Точно откатить этот снимок', 'promokodiki-admitad' ), 'secondary' ); ?><?php endif; ?>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $sample && $report ) : ?>
		<h2><?php echo esc_html__( 'Отчёт контрольной выборки', 'promokodiki-admitad' ); ?></h2>
		<ul>
			<li><?php echo esc_html( 'High confidence accuracy: ' . $report['high_confidence_accuracy'] . '%' ); ?></li>
			<li><?php echo esc_html( 'Non-other coverage: ' . $report['non_other_coverage'] . '%' ); ?></li>
			<li><?php echo esc_html( 'Lock preservation: ' . $report['lock_preservation'] . '%' ); ?></li>
			<li><?php echo esc_html( 'Out-of-profile rate: ' . $report['out_of_profile_rate'] . '%' ); ?></li>
		</ul>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Купон', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Текущий результат', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Ожидаемые рубрики редактора', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
		<?php foreach ( $sample['rows'] as $row ) : ?>
			<tr><td><?php echo esc_html( get_the_title( (int) $row['post_id'] ) . ' (#' . $row['post_id'] . ')' ); ?></td><td><?php echo esc_html( implode( ', ', $row['term_ids'] ) ); ?></td><td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="promokodiki_admitad_history_action"><input type="hidden" name="operation" value="record_review"><input type="hidden" name="sample_id" value="<?php echo esc_attr( $sample['id'] ); ?>"><input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $row['post_id'] ); ?>">
					<?php wp_nonce_field( 'promokodiki_admitad_history_action' ); ?>
					<select name="expected_terms[]" multiple required size="3"><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, (array) ( $sample['reviews'][ (string) $row['post_id'] ] ?? array() ), true ) ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select>
					<?php submit_button( __( 'Записать оценку', 'promokodiki-admitad' ), 'secondary small', '', false ); ?>
				</form>
			</td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Последние решения классификатора', 'promokodiki-admitad' ); ?></h2>
	<table class="widefat striped"><thead><tr><th>ID</th><th><?php echo esc_html__( 'Купон', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Триггер', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Было → стало', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Дата', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
	<?php foreach ( $history['items'] as $row ) : ?><tr><td><?php echo esc_html( (string) $row['id'] ); ?></td><td><?php echo esc_html( (string) $row['post_id'] ); ?></td><td><?php echo esc_html( (string) $row['trigger_name'] ); ?></td><td><?php echo esc_html( implode( ',', $row['previous_terms'] ) . ' → ' . implode( ',', $row['result_terms'] ) ); ?></td><td><?php echo esc_html( (string) $row['created_at'] ); ?></td></tr><?php endforeach; ?>
	</tbody></table>
	<div data-admitad-table data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="history_list" data-admitad-page="admitad-history" data-admitad-fragment="history-table"><?php require ADMITAD_PLUGIN_DIR . 'admin/views/partials/history-table.php'; ?></div>
</div>
