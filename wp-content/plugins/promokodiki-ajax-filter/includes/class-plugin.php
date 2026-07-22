<?php
/**
 * Plugin hook registration.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'created_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'edited_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'delete_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'set_object_terms', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'save_post_promocode', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );

		add_action( 'wp_ajax_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) );
		add_action( 'wp_ajax_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_shortcode(
			'promokodiki_ajax_filter',
			static function ( $attributes ): string {
				$attributes = is_array( $attributes ) ? $attributes : array();
				$attributes = shortcode_atts( array( 'context' => 'home', 'object_id' => 0 ), $attributes );
				return Promokodiki_Filter_Renderer::render( sanitize_key( $attributes['context'] ), absint( $attributes['object_id'] ) );
			}
		);
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'promokodiki-ajax-filter',
			PROMOKODIKI_FILTER_URL . 'assets/css/filter.css',
			array(),
			PROMOKODIKI_FILTER_VERSION
		);
		wp_enqueue_script(
			'promokodiki-filter-state',
			PROMOKODIKI_FILTER_URL . 'assets/js/filter-state.js',
			array(),
			PROMOKODIKI_FILTER_VERSION,
			true
		);
		wp_enqueue_script(
			'promokodiki-ajax-filter',
			PROMOKODIKI_FILTER_URL . 'assets/js/filter.js',
			array( 'promokodiki-filter-state' ),
			PROMOKODIKI_FILTER_VERSION,
			true
		);
		wp_localize_script(
			'promokodiki-ajax-filter',
			'PromokodikiFilterConfig',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'promokodiki_filter_frontend' ),
				'retryLabel'   => Promokodiki_Filter_Settings::get()['retry_label'],
				'loadingLabel' => __( 'Загрузка…', 'promokodiki-ajax-filter' ),
				'genericError' => __( 'Не удалось обновить промокоды.', 'promokodiki-ajax-filter' ),
			)
		);
	}
}
