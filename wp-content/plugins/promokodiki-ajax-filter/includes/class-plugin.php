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
		Promokodiki_Filter_Activator::maybe_upgrade();

		add_action( 'created_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'edited_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'delete_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'set_object_terms', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
		add_action( 'save_post_promocode', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );

		add_action( 'wp_ajax_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) );
		add_action( 'wp_ajax_promokodiki_promo_use', array( 'Promokodiki_Filter_Ajax_Controller', 'use_promo' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_promo_use', array( 'Promokodiki_Filter_Ajax_Controller', 'use_promo' ) );
		add_action( 'wp_ajax_promokodiki_promo_vote', array( 'Promokodiki_Filter_Ajax_Controller', 'vote_promo' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_promo_vote', array( 'Promokodiki_Filter_Ajax_Controller', 'vote_promo' ) );
		add_action( 'wp_ajax_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) );
		add_action( 'wp_ajax_nopriv_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'rocket_css_url', array( __CLASS__, 'normalize_wp_rocket_css_url' ), PHP_INT_MAX );
		add_action( 'admin_init', array( 'Promokodiki_Filter_Settings', 'register' ) );
		add_action( 'admin_menu', array( 'Promokodiki_Filter_Settings', 'add_menu' ) );
		add_action( 'admin_notices', array( 'Promokodiki_Filter_Settings', 'render_conflict_notice' ) );

		add_shortcode(
			'promokodiki_ajax_filter',
			static function ( $attributes ): string {
				$attributes = is_array( $attributes ) ? $attributes : array();
				$attributes = shortcode_atts( array( 'context' => 'home', 'object_id' => 0 ), $attributes );
				return Promokodiki_Filter_Renderer::render( sanitize_key( $attributes['context'] ), absint( $attributes['object_id'] ) );
			}
		);
	}

	public static function normalize_wp_rocket_css_url( string $url ): string {
		$normalized_url = wp_normalize_path( $url );
		$content_dir    = untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		$path_position  = strpos( $normalized_url, $content_dir );

		if ( false === $path_position ) {
			return $url;
		}

		$relative_path = substr( $normalized_url, $path_position + strlen( $content_dir ) );
		if ( ! str_starts_with( $relative_path, '/cache/background-css/' ) ) {
			return $url;
		}

		return content_url( ltrim( $relative_path, '/' ) );
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
