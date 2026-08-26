<?php /** @package Promokodiki_Admitad */ ?>
<div class="wrap promokodiki-admitad-admin">
	<h1><?php esc_html_e( 'Ключевые фразы', 'promokodiki-admitad' ); ?></h1>
	<p><?php esc_html_e( 'Активны только подтверждённые правила; удаление заменено архивированием.', 'promokodiki-admitad' ); ?></p>
	<form method="get" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="rule_list" data-admitad-page="admitad-rules" data-admitad-fragment="rules-table">
		<input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-rules"><input type="hidden" name="paged" value="1">
		<label for="rule-search"><?php esc_html_e( 'Поиск фразы', 'promokodiki-admitad' ); ?></label><input id="rule-search" type="search" name="s" value="<?php echo esc_attr( $request->search() ); ?>" data-admitad-rule-search>
		<label for="rule-filter-status"><?php esc_html_e( 'Статус', 'promokodiki-admitad' ); ?></label><select id="rule-filter-status" name="status"><option value=""><?php esc_html_e( 'Все статусы', 'promokodiki-admitad' ); ?></option><?php foreach ( array( 'active', 'candidate', 'suspended', 'conflict', 'archived' ) as $status ) : $badge = Promokodiki_Admitad_Admin_Presenter::status( 'rule', $status ); ?><option value="<?php echo esc_attr( $status ); ?>"<?php selected( $request->filter( 'status' ), $status ); ?>><?php echo esc_html( $badge['label'] ); ?></option><?php endforeach; ?></select>
		<?php submit_button( __( 'Найти', 'promokodiki-admitad' ), 'secondary', '', false ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="rule_save" data-admitad-page="admitad-rules" data-admitad-fragment="rules-table">
		<input type="hidden" name="action" value="promokodiki_admitad_mapping_action"><input type="hidden" name="operation" value="save_rule"><?php wp_nonce_field( 'promokodiki_admitad_mapping_action' ); ?>
		<label for="rule-phrase"><?php esc_html_e( 'Фраза', 'promokodiki-admitad' ); ?></label><input id="rule-phrase" name="phrase" required>
		<label for="rule-term"><?php esc_html_e( 'Рубрика', 'promokodiki-admitad' ); ?></label><select id="rule-term" name="site_term_id" required><option value=""><?php esc_html_e( 'Выберите рубрику', 'promokodiki-admitad' ); ?></option><?php foreach ( $term_options as $term ) : ?><option value="<?php echo esc_attr( (string) $term['id'] ); ?>"><?php echo esc_html( $term['label'] ); ?></option><?php endforeach; ?></select>
		<label for="rule-mode"><?php esc_html_e( 'Режим совпадения', 'promokodiki-admitad' ); ?></label><select id="rule-mode" name="mode"><option value="phrase">Точная фраза</option><option value="token">Слово</option><option value="prefix">Начало слова</option></select>
		<label for="rule-status"><?php esc_html_e( 'Статус', 'promokodiki-admitad' ); ?></label><select id="rule-status" name="status"><option value="candidate">Кандидат</option><option value="active">Активно</option><option value="suspended">Приостановлено</option></select>
		<label for="rule-weight"><?php esc_html_e( 'Вес', 'promokodiki-admitad' ); ?></label><input id="rule-weight" type="number" min="0" max="1000" name="weight" value="20">
		<?php submit_button( __( 'Сохранить правило', 'promokodiki-admitad' ), 'primary', '', false ); ?>
	</form>
	<div data-admitad-table data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="rule_list" data-admitad-page="admitad-rules" data-admitad-fragment="rules-table"><?php require ADMITAD_PLUGIN_DIR . 'admin/views/partials/rules-table.php'; ?></div>
</div>
