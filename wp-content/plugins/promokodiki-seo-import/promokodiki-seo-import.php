<?php
/**
 * Plugin Name: Promokodiki SEO Import
 * Description: Safe one-time Yoast SEO import and indexability rules.
 * Version: 1.0.0
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;
define( 'PROMOKODIKI_SEO_IMPORT_DIR', plugin_dir_path( __FILE__ ) );
require_once PROMOKODIKI_SEO_IMPORT_DIR . 'includes/class-seo-dataset.php';
require_once PROMOKODIKI_SEO_IMPORT_DIR . 'includes/class-indexability.php';
Promokodiki_SEO_Indexability::register();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PROMOKODIKI_SEO_IMPORT_DIR . 'includes/class-seo-command.php';
	WP_CLI::add_command( 'promokodiki seo', 'Promokodiki_SEO_Command' );
}
