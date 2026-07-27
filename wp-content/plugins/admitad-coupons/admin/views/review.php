<?php
/**
 * Review queue view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Очередь проверки Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html__( 'Редактор может исправить только отдельный купон. Глобальные связи и правила доступны администратору в соответствующих разделах.', 'promokodiki-admitad' ); ?></p>
	<form method="get"><input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-review"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"><?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?></form>
	<table class="widefat striped"><thead><tr><th>ID</th><th><?php echo esc_html__( 'Причина', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Объект', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Данные решения', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Исправить купон', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows['items'] as $row ) : ?>
		<tr>
			<td><?php echo esc_html( (string) $row['id'] ); ?></td><td><?php echo esc_html( $row['reason_code'] . ' / ' . $row['severity'] ); ?></td><td><?php echo esc_html( $row['entity_type'] . ':' . $row['entity_id'] ); ?></td>
			<td><details><summary><?php echo esc_html__( 'Показать', 'promokodiki-admitad' ); ?></summary><pre style="white-space:pre-wrap"><?php echo esc_html( wp_json_encode( $row['evidence'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre></details></td>
			<td>
			<?php if ( 'coupon' === $row['entity_type'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="resolve_coupon_only"><input type="hidden" name="queue_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
					<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
					<select name="term_ids[]" multiple required size="4"><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select>
					<?php submit_button( __( 'Назначить и заблокировать', 'promokodiki-admitad' ), 'primary small', '', false ); ?>
				</form>
			<?php else : ?>—<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $rows['total'] / $rows['per_page'] ) ), 'current' => $rows['page'] ) ) ); ?>
</div>
