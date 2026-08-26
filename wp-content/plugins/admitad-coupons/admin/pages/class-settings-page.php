<?php
/**
 * Admitad settings page read model.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders all configurable automation settings without exposing secrets.
 */
final class Promokodiki_Admitad_Settings_Page {
	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для изменения настроек Admitad.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$settings = Promokodiki_Admitad_Config::sanitize( (array) get_option( Promokodiki_Admitad_Config::OPTION_NAME, array() ) );
		$fields   = $this->fields();
		$constant_mode = array(
			'client_id'     => defined( 'PROMOKODIKI_ADMITAD_CLIENT_ID' ),
			'client_secret' => defined( 'PROMOKODIKI_ADMITAD_CLIENT_SECRET' ),
			'website_id'    => defined( 'PROMOKODIKI_ADMITAD_WEBSITE_ID' ),
		);
		require ADMITAD_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Return labels and field types for every configurable setting.
	 *
	 * @return array<string, array{label:string,type:string}>
	 */
	private function fields(): array {
		$labels = array(
			'coupon_interval'        => 'Интервал купонов, сек.',
			'reference_interval'     => 'Интервал справочников, сек.',
			'reconcile_interval'     => 'Интервал сверки, сек.',
			'batch_size'             => 'Размер пакета',
			'max_retries'            => 'Повторные попытки',
			'retry_base_seconds'     => 'Базовая задержка повтора, сек.',
			'missing_threshold'      => 'Пропуски до деактивации',
			'confidence_high'        => 'Порог высокой уверенности',
			'confidence_medium'      => 'Порог средней уверенности',
			'weight_coupon_category' => 'Вес категории купона',
			'weight_campaign'        => 'Вес категории кампании',
			'weight_company'         => 'Вес профиля компании',
			'weight_title'           => 'Вес заголовка',
			'weight_description'     => 'Вес описания',
			'max_categories'         => 'Максимум тематических рубрик',
			'candidate_evidence'     => 'Наблюдений для правила',
			'candidate_campaigns'    => 'Разных кампаний для правила',
			'candidate_conflicts'    => 'Допустимые противоречия',
			'auto_tags'              => 'Автоматические теги',
			'email_alerts'           => 'Email-уведомления',
			'email_recipient'        => 'Получатель уведомлений',
			'queue_warning_count'    => 'Порог предупреждения очереди',
			'editor_review_enabled'  => 'Проверка редакторами',
			'log_retention_days'     => 'Хранение журналов, дней',
		);
		$booleans = array( 'auto_tags', 'email_alerts', 'editor_review_enabled' );
		$fields   = array();
		foreach ( Promokodiki_Admitad_Config::defaults() as $key => $value ) {
			$type = in_array( $key, $booleans, true ) ? 'checkbox' : ( 'email_recipient' === $key ? 'email' : 'number' );
			$fields[ $key ] = array(
				'label' => $labels[ $key ],
				'type'  => $type,
			);
		}
		return $fields;
	}
}
