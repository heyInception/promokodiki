<?php
/**
 * Phrase rules view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Ключевые фразы', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html__( 'Автоматически работают только активные правила. Кандидаты и конфликты требуют подтверждения.', 'promokodiki-admitad' ); ?></p>
	<form method="get"><input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-rules"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"><?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?></form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="save_rule">
		<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<input type="text" name="phrase" required placeholder="<?php echo esc_attr__( 'Точная фраза', 'promokodiki-admitad' ); ?>">
		<select name="site_term_id" required><option value=""><?php echo esc_html__( 'Рубрика', 'promokodiki-admitad' ); ?></option><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select>
		<select name="mode"><option value="phrase">phrase</option><option value="token">token</option><option value="prefix">prefix</option></select>
		<select name="status"><option value="candidate">candidate</option><option value="active">active</option><option value="suspended">suspended</option></select>
		<input type="number" min="0" max="1000" name="weight" value="20">
		<?php submit_button( __( 'Сохранить правило', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Фраза', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Рубрика', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Режим / вес', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Доказательства', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Статус', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows['items'] as $row ) : ?>
		<?php $site_term = get_term( (int) $row['site_term_id'], 'promocode_category' ); ?>
		<tr>
			<td><?php echo esc_html( (string) $row['normalized_phrase'] ); ?></td><td><?php echo esc_html( $site_term instanceof WP_Term ? $site_term->name : '—' ); ?></td><td><?php echo esc_html( $row['match_mode'] . ' / ' . $row['weight'] ); ?></td><td><?php echo esc_html( $row['evidence_count'] . ' / ' . $row['distinct_campaign_count'] . ' / ' . $row['contradiction_count'] ); ?></td>
			<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="set_rule_status"><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>"><?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?><select name="status"><?php foreach ( array( 'active', 'candidate', 'suspended', 'conflict' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row['status'], $status ); ?>><?php echo esc_html( $status ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Изменить', 'promokodiki-admitad' ), 'secondary small', '', false ); ?></form></td>
		</tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $rows['total'] / $rows['per_page'] ) ), 'current' => $rows['page'] ) ) ); ?>
</div>
