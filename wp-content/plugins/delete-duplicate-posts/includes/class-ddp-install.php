<?php

/**
 * Installation and database setup.
 *
 * @package DeleteDuplicatePosts
 */
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class DDP_Install {
    /**
     * add_cron_intervals.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @param   mixed   $schedules
     * @return  mixed
     */
    public static function add_cron_intervals( $schedules ) {
        $schedules['5min'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 minutes', 'delete-duplicate-posts' ),
        );
        $schedules['10min'] = array(
            'interval' => 600,
            'display'  => __( 'Every 10 minutes', 'delete-duplicate-posts' ),
        );
        $schedules['15min'] = array(
            'interval' => 900,
            'display'  => __( 'Every 15 minutes', 'delete-duplicate-posts' ),
        );
        $schedules['30min'] = array(
            'interval' => 1800,
            'display'  => __( 'Every 30 minutes', 'delete-duplicate-posts' ),
        );
        return $schedules;
    }

    /**
     * Create plugin tables
     *
     * @author  Lars Koudal
     * @author  Unknown
     * @since   v0.0.1
     * @version v1.0.0  Monday, January 11th, 2021.
     * @version v1.0.1  Sunday, July 17th, 2022.
     * @version v1.0.2  Sunday, December 3rd, 2023.
     * @access  public static
     * @return  void
     */
    public static function create_table() {
        global $wpdb, $ddp_fs;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $log_table_name = $wpdb->prefix . 'ddp_log';
        $sql_log = "CREATE TABLE {$log_table_name} (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tid bigint(20) NOT NULL AUTO_INCREMENT,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tdatime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tnote tinytext NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tPRIMARY KEY  (id)\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        dbDelta( $sql_log );
        $redirects_table_name = $wpdb->prefix . 'ddp_redirects';
        $sql_redirects = "CREATE TABLE {$redirects_table_name} (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tid bigint(20) NOT NULL AUTO_INCREMENT,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tdatime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tinurl varchar(1024) NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\ttargeturl varchar(1024) NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\thttpcode varchar(3) DEFAULT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tPRIMARY KEY  (id)\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        dbDelta( $sql_redirects );
        // Additional plugin setup
        $options = DDP_Settings::get_options();
        DDP_Settings::save_options( $options );
        wp_clear_scheduled_hook( 'ddp_cron' );
        DDP_Logger::log( __( 'Plugin activated.', 'delete-duplicate-posts' ) );
    }

    /**
     * Install routines - create database and default options
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @param   mixed   $network_wide
     * @return  void
     */
    public static function install( $network_wide ) {
        global $wpdb;
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        if ( is_multisite() && $network_wide ) {
            // Get all blogs in the network and activate plugin on each one
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
            foreach ( $blog_ids as $blog_id ) {
                switch_to_blog( $blog_id );
                DDP_Install::create_table();
                restore_current_blog();
            }
        } else {
            DDP_Install::create_table();
        }
    }

    /**
     * Creating table when a new blog is created
     * https://sudarmuthu.com/blog/how-to-properly-create-tables-in-wordpress-multisite-plugins/
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @version v1.0.1  Sunday, May 14th, 2023.
     * @access  public static
     * @param   mixed   $new_site
     * @return  void
     */
    public static function on_create_blog( $new_site ) {
        if ( is_plugin_active_for_network( 'delete-duplicate-posts/delete-duplicate-posts.php' ) ) {
            switch_to_blog( $new_site->blog_id );
            DDP_Install::create_table();
            restore_current_blog();
        }
    }

    /**
     * Deleting the table whenever a blog is deleted
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @param   mixed   $tables
     * @return  mixed
     */
    public static function on_delete_blog( $tables ) {
        global $wpdb, $ddp_fs;
        $tables[] = $wpdb->prefix . 'ddp_log';
        return $tables;
    }

}
