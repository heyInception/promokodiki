<?php  
/*
Plugin Name: 	Smart Bulk Delete & Content Cleaner for WordPress
Description: 	Smartly remove posts, pages, custom types, media and comments in bulk. Save time, declutter your site and streamline content management.
Version: 		1.1
Author: 		Kirtikumar Solanki
Text Domain: 	smart-bulk-content-remover
Author URI: 	https://profiles.wordpress.org/solankisoftware/
License: 		GPLv2 or later
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) { die(); }

if ( ! defined( 'ABDFW_VERSION' ) ) { define('ABDFW_VERSION', '1.1'); }

if ( ! defined( 'ABDFW_PLUGIN_PATH' ) ) { define('ABDFW_PLUGIN_PATH', plugin_dir_path( __FILE__ )); }

if ( ! defined( 'ABDFW_PLUGIN_DIR' ) ) { define('ABDFW_PLUGIN_DIR', plugin_dir_url( __FILE__ )); }

if ( ! defined( 'ABDFW_PLUGIN_BASENAME' ) ) { define( 'ABDFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); } 

if ( ! defined( 'ABDFW_ADMIN_INCLUDES_PATH' ) ) { define( 'ABDFW_ADMIN_INCLUDES_PATH', ABSPATH . 'wp-admin/includes/' ); }

function abdfw_activate() {
    // Activation code here, like initializing default plugin options
}
register_activation_hook(__FILE__, 'abdfw_activate');

function abdfw_deactivate() {
    // Deactivation code here, like cleaning up options or temporary data
    if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
        wp_clear_scheduled_hook( 'abdfw_run_scheduled_page_cleanup' );
    }
}
register_deactivation_hook(__FILE__, 'abdfw_deactivate');

require_once plugin_dir_path(__FILE__) . 'includes/class-smart-bulk-content-remover.php';

// Instantiate the abdfw_bulk_delete class
$abdfw_bulk_delete = new abdfw_bulk_delete();

// Initialize the plugin
$abdfw_bulk_delete->abdfw_init();