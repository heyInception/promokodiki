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
	}
}
