<?php

/*
Plugin Name: Delete Duplicate Posts
Plugin Script: delete-duplicate-posts.php
Plugin URI: https://cleverplugins.com
Description: Remove duplicate blogposts on your blog! Searches and removes duplicate posts and their post meta tags. You can delete posts, pages and other Custom Post Types enabled on your website.
Version: 5.1
Author: cleverplugins.com
Author URI: https://cleverplugins.com
Min WP Version: 4.7
Max WP Version: 7.0
Text Domain: delete-duplicate-posts
Domain Path: /languages
*/
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
define( 'DDP_VERSION', '5.1' );
define( 'DDP_PLUGIN_FILE', __FILE__ );
define( 'DDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
require_once DDP_PLUGIN_DIR . 'vendor/autoload.php';
if ( function_exists( '\\DeleteDuplicatePosts\\ddp_fs' ) ) {
    \DeleteDuplicatePosts\ddp_fs()->set_basename( true, DDP_PLUGIN_FILE );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    if ( !function_exists( '\\DeleteDuplicatePosts\\ddp_fs' ) ) {
        /**
         * Create a helper function for easy SDK access.
         *
         * @return \Freemius
         */
        function ddp_fs() {
            global $ddp_fs;
            if ( !isset( $ddp_fs ) ) {
                // Activate multisite network integration.
                if ( !defined( 'WP_FS__PRODUCT_925_MULTISITE' ) ) {
                    define( 'WP_FS__PRODUCT_925_MULTISITE', true );
                }
                // Include Freemius SDK.
                // SDK is auto-loaded through composer.
                $ddp_fs = fs_dynamic_init( array(
                    'id'               => '925',
                    'slug'             => 'delete-duplicate-posts',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_0af9f9e83f00e23728a55430a57dd',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Pro',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug'       => 'delete-duplicate-posts',
                        'first-path' => 'tools.php?page=delete-duplicate-posts&welcome-message=true',
                        'contact'    => false,
                        'support'    => false,
                        'parent'     => array(
                            'slug' => 'tools.php',
                        ),
                    ),
                    'is_live'          => true,
                ) );
            }
            return $ddp_fs;
        }

        // Init Freemius.
        ddp_fs();
        // Signal that SDK was initiated.
        do_action( 'ddp_fs_loaded' );
    }
    ddp_fs()->add_action( 'after_uninstall', 'ddp_fs_uninstall_cleanup' );
    /**
     * Cleans up when uninstalling
     *
     * @return void
     */
    function ddp_fs_uninstall_cleanup() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ddp_log' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ddp_redirects' );
        delete_option( 'ddp_deleted_duplicates' );
        delete_option( 'delete_duplicate_posts_options_v4' );
    }

    new DDP_Plugin();
}