<?php
/** @var array<string,mixed> $recovery */
$ajax_nonce = wp_create_nonce( 'promokodiki_admitad_admin_ajax' );
$migration  = (array) ( $recovery['migration_progress'] ?? array() );
?>
<div class="card" data-admitad-recovery data-admitad-progress-status="<?php echo esc_attr( (string) ( $migration['status'] ?? 'idle' ) ); ?>">
	<h2>Восстановление данных</h2>
	<p>Все операции выполняются ограниченными пакетами. Путь резервной копии здесь намеренно не отображается.</p>
	<ul>
		<li>Ключевые слова: <?php echo esc_html( (string) $recovery['legacy_keywords'] ); ?></li>
		<li>Компании: <?php echo esc_html( (string) $recovery['legacy_companies'] ); ?></li>
		<li>Резервная копия: <?php echo esc_html( $recovery['backup_ready'] ? 'проверена' : 'требуется' ); ?></li>
		<li>Справочники: <?php echo esc_html( $recovery['reference_ready'] ? 'готовы' : 'требуется синхронизация' ); ?></li>
		<li>Миграция: <?php echo esc_html( (string) ( $migration['status'] ?? 'idle' ) ); ?>, <?php echo esc_html( (string) ( $migration['processed'] ?? 0 ) ); ?> / <?php echo esc_html( (string) ( $migration['total'] ?? 0 ) ); ?></li>
	</ul>
	<?php if ( $recovery['blockers'] ) : ?><p>Блокеры: <?php echo esc_html( implode( ', ', $recovery['blockers'] ) ); ?></p><?php endif; ?>
	<form method="post" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="recovery_reference_start" data-admitad-page="admitad-diagnostics">
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $ajax_nonce ); ?>">
		<button type="submit" class="button">Запустить синхронизацию справочников</button>
	</form>
	<form method="post" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="recovery_migration_start" data-admitad-page="admitad-diagnostics">
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $ajax_nonce ); ?>">
		<button type="submit" class="button button-primary"<?php disabled( ! $recovery['backup_ready'] || ! $recovery['reference_ready'] ); ?>>Запустить миграцию</button>
	</form>
	<form method="post" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="recovery_migration_status" data-admitad-page="admitad-diagnostics" data-admitad-step-operation="recovery_migration_step">
		<input type="hidden" name="_ajax_nonce" value="<?php echo esc_attr( $ajax_nonce ); ?>">
		<input type="hidden" name="owner" value="<?php echo esc_attr( (string) ( $migration['owner'] ?? '' ) ); ?>">
		<button type="submit" class="button">Обновить статус / продолжить</button>
	</form>
	<noscript>Для безопасной пакетной миграции требуется JavaScript.</noscript>
</div>
