<?php
/**
 * Plugin Name: Promokodiki Admitad Sync
 * Description: Imports Admitad coupons into a single promocode post type and links them to shops.
 * Version: 3.0.0
 * Author: Promokodiki
 * Text Domain: promokodiki-admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADMITAD_PLUGIN_VERSION', '3.0.0' );
define( 'ADMITAD_PLUGIN_FILE', __FILE__ );
define( 'ADMITAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADMITAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ADMITAD_PLUGIN_DIR . 'includes/post-types.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/db.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/api.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-category-mapper.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-company-mapper.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/importer.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/migration.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/cli.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/token-manager.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/mapping-dashboard.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/companies-mapping-page.php';

/** Activate the plugin and create its durable data structures. */
function admitad_coupons_activate() {
	admitad_register_content_types();
	admitad_create_tables();
	admitad_schedule_events();
	flush_rewrite_rules();
}

/** Remove scheduled jobs without deleting imported content. */
function admitad_coupons_deactivate() {
	foreach ( admitad_cron_hooks() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'admitad_coupons_activate' );
register_deactivation_hook( __FILE__, 'admitad_coupons_deactivate' );

add_action( 'init', 'admitad_register_content_types', 0 );
add_action( 'init', 'admitad_schedule_events', 20 );

