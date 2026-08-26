<?php

/**
 * Main plugin bootstrap class.
 *
 * @package DeleteDuplicatePosts
 */
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class DDP_Plugin {
    public function __construct() {
        if ( function_exists( 'ddp_fs' ) ) {
            ddp_fs()->add_filter( 'permission_list', array(DDP_Settings::class, 'add_freemius_extra_permission') );
        }
        add_action(
            'admin_head',
            array(DDP_Admin::class, 'set_custom_help_content'),
            1,
            2
        );
        DDP_Settings::get_options();
        add_action( 'wp_ajax_ddp_get_loglines', array(DDP_Logger::class, 'return_loglines_ajax') );
        add_action( 'wp_ajax_ddp_get_duplicates', array(DDP_Duplicates::class, 'return_duplicates_ajax') );
        add_action( 'init', array(__CLASS__, 'do_init') );
        add_action( 'wp_ajax_ddp_delete_duplicates', array(DDP_Duplicates::class, 'delete_duplicates_ajax') );
        add_action( 'wp_ajax_ddp_dismiss_notice', array(DDP_Admin::class, 'dismiss_notice_ajax') );
        add_action( 'admin_menu', array(DDP_Admin::class, 'admin_menu_link') );
        add_action( 'admin_enqueue_scripts', array(DDP_Admin::class, 'admin_enqueue_scripts') );
        add_action(
            'wp_insert_site',
            array(DDP_Install::class, 'on_create_blog'),
            99999,
            1
        );
        add_filter( 'wpmu_drop_tables', array(DDP_Install::class, 'on_delete_blog') );
        register_activation_hook( DDP_PLUGIN_FILE, array(DDP_Install::class, 'install') );
        add_action( 'ddp_cron', array(DDP_Duplicates::class, 'cleandupes') );
        add_action( 'cron_schedules', array(DDP_Install::class, 'add_cron_intervals') );
    }

    /**
     * do_init.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Monday, October 28th, 2024.
     * @access  public static
     * @return  void
     */
    public static function do_init() {
        global $ddp_fs;
        $determine_locale = determine_locale();
    }

}
