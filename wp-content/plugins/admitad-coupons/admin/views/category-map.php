<?php
/**
 * External category map view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Маппинг категорий Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php esc_html_e( 'Связывает стабильные ID Admitad с существующими рубриками сайта и не меняет иерархию таксономии.', 'promokodiki-admitad' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="category_map_save" data-admitad-page="admitad-category-map" data-admitad-target="#category-map-table">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action">
		<input type="hidden" name="operation" value="save_category_map">
		<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<h2><?php esc_html_e( 'Добавить связь', 'promokodiki-admitad' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Пространство имён определяет, к чему относится внешний ID: coupon — категория купона, campaign — категория компании.', 'promokodiki-admitad' ); ?></p>
		<p><label for="category-map-namespace"><?php esc_html_e( 'Пространство имён', 'promokodiki-admitad' ); ?></label><br><select id="category-map-namespace" name="namespace"><option value="coupon">coupon</option><option value="campaign">campaign</option></select></p>
		<p><label for="category-map-external-id"><?php esc_html_e( 'ID категории Admitad', 'promokodiki-admitad' ); ?></label><br><input id="category-map-external-id" type="number" min="1" name="external_id" required></p>
		<p><label for="category-map-site-term"><?php esc_html_e( 'Рубрика сайта', 'promokodiki-admitad' ); ?></label><br><select id="category-map-site-term" name="site_term_id" required><option value=""><?php esc_html_e( 'Выберите рубрику', 'promokodiki-admitad' ); ?></option><?php foreach ( $term_options as $option ) : ?><option value="<?php echo esc_attr( (string) $option['id'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option><?php endforeach; ?></select></p>
		<p><label for="category-map-weight"><?php esc_html_e( 'Вес сигнала', 'promokodiki-admitad' ); ?></label><br><input id="category-map-weight" type="number" min="0" max="1000" name="weight" value="100"><span class="description"> <?php esc_html_e( 'Больший вес усиливает влияние этой связи при классификации.', 'promokodiki-admitad' ); ?></span></p>
		<?php submit_button( __( 'Сохранить связь', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<div id="category-map-table" data-admitad-table data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="category_map_list" data-admitad-page="admitad-category-map" data-admitad-fragment="category-map-table">
		<form method="get" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="category_map_list" data-admitad-page="admitad-category-map" data-admitad-fragment="category-map-table">
			<input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-category-map">
			<label for="category-map-search"><?php esc_html_e( 'Поиск', 'promokodiki-admitad' ); ?></label> <input id="category-map-search" type="search" name="s" value="<?php echo esc_attr( $request->search() ); ?>" placeholder="<?php esc_attr_e( 'Название или ID Admitad', 'promokodiki-admitad' ); ?>">
			<?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
		</form>
		<?php require ADMITAD_PLUGIN_DIR . 'admin/views/partials/category-map-table.php'; ?>
	</div>
</div>
