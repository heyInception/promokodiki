<?php
/**
 * Company profiles view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Профили компаний Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php echo esc_html__( 'Профиль ограничивает допустимые рубрики компании и задаёт необязательную рубрику по умолчанию.', 'promokodiki-admitad' ); ?></p>
	<form method="get">
		<input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-companies">
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Компания или ID', 'promokodiki-admitad' ); ?>">
		<?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
	</form>
	<h2><?php echo esc_html__( 'Создать или обновить профиль', 'promokodiki-admitad' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="save_company">
		<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<input type="number" min="1" name="campaign_id" required placeholder="Campaign ID">
		<input type="text" name="display_name" placeholder="<?php echo esc_attr__( 'Название компании', 'promokodiki-admitad' ); ?>">
		<select name="allowed_term_ids[]" multiple required size="5">
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="default_term_id"><option value="0"><?php echo esc_html__( 'Без рубрики по умолчанию', 'promokodiki-admitad' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="number" min="0" max="1000" name="weight" value="40">
		<?php submit_button( __( 'Сохранить профиль', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<table class="widefat striped"><thead><tr><th>Campaign ID</th><th><?php echo esc_html__( 'Компания', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Допустимые рубрики', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'По умолчанию', 'promokodiki-admitad' ); ?></th><th><?php echo esc_html__( 'Статус', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows['items'] as $row ) : ?>
		<?php
		$allowed_names = array();
		foreach ( $row['allowed_term_ids'] as $allowed_id ) {
			$allowed_term = get_term( (int) $allowed_id, 'promocode_category' );
			if ( $allowed_term instanceof WP_Term ) {
				$allowed_names[] = $allowed_term->name;
			}
		}
		$default_term = get_term( (int) $row['default_term_id'], 'promocode_category' );
		?>
		<tr><td><?php echo esc_html( (string) $row['campaign_id'] ); ?></td><td><?php echo esc_html( (string) $row['display_name'] ); ?></td><td><?php echo esc_html( implode( ', ', $allowed_names ) ?: '—' ); ?></td><td><?php echo esc_html( $default_term instanceof WP_Term ? $default_term->name : '—' ); ?></td><td><?php echo esc_html( (string) $row['status'] ); ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php echo wp_kses_post( paginate_links( array( 'total' => max( 1, (int) ceil( $rows['total'] / $rows['per_page'] ) ), 'current' => $rows['page'] ) ) ); ?>
</div>
