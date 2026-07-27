<?php
/**
 * External category map view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Маппинг категорий Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html__( 'Связывает стабильные ID Admitad только с уже существующими рубриками. Иерархия сайта не изменяется.', 'promokodiki-admitad' ); ?></p>
	<form method="get">
		<input type="hidden" name="post_type" value="promocode">
		<input type="hidden" name="page" value="admitad-category-map">
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Название или ID Admitad', 'promokodiki-admitad' ); ?>">
		<?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
	</form>
	<h2><?php echo esc_html__( 'Добавить связь', 'promokodiki-admitad' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action">
		<input type="hidden" name="operation" value="save_category_map">
		<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<select name="namespace"><option value="coupon">coupon</option><option value="campaign">campaign</option></select>
		<input type="number" min="1" name="external_id" required placeholder="Admitad ID">
		<select name="site_term_id" required>
			<option value=""><?php echo esc_html__( 'Рубрика сайта', 'promokodiki-admitad' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name . ' (ID ' . $term->term_id . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="number" min="0" max="1000" name="weight" value="100">
		<?php submit_button( __( 'Сохранить связь', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<table class="widefat striped">
		<thead><tr><th>ID Admitad</th><th><?php echo esc_html__( 'Название', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Рубрика сайта', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Вес', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Статус', 'promokodiki-admitad' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $rows['items'] as $row ) : ?>
			<?php $site_term = get_term( (int) $row['site_term_id'], 'promocode_category' ); ?>
			<tr>
				<td><?php echo esc_html( $row['source_namespace'] . ':' . $row['external_category_id'] ); ?></td>
				<td><?php echo esc_html( (string) $row['external_name'] ); ?></td>
				<td><?php echo esc_html( $site_term instanceof WP_Term ? $site_term->name : '—' ); ?></td>
				<td><?php echo esc_html( (string) $row['weight'] ); ?></td>
				<td><?php echo esc_html( (string) $row['status'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $rows['total'] / $rows['per_page'] ) ), 'current' => $rows['page'] ) ) ); ?>
</div>
