<?php
/**
 * Plugin Name: Promokodiki AJAX Filter
 * Description: Context-aware AJAX filtering and weekly click statistics for promocodes.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Promokodiki
 * License: GPL-2.0-or-later
 * Text Domain: promokodiki-ajax-filter
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PROMOKODIKI_FILTER_VERSION', '0.1.0' );
define( 'PROMOKODIKI_FILTER_FILE', __FILE__ );
define( 'PROMOKODIKI_FILTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROMOKODIKI_FILTER_URL', plugin_dir_url( __FILE__ ) );

require_once PROMOKODIKI_FILTER_DIR . 'includes/class-activator.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-settings.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-state.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-context.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-click-stats.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-query-service.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-renderer.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-ajax-controller.php';
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-plugin.php';

register_activation_hook( PROMOKODIKI_FILTER_FILE, array( 'Promokodiki_Filter_Activator', 'activate' ) );
add_action( 'plugins_loaded', array( 'Promokodiki_Filter_Plugin', 'boot' ) );
