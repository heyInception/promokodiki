<?php
/**
 * Overview view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Admitad: обзор автоматизации', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html( sprintf( 'Версия схемы: %s', $snapshot['schema_version'] ) ); ?></p>
	<h2><?php echo esc_html__( 'Очередь проверки', 'promokodiki-admitad' ); ?></h2>
	<ul>
		<?php foreach ( $snapshot['queue'] as $reason => $count ) : ?>
			<li><code><?php echo esc_html( $reason ); ?></code>: <?php echo esc_html( (string) $count ); ?></li>
		<?php endforeach; ?>
	</ul>
	<h2><?php echo esc_html__( 'Расписание', 'promokodiki-admitad' ); ?></h2>
	<ul>
		<?php foreach ( $snapshot['cron'] as $job => $event ) : ?>
			<li><?php echo esc_html( $job . ': ' . ( $event['scheduled'] ? wp_date( 'Y-m-d H:i:s', $event['timestamp'] ) : 'не запланировано' ) ); ?></li>
		<?php endforeach; ?>
	</ul>
</div>
