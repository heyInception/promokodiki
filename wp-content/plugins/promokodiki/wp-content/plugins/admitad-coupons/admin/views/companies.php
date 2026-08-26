<?php
/**
 * Company profiles view.
 *
 * @package Promokodiki_Admitad
 */
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Профили компаний Admitad', 'promokodiki-admitad' ); ?></h1>
	<p><?php esc_html_e( 'Профиль ограничивает допустимые рубрики компании и при необходимости задаёт одну рубрику по умолчанию.', 'promokodiki-admitad' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="company_save" data-admitad-page="admitad-companies" data-admitad-target="#company-table">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="save_company">
		<?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<h2><?php esc_html_e( 'Создать или обновить профиль', 'promokodiki-admitad' ); ?></h2>
		<p><label for="company-display-name"><?php esc_html_e( 'Компания Admitad', 'promokodiki-admitad' ); ?></label><br><input id="company-display-name" type="text" name="display_name" data-admitad-company-search autocomplete="off" aria-autocomplete="list" aria-controls="company-search-results"><input id="company-campaign-id" type="hidden" name="campaign_id" value=""><span class="description"> <?php esc_html_e( 'Начните вводить название и выберите компанию из списка: сохранится её стабильный Campaign ID.', 'promokodiki-admitad' ); ?></span></p>
		<div id="company-search-results" class="promokodiki-admitad-company-results" role="listbox" hidden></div>
		<p><label for="company-allowed-terms"><?php esc_html_e( 'Допустимые рубрики', 'promokodiki-admitad' ); ?></label><br><select id="company-allowed-terms" name="allowed_term_ids[]" multiple required size="5"><?php foreach ( $term_options as $option ) : ?><option value="<?php echo esc_attr( (string) $option['id'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option><?php endforeach; ?></select><span class="description"> <?php esc_html_e( 'Классификатор будет использовать только эти рубрики для компании.', 'promokodiki-admitad' ); ?></span></p>
		<p><label for="company-default-term"><?php esc_html_e( 'Рубрика по умолчанию', 'promokodiki-admitad' ); ?></label><br><select id="company-default-term" name="default_term_id"><option value="0"><?php esc_html_e( 'Без рубрики по умолчанию', 'promokodiki-admitad' ); ?></option><?php foreach ( $term_options as $option ) : ?><option value="<?php echo esc_attr( (string) $option['id'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option><?php endforeach; ?></select><span class="description"> <?php esc_html_e( 'Если выбрана, эта рубрика обязательно должна входить в допустимые.', 'promokodiki-admitad' ); ?></span></p>
		<p><label for="company-weight"><?php esc_html_e( 'Вес сигнала', 'promokodiki-admitad' ); ?></label><br><input id="company-weight" type="number" min="0" max="1000" name="weight" value="40"><span class="description"> <?php esc_html_e( 'Больший вес усиливает приоритет профиля в классификации.', 'promokodiki-admitad' ); ?></span></p>
		<?php submit_button( __( 'Сохранить профиль', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<div id="company-table" data-admitad-table data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="company_list" data-admitad-page="admitad-companies" data-admitad-fragment="company-table">
		<form method="get" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="company_list" data-admitad-page="admitad-companies" data-admitad-fragment="company-table">
			<input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-companies">
			<label for="company-search"><?php esc_html_e( 'Поиск', 'promokodiki-admitad' ); ?></label> <input id="company-search" type="search" name="s" value="<?php echo esc_attr( $request->search() ); ?>" placeholder="<?php esc_attr_e( 'Компания или ID', 'promokodiki-admitad' ); ?>">
			<?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
		</form>
		<?php require ADMITAD_PLUGIN_DIR . 'admin/views/partials/company-table.php'; ?>
	</div>
</div>
