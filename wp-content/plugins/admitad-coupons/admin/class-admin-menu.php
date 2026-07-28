<?php
/**
 * Admitad administration navigation.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the nine-section automation administration.
 */
final class Promokodiki_Admitad_Admin_Menu {
	/**
	 * Register the menu hook.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_pages' ) );
	}

	/**
	 * Return section capabilities.
	 *
	 * @return array<string, string>
	 */
	public static function section_capabilities(): array {
		return array(
			'admitad-overview'     => 'review_admitad_mapping',
			'admitad-sync'         => 'manage_admitad_automation',
			'admitad-category-map' => 'manage_admitad_automation',
			'admitad-companies'    => 'manage_admitad_automation',
			'admitad-rules'        => 'manage_admitad_automation',
			'admitad-review'       => 'review_admitad_mapping',
			'admitad-history'      => 'review_admitad_mapping',
			'admitad-settings'     => 'manage_admitad_automation',
			'admitad-diagnostics'  => 'manage_admitad_automation',
		);
	}

	/**
	 * Register all submenu routes.
	 */
	public static function add_pages(): void {
		$labels = array(
			'admitad-overview'     => __( 'Admitad: Обзор', 'promokodiki-admitad' ),
			'admitad-sync'         => __( 'Синхронизация', 'promokodiki-admitad' ),
			'admitad-category-map' => __( 'Маппинг категорий', 'promokodiki-admitad' ),
			'admitad-companies'    => __( 'Компании', 'promokodiki-admitad' ),
			'admitad-rules'        => __( 'Ключевые фразы', 'promokodiki-admitad' ),
			'admitad-review'       => __( 'Очередь проверки', 'promokodiki-admitad' ),
			'admitad-history'      => __( 'История и откат', 'promokodiki-admitad' ),
			'admitad-settings'     => __( 'Настройки', 'promokodiki-admitad' ),
			'admitad-diagnostics'  => __( 'Диагностика', 'promokodiki-admitad' ),
		);
		foreach ( self::section_capabilities() as $slug => $capability ) {
			add_submenu_page(
				'edit.php?post_type=promocode',
				$labels[ $slug ],
				$labels[ $slug ],
				$capability,
				$slug,
				array( self::class, 'render_section' )
			);
		}
	}

	/**
	 * Route the current section to its page object.
	 */
	public static function render_section(): void {
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? 'admitad-overview' ) );
		if ( 'admitad-settings' === $page ) {
			self::render_page( new Promokodiki_Admitad_Settings_Page() );
			return;
		}
		$pages = array(
			'admitad-overview'     => Promokodiki_Admitad_Overview_Page::class,
			'admitad-sync'         => Promokodiki_Admitad_Sync_Page::class,
			'admitad-category-map' => Promokodiki_Admitad_Category_Map_Page::class,
			'admitad-companies'    => Promokodiki_Admitad_Company_Page::class,
			'admitad-rules'        => Promokodiki_Admitad_Rule_Page::class,
			'admitad-review'       => Promokodiki_Admitad_Review_Page::class,
			'admitad-history'      => Promokodiki_Admitad_History_Page::class,
			'admitad-diagnostics'  => Promokodiki_Admitad_Diagnostics_Page::class,
		);
		if ( isset( $pages[ $page ] ) ) {
			self::render_page( new $pages[ $page ]() );
			return;
		}
		if ( ! current_user_can( self::section_capabilities()[ $page ] ?? 'manage_admitad_automation' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для просмотра этого раздела.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Автоматизация Admitad', 'promokodiki-admitad' ) . '</h1>';
		echo '<p>' . esc_html__( 'Раздел подключён к новому безопасному административному маршрутизатору.', 'promokodiki-admitad' ) . '</p></div>';
	}

	/**
	 * Render a page inside the shared accessible AJAX shell.
	 *
	 * @param object $page Page controller with a render method.
	 */
	private static function render_page( object $page ): void {
		echo '<div class="promokodiki-admitad-admin">';
		echo '<div class="promokodiki-admitad-notices" data-admitad-notices role="status" aria-live="polite" aria-atomic="true"></div>';
		$page->render();
		echo '</div>';
	}
}
