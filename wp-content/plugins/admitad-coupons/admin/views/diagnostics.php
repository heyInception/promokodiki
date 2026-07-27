<?php
/**
 * Sanitized diagnostics view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Диагностика Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html__( 'Снимок не содержит токены, секреты или заголовки авторизации.', 'promokodiki-admitad' ); ?></p>
	<textarea readonly rows="30" class="large-text code"><?php echo esc_textarea( wp_json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
</div>
