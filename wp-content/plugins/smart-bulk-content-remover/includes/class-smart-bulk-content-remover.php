<?php

if (!class_exists('abdfw_bulk_delete')) {

    class abdfw_bulk_delete {

        public $wpdb;

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
            add_action('admin_menu', array($this, 'abdfw_add_admin_menu'));
            add_filter( 'plugin_action_links_' . ABDFW_PLUGIN_BASENAME, array( $this, 'abdfwp_add_settings_link' ) );            
            add_filter( 'cron_schedules', array( $this, 'abdfw_add_cron_schedules' ) );
            add_action( 'init', array( $this, 'abdfw_maybe_schedule_page_cleanup' ) );
            add_action( 'abdfw_run_scheduled_page_cleanup', array( $this, 'abdfw_run_scheduled_page_cleanup' ) );
        }

        public function abdfw_init(){
            // enqueued script
            add_action('admin_enqueue_scripts', array($this, 'abdfw_enqueue_scripts'));

            add_action('wp_ajax_abdfw_custom_delete_all_pages', array($this, 'abdfw_custom_delete_all_pages'));

            add_action('wp_ajax_abdfw_delete_post_types', array($this,'abdfw_delete_post_types_callback'));

            add_action( 'wp_ajax_abdfw_delete_all_comments', array($this ,'abdfw_delete_all_comments'));

            add_action( 'wp_ajax_abdfw_delete_all_media', array($this ,'abdfw_delete_all_media'));
            add_action('wp_ajax_abdfw_get_image_count_by_date', array($this, 'abdfw_get_image_count_by_date_callback'));            
            add_action('wp_ajax_abdfw_delete_images', array($this, 'abdfw_delete_images_callback'));
            add_action('wp_ajax_abdfw_fetch_images_by_month', array($this, 'abdfw_fetch_images_by_month'));           
            add_action('wp_ajax_abdfw_get_image_count_by_year', array($this,'abdfw_get_image_count_by_year') );            
            add_action('wp_ajax_abdfw_get_images_by_author', array($this ,'abdfw_get_images_by_author' ) );            
            add_action('wp_ajax_abdfw_get_images_by_month_year', array($this, 'abdfw_get_images_by_month_year'));            
            add_action('wp_ajax_abdfw_delete_media_by_author', array ($this , 'abdfw_delete_media_by_author_callback') );
            add_action('wp_ajax_abdfw_delete_media_by_month_year', array($this, 'abdfw_delete_media_by_month_year_callback'));
            add_action('wp_ajax_abdfw_delete_images_between_dates', array($this ,'abdfw_delete_images_between_dates_callback') );
            add_action('wp_ajax_abdfw_delete_all_unattached_images', array($this ,'abdfw_delete_all_unattached_images_callback'));
            add_action('wp_ajax_abdfw_delete_all_attached_images', array ($this ,'abdfw_delete_all_attached_images_callback') );
            add_action('wp_ajax_abdfw_delete_media_by_year', array($this , 'abdfw_delete_media_by_year_callback') );
            add_action('wp_ajax_abdfw_delete_all_images', array($this, 'abdfw_delete_all_images_callback'));
            add_action( 'wp_ajax_abdfw_download_all_images', array($this, 'abdfw_download_all_images'));
            add_action( 'wp_ajax_abdfw_download_attached_images', array($this, 'abdfw_download_attached_images' ) );
            add_action( 'wp_ajax_abdfw_download_unattached_images', array($this,'abdfw_download_unattached_images' ) );
            add_action('wp_ajax_abdfw_download_media_by_author', array($this, 'abdfw_download_media_by_author'));
            add_action('wp_ajax_abdfw_download_images_between_dates', array($this, 'abdfw_download_images_between_dates_callback'));
            add_action('wp_ajax_abdfw_download_images_by_month_year', array($this, 'abdfw_download_images_by_month_year'));
            add_action('wp_ajax_abdfw_download_media_by_years', array($this, 'abdfw_download_media_by_years'));
            add_action('wp_ajax_abdfw_download_author_images_callback', array($this, 'abdfw_download_author_images_callback'));         
            add_action('wp_ajax_abdfw_delete_selected_files', array($this, 'abdfw_delete_selected_files_callback'));
            add_action('wp_ajax_abdfw_download_selected_files', array($this ,'abdfw_download_selected_files_callback'));      

            // page filter
            add_action( 'wp_ajax_abdfw_load_pages', [ $this, 'abdfw_load_pages' ] );
            add_action( 'wp_ajax_abdfw_delete_pages', [ $this, 'abdfw_delete_pages' ] );

            // post filter
            add_action( 'wp_ajax_abdfw_post_load_posts', [ $this, 'abdfw_post_load_posts' ] );
            add_action( 'wp_ajax_abdfw_post_delete_posts', [ $this, 'abdfw_post_delete_posts' ] );

            // comments filter
            add_action( 'wp_ajax_abdfw_load_comments', [ $this, 'abdfw_load_comments' ] );
            add_action( 'wp_ajax_abdfw_delete_comments', [ $this, 'abdfw_delete_comments' ] );

            // page schedule
            add_action( 'wp_ajax_abdfw_save_page_cleanup_schedule', [ $this, 'abdfw_save_page_cleanup_schedule' ] );

        } 

        public function abdfw_enqueue_scripts() {
            // Check if jQuery is not already enqueued
            if (!wp_script_is('jquery', 'enqueued')) { wp_enqueue_script('jquery'); }

            // Check if jQuery UI Datepicker is not already enqueued
            if (!wp_script_is('jquery-ui-datepicker', 'enqueued')) { 
                // Enqueue jQuery UI Datepicker
                wp_enqueue_script('jquery-ui-datepicker');
            }

            wp_enqueue_script(
                'abdfw-validation-js',
                esc_url(ABDFW_PLUGIN_DIR . 'assets/js/jquery.validate.min.js'),
                array('jquery'),
                ABDFW_VERSION,
                true
            );

            wp_enqueue_script(
                'abdfw-custom-js',
                esc_url(ABDFW_PLUGIN_DIR . 'assets/js/custom.js'),
                array('jquery'),
                ABDFW_VERSION,
                true
            );

            wp_localize_script( 'abdfw-custom-js', 'abdfw_ajax_object', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'abdfw_nonce' ),
            ] );

            wp_enqueue_style(
                'abdfw-custom-css',
                esc_url(ABDFW_PLUGIN_DIR . 'assets/css/custom.css')
            );
        }

        public function abdfw_add_admin_menu() {
            // Main menu page (redirects to Page Settings by default)
            add_menu_page(
                __('Smart Bulk Content Remover', 'smart-bulk-content-remover'),
                __('Smart Bulk Content Remover', 'smart-bulk-content-remover'),
                'manage_options',
                'smart-bulk-content-remover', // parent slug
                array($this, 'abdfw_render_page_settings'),
                'dashicons-trash',
                60
            );

            // Submenu: Page Settings
            add_submenu_page(
                'smart-bulk-content-remover', // must match parent slug
                __('Page Settings', 'smart-bulk-content-remover'),
                __('Page Settings', 'smart-bulk-content-remover'),
                'manage_options',
                'smart-bulk-content-remover',
                array($this, 'abdfw_render_page_settings')
            );

            // Submenu: Post Settings
            add_submenu_page(
                'smart-bulk-content-remover',
                __('Post Settings', 'smart-bulk-content-remover'),
                __('Post Settings', 'smart-bulk-content-remover'),
                'manage_options',
                'smart-bulk-content-remover-post',
                array($this, 'abdfw_render_post_settings')
            );

            // Submenu: Media Settings
            add_submenu_page(
                'smart-bulk-content-remover',
                __('Media Settings', 'smart-bulk-content-remover'),
                __('Media Settings', 'smart-bulk-content-remover'),
                'manage_options',
                'smart-bulk-content-remover-media',
                array($this, 'abdfw_render_media_settings')
            );

            // Submenu: Comments Settings
            add_submenu_page(
                'smart-bulk-content-remover',
                __('Comments Settings', 'smart-bulk-content-remover'),
                __('Comments Settings', 'smart-bulk-content-remover'),
                'manage_options',
                'smart-bulk-content-remover-comments',
                array($this, 'abdfw_render_comment_settings')
            );
        }

        /**
         * Add settings link on plugin page
         */
        function abdfwp_add_settings_link( $links ) {
            $settings_link = '<a href="' . admin_url( 'admin.php?page=smart-bulk-content-remover' ) . '">' . __( 'Settings', 'smart-bulk-content-remover' ) . '</a>';
            array_unshift( $links, $settings_link );
            return $links;
        }

        function abdfw_render_page_settings() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Smart Bulk Delete & Content Cleaner for WordPress', 'smart-bulk-content-remover'); ?><span class="abdfw-version">
                    <?php echo esc_html( 'v' . ABDFW_VERSION ); ?>
                </span></h1>
                <h2><?php esc_html_e('Page Settings', 'smart-bulk-content-remover'); ?></h2>
                <!-- Your "page" tab HTML here -->
                <div id="abdfw-page-settings" class="tab-content">
                    <div class="abdfw-delete-pages">
                        <form method="post" class="abdfw-pages-frm">
                            <?php 
                            wp_nonce_field( 'custom_delete_all_pages', 'custom_delete_all_pages_nonce' );
                                $pages_count = wp_count_posts('page')->publish; 
                            ?>
                            <table class="form-table">
                                <tr>
                                    <th>
                                        <label for="abdfw_delete_all_pages">
                                            <?php echo esc_html__('Delete All Pages?', 'smart-bulk-content-remover'); ?>
                                            <?php echo esc_html__('(', 'smart-bulk-content-remover'); ?>
                                            <?php echo esc_html($pages_count); ?> <?php echo esc_html__('Pages )', 'smart-bulk-content-remover'); ?>
                                                
                                            </label>
                                    </th>
                                    <td>
                                        <label class="abdfw_switch" for="abdfw_delete_all_pages">
                                            <input type="checkbox" id="abdfw_delete_all_pages" name="abdfw_delete_all_pages" />
                                            <span class="abdfw_slider abdfw_round"></span>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <input type="submit" name="abdfw_submit_delete_all_pages" value="Delete Pages" class="button-primary"/>
                        </form>
                    </div> 

                    <?php
                    $page_schedule = get_option( 'abdfw_page_cleanup_schedule', [] );
                    $schedule_enabled = ! empty( $page_schedule['enabled'] );
                    $schedule_frequency = $page_schedule['frequency'] ?? 'daily';
                    $schedule_time = $page_schedule['time'] ?? '02:00';
                    $schedule_status = $page_schedule['status'] ?? '';
                    $schedule_author = isset( $page_schedule['author'] ) ? (int) $page_schedule['author'] : 0;
                    $schedule_from = $page_schedule['from'] ?? '';
                    $schedule_to = $page_schedule['to'] ?? '';
                    $schedule_search = $page_schedule['search'] ?? '';
                    $schedule_permanent = ! empty( $page_schedule['permanent'] );
                    $next_run = wp_next_scheduled( 'abdfw_run_scheduled_page_cleanup' );
                    $schedule_authors = get_users( [
                        'capability' => 'edit_posts',
                    ] );
                    ?>

                    <div class="abdfw-page-schedule">
                        <h2><?php esc_html_e( 'Schedule Automatic Cleanup', 'smart-bulk-content-remover' ); ?></h2>
                        <form id="abdfw-page-schedule-form">
                            <?php wp_nonce_field( 'abdfw_page_schedule', 'abdfw_page_schedule_nonce' ); ?>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e( 'Enable Schedule', 'smart-bulk-content-remover' ); ?></th>
                                    <td>
                                        <label class="abdfw_switch" for="abdfw_page_schedule_enabled">
                                            <input type="checkbox" id="abdfw_page_schedule_enabled" <?php checked( $schedule_enabled ); ?> />
                                            <span class="abdfw_slider abdfw_round"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="abdfw_page_schedule_frequency"><?php esc_html_e( 'Frequency', 'smart-bulk-content-remover' ); ?></label></th>
                                    <td>
                                        <select id="abdfw_page_schedule_frequency">
                                            <option value="daily" <?php selected( $schedule_frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="weekly" <?php selected( $schedule_frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="monthly" <?php selected( $schedule_frequency, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'smart-bulk-content-remover' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="abdfw_page_schedule_time"><?php esc_html_e( 'Run Time', 'smart-bulk-content-remover' ); ?></label></th>
                                    <td>
                                        <input type="time" id="abdfw_page_schedule_time" value="<?php echo esc_attr( $schedule_time ); ?>" />
                                        <p class="description"><?php esc_html_e( 'Uses site timezone.', 'smart-bulk-content-remover' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="abdfw_page_schedule_status"><?php esc_html_e( 'Status', 'smart-bulk-content-remover' ); ?></label></th>
                                    <td>
                                        <select id="abdfw_page_schedule_status">
                                            <option value="" <?php selected( $schedule_status, '' ); ?>><?php esc_html_e( 'All Statuses', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="publish" <?php selected( $schedule_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="draft" <?php selected( $schedule_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="pending" <?php selected( $schedule_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'smart-bulk-content-remover' ); ?></option>
                                            <option value="trash" <?php selected( $schedule_status, 'trash' ); ?>><?php esc_html_e( 'Trash', 'smart-bulk-content-remover' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="abdfw_page_schedule_author"><?php esc_html_e( 'Author', 'smart-bulk-content-remover' ); ?></label></th>
                                    <td>
                                        <select id="abdfw_page_schedule_author">
                                            <option value="" <?php selected( $schedule_author, 0 ); ?>><?php esc_html_e( 'All Authors', 'smart-bulk-content-remover' ); ?></option>
                                            <?php foreach ( $schedule_authors as $a ) : ?>
                                                <option value="<?php echo esc_attr( $a->ID ); ?>" <?php selected( $schedule_author, $a->ID ); ?>><?php echo esc_html( $a->display_name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="abdfw_page_schedule_search"><?php esc_html_e( 'Title Contains', 'smart-bulk-content-remover' ); ?></label></th>
                                    <td>
                                        <input type="text" id="abdfw_page_schedule_search" value="<?php echo esc_attr( $schedule_search ); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Date Range', 'smart-bulk-content-remover' ); ?></th>
                                    <td>
                                        <label for="abdfw_page_schedule_from"><?php esc_html_e( 'From:', 'smart-bulk-content-remover' ); ?></label>
                                        <input type="date" id="abdfw_page_schedule_from" value="<?php echo esc_attr( $schedule_from ); ?>" />
                                        <label for="abdfw_page_schedule_to"><?php esc_html_e( 'To:', 'smart-bulk-content-remover' ); ?></label>
                                        <input type="date" id="abdfw_page_schedule_to" value="<?php echo esc_attr( $schedule_to ); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Delete Mode', 'smart-bulk-content-remover' ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="abdfw_page_schedule_permanent" <?php checked( $schedule_permanent ); ?> />
                                            <?php esc_html_e( 'Permanently Delete (skip Trash)', 'smart-bulk-content-remover' ); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Schedule', 'smart-bulk-content-remover' ); ?></button>
                                <span id="abdfw_page_schedule_message" class="abdfw-status"></span>
                            </p>
                            <?php if ( $next_run ) : ?>
                                <p class="description">
                                    <?php echo esc_html( sprintf( __( 'Next run: %s', 'smart-bulk-content-remover' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ) ); ?>
                                </p>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Advance page delete html -->
                    <div class="abdfw-page-main">
                        <h2><?php esc_html_e( 'Advanced Page Remover', 'smart-bulk-content-remover' ); ?></h2>

                        <div id="abdfw-page-filters">
                            <input type="text" id="abdfw-page-search" placeholder="Search title…">
                            <select id="abdfw-page-status">
                                <option value=""><?php esc_html_e( 'All Statuses', 'smart-bulk-content-remover' ); ?></option>
                                <option value="publish"><?php esc_html_e( 'Published', 'smart-bulk-content-remover' ); ?></option>
                                <option value="draft"><?php esc_html_e( 'Draft', 'smart-bulk-content-remover' ); ?></option>
                                <option value="pending"><?php esc_html_e( 'Pending', 'smart-bulk-content-remover' ); ?></option>
                                <option value="trash"><?php esc_html_e( 'Trash', 'smart-bulk-content-remover' ); ?></option>
                            </select>
                            <?php
                            $authors = get_users( [
                                'capability' => 'edit_posts', // ✅ replaces 'who' => 'authors'
                            ] );
                            ?>
                            <select id="abdfw-page-author">
                                <option value=""><?php esc_html_e( 'All Authors', 'smart-bulk-content-remover' ); ?></option>
                                <?php 
                                foreach ( $authors as $a ) {
                                    echo '<option value="' . esc_attr( $a->ID ) . '">' . esc_html( $a->display_name ) . '</option>';
                                }
                                ?>
                            </select>
                            <label for="abdfw-page-from"><?php esc_html_e( 'From:', 'smart-bulk-content-remover' ); ?><input type="date" id="abdfw-page-from"></label>
                            <label for="abdfw-page-to"><?php esc_html_e( 'To:', 'smart-bulk-content-remover' ); ?><input type="date" id="abdfw-page-to"></label>
                            
                            <button id="abdfw-load-pages" class="button button-primary"><?php esc_html_e( 'Load Pages', 'smart-bulk-content-remover' ); ?></button>
                        </div>

                        <form id="abdfw-page-form">                            
                            <div id="abdfw-page-list"></div>
                            <p>
                            <label>
                                <input type="checkbox" id="abdfw-page-permanent"> 
                                <?php esc_html_e( 'Permanently Delete (skip Trash)', 'smart-bulk-content-remover' ); ?>
                            </label>
                            </p>
                            <button type="submit" class="button button-secondary"><?php esc_html_e( 'Delete Selected', 'smart-bulk-content-remover' ); ?></button>
                        </form>
                    </div>   

                </div>
            </div>
            <?php
        }

        function abdfw_render_post_settings() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Smart Bulk Delete & Content Cleaner for WordPress', 'smart-bulk-content-remover'); ?><span class="abdfw-version">
                    <?php echo esc_html( 'v' . ABDFW_VERSION ); ?>
                </span></h1> 
                <h2><?php esc_html_e('Post Settings', 'smart-bulk-content-remover'); ?></h2>
                <!-- Your "post" tab HTML here -->
                <div id="abdfw-post-settings" class="tab-content">
                    <div class="abdfw-delete-post-types">
                        <form id="abdfw-delete-post-types-form" method="post" action="">
                        <?php
                        wp_nonce_field('custom_delete_post_types', 'custom_delete_post_types_nonce');              
                        // Get all post types including default post type ('post') and excluding attachment and pages
                        $post_types = get_post_types(array('public' => true), 'names', 'and');
                        ?>
                        <table class="form-table">
                            <?php 
                            foreach ($post_types as $post_type) {
                                // Skip the 'attachment' and 'page' post types
                                if ($post_type === 'attachment' || $post_type === 'page') {
                                    continue;
                                }
                                $post_count = wp_count_posts($post_type)->publish; // Get count of published posts for the current post type
                                ?>                            
                                <tr>
                                    <th>
                                        <label for="delete_<?php echo esc_attr($post_type); ?>">
                                            <?php echo esc_html(ucfirst($post_type)); ?> <?php echo esc_html__('(', 'smart-bulk-content-remover'); ?><?php echo esc_html($post_count); ?> <?php echo esc_html__('Posts)', 'smart-bulk-content-remover'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <label class="abdfw_switch" for="delete_<?php echo esc_attr($post_type); ?>">
                                            <input type="checkbox" name="post_types[]" id="delete_<?php echo esc_attr($post_type); ?>" value="<?php echo esc_attr($post_type); ?>">
                                            <span class="abdfw_slider abdfw_round"></span>
                                        </label>
                                    </td>
                                </tr>                   
                                <?php
                            }
                        ?>
                        </table>
                        <button type="button" id="abdfw_delete_post_types_button" class="button-primary"><?php echo esc_html__('Delete Post Type Data','smart-bulk-content-remover'); ?></button>
                    </form>
                    <div class="abdfw_post_form_main">
                        <h2><?php esc_html_e( 'Advanced Post Remover', 'smart-bulk-content-remover' ); ?></h2>
                            <form id="abdfw_post_form">

                                <!-- NEW: Post type dropdown -->
                                <select name="post_type">
                                    <?php
                                    // Get all public post types (posts, pages, products, etc.)
                                    $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                    foreach ( $post_types as $pt ) {
                                        printf(
                                            '<option value="%s">%s</option>',
                                            esc_attr( $pt->name ),
                                            esc_html( $pt->labels->singular_name )
                                        );
                                    }
                                    ?>
                                </select>

                                <input type="text" name="search" placeholder="Search content">

                                <select name="status">
                                    <option value=""><?php esc_html_e( 'All Statuses', 'smart-bulk-content-remover' ); ?></option>
                                    <option value="publish"><?php esc_html_e( 'Published', 'smart-bulk-content-remover' ); ?></option>
                                    <option value="draft"><?php esc_html_e( 'Draft', 'smart-bulk-content-remover' ); ?></option>
                                    <option value="pending"><?php esc_html_e( 'Pending', 'smart-bulk-content-remover' ); ?></option>
                                    <option value="trash"><?php esc_html_e( 'Trash', 'smart-bulk-content-remover' ); ?></option>
                                </select>

                                <?php
                                wp_dropdown_users( [
                                    'name'             => 'author',
                                    'show_option_all'  => 'All Authors',
                                    'selected'         => 0,
                                ] );
                                ?>

                                <label for="abdfw-page-from">
                                    <?php esc_html_e( 'From:', 'smart-bulk-content-remover' ); ?>
                                </label>
                                <input type="date" id="abdfw-page-from" name="from" />

                                <label for="abdfw-page-to">
                                    <?php esc_html_e( 'To:', 'smart-bulk-content-remover' ); ?>
                                </label>
                                <input type="date" id="abdfw-page-to" name="to" />

                                <button class="button button-primary" id="abdfw_post_load" type="button">
                                    <?php esc_html_e( 'Load Posts', 'smart-bulk-content-remover' ); ?>
                                </button>

                            </form>

                            <form id="abdfw_post_delete_form">
                                <div id="abdfw_post_results"></div>
                                <p>
                                    <label>
                                        <input type="checkbox" name="permanent" value="1"> Delete permanently (skip trash)
                                    </label>
                                </p>
                                <button class="button button-secondary" id="abdfw_post_delete">Delete Selected</button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
            <?php
        }

        function abdfw_render_media_settings() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Smart Bulk Delete & Content Cleaner for WordPress', 'smart-bulk-content-remover'); ?><span class="abdfw-version">
                    <?php echo esc_html( 'v' . ABDFW_VERSION ); ?>
                </span></h1>
                <h2><?php esc_html_e('Media Settings', 'smart-bulk-content-remover'); ?></h2>
                <!-- Your "media" tab HTML here -->
                <div id="abdfw-media-settings" class="tab-content">
                    <div class="abdfw-media-delete">
                        <form id="abdfw-delete-post-types-form">
                            <?php wp_nonce_field('delete_media_nonce', 'delete_media_nonce'); ?>
                            <table class="form-table">
                                <tr>
                                    <th>
                                        <label for="abdfw_deleteAllMedia">
                                            <?php echo esc_html__('Delete All Media Files', 'smart-bulk-content-remover'); ?>
                                            <?php 
                                            global $wpdb;
                                            // Query to get the count of all attachments
                                            $media_count = $wpdb->get_var("
                                                SELECT COUNT(ID) 
                                                FROM {$wpdb->posts} 
                                                WHERE post_type = 'attachment' 
                                                AND post_mime_type LIKE 'image%'
                                            ");
                                            echo esc_html__('(', 'smart-bulk-content-remover'); ?>
                                            <?php echo esc_html($media_count); ?> <?php echo esc_html__('Media)', 'smart-bulk-content-remover'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <label class="abdfw_switch" for="abdfw_deleteAllMedia">
                                            <input type="checkbox" id="abdfw_deleteAllMedia">
                                            <span class="abdfw_slider abdfw_round"></span>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" id="abdfw_delete_media_button" class="button-secondary"><?php echo esc_html__('Delete Media','smart-bulk-content-remover'); ?></button>
                        </form>
                        <?php
                            // Get the global $wpdb object
                            global $wpdb;
                            // Query to get the count of all attachments
                            $total_attachments_count = $wpdb->get_var("
                                SELECT COUNT(ID) 
                                FROM {$wpdb->posts} 
                                WHERE post_type = 'attachment' 
                                AND post_mime_type LIKE 'image%'
                            ");
                            // Query to get the count of attached images
                            $attached_images_count = $wpdb->get_var("
                                SELECT COUNT(ID) 
                                FROM {$wpdb->posts} 
                                WHERE post_type = 'attachment' 
                                AND post_parent > 0
                            ");
                            // Query to get the sum of sizes of all attachments
                            $total_images_size_bytes = $wpdb->get_var("
                                SELECT SUM(meta_value) 
                                FROM {$wpdb->postmeta} 
                                WHERE meta_key = '_wp_attached_file'
                            ");
                            // Query to get the sum of sizes of attached images
                            $attached_images_size_bytes = $wpdb->get_var("
                                SELECT SUM(pm.meta_value) 
                                FROM {$wpdb->posts} p
                                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                                WHERE p.post_type = 'attachment'
                                AND p.post_parent > 0
                                AND pm.meta_key = '_wp_attached_file'
                            ");
                            // Calculate the count of unattached images
                            $unattached_images_count = $total_attachments_count - $attached_images_count;
                            // Calculate the size of unattached images
                            $unattached_images_size_bytes = $total_images_size_bytes - $attached_images_size_bytes;
                            // Convert bytes to gigabytes
                            $total_images_size_gb = $total_images_size_bytes / (1024 * 1024 * 1024);
                            $attached_images_size_gb = $attached_images_size_bytes / (1024 * 1024 * 1024);
                            $unattached_images_size_gb = $unattached_images_size_bytes / (1024 * 1024 * 1024);
                            ?>
                            <div class="abdfw-images-main-wrapper">
                            <h2 class="abdfw-images-main-title"><?php esc_html_e( 'Advanced Media Remover', 'smart-bulk-content-remover' ); ?></h2>
                            <div class="abdfw-images-main">
                            <?php
                            // Output the counts                        
                            if($total_attachments_count > 0) { ?>
                            <div class="abdfw-total-images-wrapper">
                                <div class="abdfw-total-images">
                                    <?php 
                                    $abdfw_bulk_delete = new abdfw_bulk_delete();
                                    $total_image_size = $abdfw_bulk_delete->abdfw_get_total_image_size();
                                    ?>
                                    <table class="form-table">
                                        <tr>
                                            <th><?php echo esc_html__('#Total Number Of Images:', 'smart-bulk-content-remover'); ?></th>
                                            <td><?php echo esc_html($total_attachments_count); ?></td>
                                        </tr>                                
                                        <tr>
                                            <th><?php echo esc_html__('Total Size Of All Images: ','smart-bulk-content-remover'); ?></th>
                                            <td><?php echo esc_html($total_image_size); ?></td>
                                        </tr>
                                    </table>
                                    <input type="submit" value="Download" id="abdfw-download-all-images" class="button-primary">
                                    <input type="submit" value="Delete All" id="abdfw-delete-all-images" class="button-secondary"> <?php wp_nonce_field('delete_all_images_nonce', 'delete_all_images_nonce_field'); ?>
                                </div>
                                <!-- Popup container -->
                                <div id="abdfw-show-all-image-popup" class="image_popup" style="display: none;">
                                    <button id="abdfw-all-image-close-popup" class="close-icon"><?php echo esc_html__('Close','smart-bulk-content-remover'); ?></button>
                                    <div id="abdfw-image-list"></div>
                                </div>
                            </div>
                                <?php
                            }                        
                            if($attached_images_count > 0){ ?>
                            <div class="abdfw-attached-images-wrapper">
                                <div class="abdfw-attached-images">
                                    <?php 
                                    $abdfw_bulk_delete = new abdfw_bulk_delete();
                                    $total_attached_size = $abdfw_bulk_delete->abdfw_get_total_attached_image_size();
                                    ?>
                                    <table class="form-table">
                                        <tr>
                                            <th><?php echo esc_html__('#Number Of Attached Images: ','smart-bulk-content-remover'); ?></th>
                                            <td><?php echo esc_html($attached_images_count); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php echo esc_html__('Total Size Of Attached images: ','smart-bulk-content-remover'); ?></th>
                                            <td><?php echo esc_html($total_attached_size);?></td>
                                        </tr>
                                    </table>
                                    <input type="submit" value="Download" id="abdfw-download-all-attached-images" class="button-primary">
                                    <input type="submit" value="Delete Attached" id="abdfw-delete-all-attached-images" class="button-secondary">                  
                                    <?php 
                                    wp_nonce_field('delete_all_attached_images_nonce', 'delete_all_attached_images_nonce_field');
                                    ?>
                                </div>
                                <!-- Popup container -->
                                <div id="abdfw-show-attached-image-popup" class="image_popup" style="display: none;">
                                    <button id="abdfw-attached-image-close-popup" class="close-icon"></button>
                                    <div id="abdfw-attached-image-list"></div>
                                </div>
                            </div>
                                <?php
                            }
                            if($unattached_images_count > 0){ ?>
                                <div class="abdfw-unattached-images-wrapper">
                                    <div class="abdfw-unattached-images">
                                        <table class="form-table">
                                            <tr>
                                                <th><?php echo esc_html__('#Number Of Unattached Images: ','smart-bulk-content-remover'); ?></th>
                                                <td><?php echo esc_html($unattached_images_count);?></td>
                                            </tr>
                                            <tr>
                                            <?php 
                                            $abdfw_bulk_delete = new abdfw_bulk_delete();
                                            $total_unattached_size = $abdfw_bulk_delete->abdfw_get_total_unattached_image_size();
                                            ?>
                                            <tr>
                                                <th><?php echo esc_html__('Total Size Of Unattached images: ','smart-bulk-content-remover'); ?></th>
                                                <td><?php echo esc_html($total_unattached_size);?></td>
                                            </tr>
                                        </table>
                                        <input type="submit" value="Download" id="abdfw-download-all-unattached-images" class="button-primary">
                                        <input type="submit" value="Delete Unattached" id="abdfw-delete-all-unattached-images" class="button-secondary">                              
                                        <?php 
                                        wp_nonce_field('delete_all_unattached_images_nonce', 'delete_all_unattached_images_nonce_field');?>
                                    </div>
                                    <?php } ?>
                                    <!-- Popup container -->
                                    <div id="abdfw-show-unattached-image-popup" class="image_popup" style="display: none;">
                                        <button id="abdfw-unattached-image-close-popup" class="close-icon"><?php echo esc_html__('Close','smart-bulk-content-remover'); ?></button>
                                        <div id="abdfw-unattached-image-list"></div>
                                    </div>
                                </div>
                                <?php 
                                if($unattached_images_count > 0){ ?>
                            </div>
                        <?php } ?>
                        <div class="filter-date-wrapper">
                            <div class="filter-date-inner date-selector-wrapper">
                                <h2><?php echo esc_html__('Filter by Date Range','smart-bulk-content-remover'); ?></h2>
                                <form id="abdfw-date-range-form">
                                    <?php wp_nonce_field( 'date_images_nonce', 'date_images_nonce_field' ); ?>
                                    <div class="abdfw-from-date-wrap">
                                        <label for="abdfw-from-date"><?php echo esc_html__('From:','smart-bulk-content-remover'); ?></label>
                                        <input type="date" id="abdfw-from-date" name="smart-bulk-content-remover">
                                    </div>
                                    <div class="abdfw-abdfw-to-date-wrap">
                                        <label for="abdfw-abdfw-to-date"><?php echo esc_html__('To:','smart-bulk-content-remover'); ?></label>
                                        <input type="date" id="abdfw-to-date" name="abdfw-to-date">
                                    </div>
                                    <input type="submit" value="Apply" id="abdfw-submit-dates" class="button-primary">
                                    <?php //wp_nonce_field( 'delete_images_nonce', 'delete_images_nonce_field' ); ?>
                                </form>
                                <div id="abdfw-image-count-result"><p></p></div>
                                <!-- Popup container -->
                                <div id="abdfw-show-dates-image-popup" class="image_popup" style="display: none;">
                                    <button id="abdfw-dates-image-close-popup" class="close-icon"><?php echo esc_html__('Close','smart-bulk-content-remover'); ?></button>
                                    <div id="abdfw-dates-image-list"></div>
                                </div>
                            </div>                     
                            <?php 
                            $this->abdfw_custom_month_year_dropdown(); 
                            $this->abdfw_custom_year_dropdown();
                            $this->abdfw_display_authors_list();
                            ?>
                    </div>
                    </div>

                </div> 
            </div>
            <?php
        }

        public function abdfw_render_comment_settings() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Smart Bulk Delete & Content Cleaner for WordPress', 'smart-bulk-content-remover'); ?><span class="abdfw-version">
                    <?php echo esc_html( 'v' . ABDFW_VERSION ); ?>
                </span></h1>
                <h2><?php esc_html_e('Comments Settings', 'smart-bulk-content-remover'); ?></h2>
                <!-- Your "comments" tab HTML here -->
                <div id="abdfw-comment-settings" class="tab-content">           
                    <div class="abdfw-comments-delete">
                        <form id="abdfw-comments-post-types-form">
                            <?php wp_nonce_field('delete_comments_nonce', 'delete_comments_nonce');
                            $comment_count = wp_count_comments()->total_comments; ?>
                            <table class="form-table">
                                <tr>
                                    <th>
                                        <label for="abdfw_deleteAllComments">
                                            <?php echo esc_html('Delete All Comments?', 'smart-bulk-content-remover'); ?>
                                            <?php echo esc_html__('(', 'smart-bulk-content-remover'); ?><?php echo esc_html($comment_count); ?> <?php echo esc_html__('Comments)', 'smart-bulk-content-remover'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <label class="abdfw_switch" for="abdfw_deleteAllComments">
                                            <input type="checkbox" id="abdfw_deleteAllComments">
                                            <span class="abdfw_slider abdfw_round"></span>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" id="abdfw_delete_comments_button" class="button-primary"><?php echo esc_html('Delete Comments','smart-bulk-content-remover'); ?></button>
                        </form>
                    </div>   

                    <div class="abdfw_comment_main">
                        <h2><?php esc_html_e( 'Advanced Comment Remover', 'smart-bulk-content-remover' ); ?></h2>
                        <form id="abdfw-comment-filter-form">
                            <!-- Post type dropdown -->
                            <select id="abdfw-post-type" name="post_type">
                                <option value=""><?php esc_html_e( 'All Post Types', 'smart-bulk-content-remover' ); ?></option>
                                <?php
                                $pts = get_post_types( [ 'public' => true ], 'objects' );
                                foreach ( $pts as $pt ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $pt->name ); ?>">
                                        <?php echo esc_html( $pt->labels->singular_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text" name="search" placeholder="Search in comment content">
                            <select name="status">
                                <option value=""><?php esc_html_e( 'All Statuses', 'smart-bulk-content-remover' ); ?></option>
                                <option value="approve"><?php esc_html_e( 'Approved', 'smart-bulk-content-remover' ); ?></option>
                                <option value="hold"><?php esc_html_e( 'Pending', 'smart-bulk-content-remover' ); ?></option>
                                <option value="spam"><?php esc_html_e( 'Spam', 'smart-bulk-content-remover' ); ?></option>
                                <option value="trash"><?php esc_html_e( 'Trash', 'smart-bulk-content-remover' ); ?></option>
                            </select>

                            <?php
                            wp_dropdown_users( [
                                'name'             => 'author',
                                'show_option_all'  => 'All Comment Authors',
                                'selected'         => 0,
                            ] );
                            ?>

                            
                            <label for="abdfw-comment-from">
                                <?php esc_html_e( 'From:', 'smart-bulk-content-remover' ); ?>
                            </label>
                            <input type="date" id="abdfw-comment-from" name="from" />

                            <label for="abdfw-comment-to">
                                <?php esc_html_e( 'To:', 'smart-bulk-content-remover' ); ?>
                            </label>
                            <input type="date" id="abdfw-comment-to" name="to" />

                            <button class="button button-primary" id="abdfw-comment-load" type="button">
                                <?php esc_html_e( 'Load Comments', 'smart-bulk-content-remover' ); ?>
                            </button>

                        </form>

                        <form id="abdfw-comment-delete-form">
                            <div id="abdfw-comment-results"></div>
                            <p>
                                <label>
                                    <input type="checkbox" name="permanent" value="1">
                                    <?php esc_html_e( 'Delete permanently (skip Trash)', 'smart-bulk-content-remover' ); ?>
                                </label>
                            </p>
                            <button class="button button-secondary" id="abdfw-comment-delete"><?php esc_html_e( 'Delete Selected', 'smart-bulk-content-remover' ); ?></button>
                        </form>
                    </div>

                </div>
            </div>
            <?php
        }

        private function abdfw_calculate_directory_size($dir) {
            $size = 0;
            $dir_handle = opendir($dir);
            if ($dir_handle) {
                while (($file = readdir($dir_handle)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $path = $dir . '/' . $file;
                        if (is_file($path)) {
                            $size += filesize($path);
                        } elseif (is_dir($path)) {
                            $size += $this->abdfw_calculate_directory_size($path);
                        }
                    }
                }
                closedir($dir_handle);
            }
            return $size;
        }

        // Function to format size based on appropriate unit
        private function abdfw_format_size($size) {
            if ($size < 1024) {
                return number_format($size, 2) . ' ' . esc_html__('bytes', 'smart-bulk-content-remover');
            } elseif ($size < 1048576) {
                return number_format($size / 1024, 2) . ' ' . esc_html__('KB', 'smart-bulk-content-remover');
            } elseif ($size < 1073741824) {
                return number_format($size / 1048576, 2) . ' ' . esc_html__('MB', 'smart-bulk-content-remover');
            } else {
                return number_format($size / 1073741824, 2) . ' ' . esc_html__('GB', 'smart-bulk-content-remover');
            }
        }

        // Function to get total size of all images
        public function abdfw_get_total_image_size() {
            // Get the uploads directory path
            $uploads_dir = wp_upload_dir();
            $uploads_path = $uploads_dir['basedir'];
            // Calculate size of uploads directory
            $total_size = $this->abdfw_calculate_directory_size($uploads_path);
            // Format and return total size
            return $this->abdfw_format_size($total_size);
        }

        // Function to retrieve attached image IDs
        private function abdfw_get_attached_image_ids() {
            global $wpdb;
            // Query the database to retrieve attachment IDs of images with a parent post
            $attachment_ids = $wpdb->get_col("
                SELECT ID
                FROM $wpdb->posts
                WHERE post_type = 'attachment'
                AND post_parent > 0
                AND post_mime_type LIKE 'image%'
            ");
            return $attachment_ids;
        }

        // Function to calculate the size of an image file and all its sizes
        private function abdfw_calculate_image_size($attachment_id) {
            $total_size = 0;

            // Get the path to the original image file
            $file_path = get_attached_file($attachment_id);
            if ($file_path && is_file($file_path)) {
                $total_size += filesize($file_path); // Size of the original image
            }

            // Get the metadata for the image
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata && isset($metadata['sizes'])) {
                // Iterate through all image sizes
                foreach ($metadata['sizes'] as $size) {
                    $size_file = str_replace(basename($file_path), $size['file'], $file_path);
                    if (is_file($size_file)) {
                        $total_size += filesize($size_file);
                    }
                }
            }

            return $total_size;
        }

        // Function to calculate total size of all attached image files
        private function abdfw_calculate_attached_image_size() {
            // Get attached image IDs
            $attached_image_ids = $this->abdfw_get_attached_image_ids();
            // Initialize total size variable
            $total_size = 0;
            // Calculate size of each attached image file and add to total size
            foreach ($attached_image_ids as $attachment_id) {
                $total_size += $this->abdfw_calculate_image_size($attachment_id);
            }
            return $total_size;
        }

        // Function to get total size of all attached images
        public function abdfw_get_total_attached_image_size() {
            // Calculate total size of attached image files
            $total_size = $this->abdfw_calculate_attached_image_size();
            // Format and return total size
            return $this->abdfw_format_size($total_size);
        }

        // Function to retrieve unattached image IDs
        private function abdfw_get_unattached_image_ids() {
            global $wpdb;
            // Query the database to retrieve attachment IDs of unattached images
            $attachment_ids = $wpdb->get_col("
                SELECT ID
                FROM $wpdb->posts
                WHERE post_type = 'attachment'
                AND post_parent = 0
                AND post_mime_type LIKE 'image%'
            ");
            return $attachment_ids;
        }

        // Function to calculate the size of an unattached image file and all its sizes
        private function abdfw_calculate_unattached_images_size($attachment_id) {
            $total_size = 0;

            // Get the path to the original image file
            $file_path = get_attached_file($attachment_id);
            if ($file_path && is_file($file_path)) {
                $total_size += filesize($file_path); // Size of the original image
            }

            // Get the metadata for the image
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata && isset($metadata['sizes'])) {
                // Iterate through all image sizes
                foreach ($metadata['sizes'] as $size) {
                    $size_file = str_replace(basename($file_path), $size['file'], $file_path);
                    if (is_file($size_file)) {
                        $total_size += filesize($size_file);
                    }
                }
            }
            return $total_size;
        }

        // Function to calculate size of unattached image files
        private function abdfw_calculate_unattached_image_size() {
            // Get unattached image IDs
            $unattached_image_ids = $this->abdfw_get_unattached_image_ids();
            // Initialize total size variable
            $total_size = 0;
            // Calculate size of each unattached image file and add to total size
            foreach ($unattached_image_ids as $attachment_id) {
                $total_size += $this->abdfw_calculate_unattached_images_size($attachment_id);
            }
            return $total_size;
        }

        // Function to get total size of all unattached images
        public function abdfw_get_total_unattached_image_size() {
            // Calculate total size of unattached image files
            $total_size = $this->abdfw_calculate_unattached_image_size();
            // Format and return total size
            return $this->abdfw_format_size($total_size);
        }        

        public function abdfw_custom_delete_all_pages() {
            // Check nonce
            if ( ! isset( $_POST['custom_delete_all_pages_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['custom_delete_all_pages_nonce'] ) ) , 'custom_delete_all_pages' ) ) {
                wp_send_json_error( esc_html__('Nonce verification failed.', 'smart-bulk-content-remover') );
                wp_die();
            }
            
            // Check if user has permission to delete pages
            if (!current_user_can('delete_pages')) {                
                wp_send_json_error( esc_html__('You do not have permission to delete pages.', 'smart-bulk-content-remover') );
                wp_die();
            }
            // Get all pages including those in trash and draft states
            $args = array(
                'post_type'      => 'page',
                'post_status'    => array('publish', 'draft', 'trash'),
                'posts_per_page' => -1,
            );
            $pages_query = new WP_Query($args);
            // Check if there are any pages
            if ( ! $pages_query->have_posts() ) {
                wp_send_json_error( esc_html__('No pages found to delete.', 'smart-bulk-content-remover') );
                wp_die();
            }
            // Loop through each page and delete it
            if ($pages_query->have_posts()) {
                while ($pages_query->have_posts()) {
                    $pages_query->the_post();
                    wp_delete_post(get_the_ID(), true); // Set second parameter to true to permanently delete the page
                }
            }
            // Reset post data
            wp_reset_postdata();
            // Send success response
            wp_send_json_success( esc_html__('All pages and associated database records deleted successfully.', 'smart-bulk-content-remover') );
            wp_die();
        }

        function abdfw_delete_post_types_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die();
            }

            // Verify nonce
            if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'custom_delete_post_types')) {
                wp_send_json_error('Nonce failed');
                wp_die();
            }

            if (isset($_POST['post_types'])) {
                $post_types = array_map('sanitize_text_field', $_POST['post_types']);
                $response = [];

                foreach ($post_types as $post_type) {
                    $args = [
                        'post_type'      => $post_type,
                        'posts_per_page' => -1,
                        'post_status'    => 'any', // Consider all post statuses
                    ];
                    $posts = get_posts($args);

                    if (empty($posts)) {
                        // No posts found for this post type
                        $response[$post_type] = sprintf(
                            __( 'Post type "%s" does not have any data.', 'smart-bulk-content-remover' ), $post_type
                        );
                    } else {
                        // Delete posts
                        foreach ($posts as $post) {
                            wp_delete_post($post->ID, true); // Delete post permanently
                        }
                        $response[$post_type] = sprintf(
                            __( 'Post type "%s" deleted successfully!', 'smart-bulk-content-remover' ),
                            $post_type
                        );
                    }
                }
                wp_send_json_success($response);
            } else {
                wp_send_json_error(esc_html__('No post types selected.', 'smart-bulk-content-remover'));
            }
            wp_die();
        }
        
        function abdfw_delete_all_media() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die();
            }
            // Verify nonce
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['security'] ) ) , 'delete_media_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                wp_die();
            }
            global $wpdb;
            $media_count = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image%'
                ");
            if($media_count == 0){
                wp_send_json_error( esc_html__('No media found for delete.', 'smart-bulk-content-remover') );
                wp_die();
            } else {
                // Delete all media files from the upload folder
                $upload_dir = wp_upload_dir();
                $upload_path = $upload_dir['basedir'];
                // Get all files in the uploads directory
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($upload_path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $file = $fileinfo->getPathname();
                    if ($fileinfo->isFile()) {
                        // Delete the file
                        unlink($file);
                    } elseif ($fileinfo->isDir()) {
                        // Remove directory if empty
                        rmdir($file);
                    }
                }
                // Delete all media entries from the database
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'attachment'");
                wp_send_json_success( esc_html__('All media files deleted successfully.', 'smart-bulk-content-remover') );
                wp_die();
            }
        }

        function abdfw_delete_all_comments() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die();
            }
            // Verify nonce
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['security'] ) ) , 'delete_comments_nonce' ) ){
                wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                wp_die();
            }
            // Get all comments
            $comments = get_comments(array(
                'number' => 1, // Just to check if there are any comments
                'fields' => 'ids' // Only get the IDs for performance
            ));

            // Check if there are comments to delete
            if (empty($comments)) {
                wp_send_json_error(esc_html__('No comments found to delete.', 'smart-bulk-content-remover'));
                wp_die();
            }
            // Loop through comments and delete each one
            foreach ($comments as $comment) {
                wp_delete_comment($comment->comment_ID, true); 
            }
            // Send success response
            wp_send_json_success( esc_html__('All comments deleted successfully', 'smart-bulk-content-remover') );
            wp_die();
        } 

        public function abdfw_get_image_count_by_date_callback() {
            global $wpdb;
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Verify the nonce            
            if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'date_images_nonce')) {
                wp_send_json_error(esc_html__('Invalid nonce', 'smart-bulk-content-remover'));
                wp_die();
            }
            $from_date = isset($_POST['from_date']) ? sanitize_text_field($_POST['from_date']) : null;
            // Check if the 'to_date' parameter is set and sanitize it
            $to_date = isset($_POST['to_date']) ? sanitize_text_field($_POST['to_date']) : null;
            $image_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(ID)
                FROM {$wpdb->posts}
                WHERE post_type = 'attachment'
                AND post_mime_type LIKE 'image%%'
                AND post_date BETWEEN %s AND %s
            ", $from_date . ' 00:00:00', $to_date . ' 23:59:59'));
            echo esc_html($image_count);
            wp_die();
        }

        public function abdfw_delete_images_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die();
            }
            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['nonce'] ) ) , 'delete_images_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                wp_die();
            }
            $from_date = isset($_POST['from_date']) ? sanitize_text_field(wp_unslash($_POST['from_date'])) : '';

            $to_date = isset($_POST['to_date']) ? sanitize_text_field(wp_unslash($_POST['to_date'])) : '';
            // Get image attachments to delete
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'date_query' => array(
                    'after' => $from_date,
                    'before' => $to_date,
                ),
                'fields' => 'ids',
            ));
            if (empty($attachments)) {
                wp_send_json_error( esc_html__('No images found for the specified date range.', 'smart-bulk-content-remover') );
                wp_die();
            }
            $deleted_count = 0;
            foreach ($attachments as $attachment_id) {
                // Delete the attachment from database
                $deleted = wp_delete_attachment($attachment_id, true);
                if ($deleted) {
                    $deleted_count++;
                }
            }
            if ($deleted_count > 0) {
                // Output success message
                wp_send_json_success( esc_html__('Successfully deleted $deleted_count images.', 'smart-bulk-content-remover') );
            } else {
                // Output error message if no images were deleted
                wp_send_json_error( esc_html__('Failed to delete any images.', 'smart-bulk-content-remover') );
            }
            wp_die();
        }
    
        public function abdfw_custom_month_year_dropdown() {
            global $wpdb;
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Get the month and year when WordPress was installed
            $install_date = $wpdb->get_var("SELECT DATE_FORMAT( MIN(post_date), '%Y-%m' ) FROM $wpdb->posts");
            // Get current month and year
            $current_month_year = date('Y-m');
            // Initialize an empty array to store month-year values
            $months_years = array();
            // Generate all months and years between installation date and current date
            $start = new DateTime($install_date);
            $end = new DateTime($current_month_year);
            $interval = new DateInterval('P1M');
            $period = new DatePeriod($start, $interval, $end);
            foreach ($period as $dt) {
                $months_years[] = $dt->format('F Y');
            }
            // Add the current month to the array
            $months_years[] = date('F Y'); // Add current month
            // Reverse the array to show the most recent months first
            $months_years = array_reverse($months_years);
            // Output the dropdown menu
            ?>
            <div class="filter-date-inner month-selector-wrapper">
                <h2><?php echo esc_html__('Filter by Month','smart-bulk-content-remover'); ?></h2>
                <form id="abdfw_search_monthswise_image">
                    <?php wp_nonce_field('monthswise_images_nonce', 'monthswise_images_nonce_field'); ?>
                    <select name="abdfw_month_year">
                        <option value="0"><?php echo esc_html__('Select Month-Year','smart-bulk-content-remover'); ?></option>
                        <?php foreach ($months_years as $my) : ?>
                            <option value="<?php echo esc_attr($my); ?>"><?php echo esc_html($my); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $nonce = wp_create_nonce('delete_media_by_month_year_nonce'); ?>
                    <input type="submit" value="Apply" class="button-primary"> <input type="hidden" name="nonce" value="<?php echo esc_html($nonce); ?>">
                </form>
                <div id="abdfw_monthswise_images_display"></div>
                <div id="abdfw-show-month_year-image-popup" class="image_popup" style="display: none;">
                    <button id="abdfw_month_year-image-close-popup" class="close-icon"><?php echo esc_html__('Close','smart-bulk-content-remover'); ?></button>
                    <div id="abdfw_month_year-image-list"></div>
                </div>
            </div>
            <?php
        }

        // PHP function to handle AJAX request
        function abdfw_get_images_by_month_year() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            if (isset($_POST['abdfw_month_year'])) {
                global $wpdb;

                // Verify nonce
                if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['security'] ) ) , 'monthswise_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                    wp_die();
                }

                // Get the month and year from the selected value
                $selected_date = sanitize_text_field($_POST['abdfw_month_year']);
                $year = date('Y', strtotime($selected_date));
                $month = date('m', strtotime($selected_date));
                // Construct SQL query to retrieve the count of images uploaded in the selected month and year
                $image_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) 
                    FROM $wpdb->posts 
                    WHERE post_type = 'attachment' 
                    AND YEAR(post_date) = %d
                    AND MONTH(post_date) = %d", $year, $month)
                );
                echo esc_html($image_count);
            }
            wp_die(); // This is required to terminate immediately and return a proper response
        }

        public function abdfw_custom_year_dropdown() { ?>
            <div class="filter-date-inner year-selector-wrapper">
                <h2><?php echo esc_html__('Filter by Year','smart-bulk-content-remover'); ?></h2>
                <form id="abdfw-year-form">
                    <?php wp_nonce_field('year_images_nonce', 'year_images_nonce_field'); ?>
                    <?php 
                    global $wpdb;
                    $install_year = $wpdb->get_var("SELECT DATE_FORMAT(MIN(post_date), '%Y') FROM $wpdb->posts");
                    $current_year = date('Y');
                    ?>
                    <select name="abdfw-year" id="abdfw-year">
                        <option value="0"><?php echo esc_html('Select Year','smart-bulk-content-remover'); ?></option>
                        <?php 
                        for ($year = $install_year; $year <= $current_year; $year++) { ?>
                            <option value="<?php echo esc_html($year);?>"><?php echo esc_html($year);?></option>
                        <?php } ?>
                    </select>
                    <input type="submit" value="Apply" class="button-primary">
                    </form>
                    <div id="abdfw-image-count"></div>
                    <!-- Popup container -->
                    <div id="abdfw-show-year-image-popup" class="image_popup" style="display: none;">
                        <button id="abdfw-year-image-close-popup" class="close-icon"><?php echo esc_html__('Close','smart-bulk-content-remover'); ?></button>
                        <div id="abdfw-year-image-list"></div>
                    </div>
                </div>
            <?php
        }

        function abdfw_get_image_count_by_year() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            if (isset($_POST['year'])) {
                if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['security'] ) ) , 'year_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                    wp_die();
                }
                $year = intval($_POST['year']);                
                // Assuming your images are stored in a database table called 'images'
                global $wpdb;
                $image_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) 
                    FROM $wpdb->posts 
                    WHERE post_type = 'attachment' 
                    AND YEAR(post_date) = %d", $year));                
                echo esc_html($image_count);
            }
            wp_die(); // This is required to terminate immediately and return a proper response
        }
       
        public function abdfw_display_authors_list() {
            ?>
            <div class="filter-date-inner author-selector-wrapper">
                <h2><?php echo esc_html__( 'Filter by Author', 'smart-bulk-content-remover' ); ?></h2>
                <form id="abdfw-author-form">
                    <?php wp_nonce_field( 'author_images_nonce', 'author_images_nonce_field' ); ?>
                    <?php
                    // This function generates the dropdown of authors
                    wp_dropdown_users( [
                        'name'              => 'author_id',
                        'id'                => 'author_id',
                        'capability'        => 'edit_posts', // ✅ Replaces deprecated 'who' => 'authors'
                        'show_option_all'   => __( 'Select Author', 'smart-bulk-content-remover' ),
                        'option_none_value' => '0',
                    ] );
                    ?>
                    <input type="submit" name="submit" value="<?php echo esc_attr__( 'Apply', 'smart-bulk-content-remover' ); ?>" class="button-primary">
                </form>

                <div id="abdfw-author-result"></div> <!-- Container for displaying result -->

                <!-- Popup container -->
                <div id="abdfw-show-author-image-popup" class="image_popup" style="display: none;">
                    <button id="abdfw-author-image-close-popup" class="close-icon">
                        <?php echo esc_html__( 'Close', 'smart-bulk-content-remover' ); ?>
                    </button>
                    <div id="abdfw-author-image-list"></div>
                </div>
            </div>
            <?php
        }

        public function abdfw_get_images_by_author() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            if (isset($_POST['author_id'])) {
                global $wpdb;
                if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['security'] ) ) , 'author_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce', 'smart-bulk-content-remover') );
                    wp_die();
                }
                $author_id = intval($_POST['author_id']);
                // Initialize the total image count
                $total_image_count = 0;
                // Construct SQL query to retrieve image count by author
                $query = $wpdb->prepare("
                    SELECT COUNT(*) 
                    FROM $wpdb->posts 
                    WHERE post_type = 'attachment' 
                    AND post_author = %d",
                    $author_id
                );
                // Execute the query
                $image_count = $wpdb->get_var($query);
                // Display the total image count
                echo esc_html($image_count);
            }
            wp_die(); // This is required to terminate immediately and return a proper response
        }

        protected function abdfw_fetchImageUrls() {
            global $wpdb; // Use global $wpdb instead of $this->wpdb for clarity and consistency
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }

            if (!empty($wpdb)) {
                // Prepare the SQL query with placeholders
                $sql = $wpdb->prepare(
                    "SELECT option_name, option_value 
                    FROM {$wpdb->options}
                    WHERE option_value LIKE %s 
                    OR option_value LIKE %s 
                    OR option_value LIKE %s 
                    OR option_value LIKE %s",
                    '%jpg%',
                    '%jpeg%',
                    '%png%',
                    '%gif%'
                );

                // Execute the query
                $results = $wpdb->get_results($sql);

                return $results;
            } else {
                return null;
            }
        }
       
        public function abdfw_delete_media_by_author_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Verify nonce for security
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['nonce'] ) ) , 'delete_media_nonce' ) ){
                wp_send_json_error( esc_html__('Nonce verification failed!', 'smart-bulk-content-remover') );
                wp_die();
            }
            global $wpdb;
            // Retrieve author ID from the form submission
            $author_id = isset($_POST['author_id']) ? intval($_POST['author_id']) : 0;
            if ($author_id > 0) {
                // Prepare the SQL query to select attachments by author ID
                $query = $wpdb->prepare("
                    SELECT ID 
                    FROM $wpdb->posts 
                    WHERE post_type = 'attachment' 
                    AND post_author = %d",
                    $author_id
                );
                // Execute the query
                $attachments = $wpdb->get_results($query);
                // Check if any attachments found for the author
                if (!empty($attachments)) {
                    // Loop through each attachment and delete it
                    foreach ($attachments as $attachment) {
                        if (wp_delete_attachment($attachment->ID, true) === false) {
                            wp_send_json_error( array(
                                'message' => esc_html__('Error while deleting attachment','smart-bulk-content-remover')
                            ) );
                            wp_die();
                        }
                    }
                    // Send success message if all attachments are deleted successfully
                    wp_send_json_success( esc_html__('All media files deleted successfully.', 'smart-bulk-content-remover') );
                    wp_die();
                } else {
                    // No attachments found for the selected author
                    wp_send_json_error( esc_html__('No media files found for the selected author.','smart-bulk-content-remover') );
                    wp_die();
                }
            } else {
                // Invalid author ID
                wp_send_json_error( esc_html__('Invalid author ID.','smart-bulk-content-remover') );
                wp_die();
            }
        }
       
        public function abdfw_delete_media_by_month_year_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Verify nonce for security
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_media_by_month_year_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid Nonce','smart-bulk-content-remover') );
                wp_die();
            }
            // Retrieve form data
            $month_year = isset($_POST['abdfw_month_year']) ? sanitize_text_field($_POST['abdfw_month_year']) : ''; 
            // Perform deletion logic based on the selected month-year
            if (!empty($month_year)) {
                global $wpdb;
                // Generate the start and end date range for the selected month-year
                $start_date = date('Y-m-01 00:00:00', strtotime($month_year));
                $end_date = date('Y-m-t 23:59:59', strtotime($month_year));
                // Retrieve attachment IDs and file paths for attachments uploaded within the specified month-year
                $attachments = $wpdb->get_results($wpdb->prepare("
                    SELECT ID, meta_value
                    FROM $wpdb->posts 
                    LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                    WHERE post_type = 'attachment' 
                    AND post_date >= %s 
                    AND post_date <= %s",
                    $start_date,
                    $end_date
                ));
                // Delete attachments and associated files
                foreach ($attachments as $attachment) {
                    // Delete attachment from database
                    wp_delete_attachment($attachment->ID, true);
                    
                    // Retrieve file path
                    $file_path = get_attached_file($attachment->ID);

                    // Delete file from uploads folder
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                // Send success message
                wp_send_json_success( esc_html__('Media for the selected month-year deleted successfully.','smart-bulk-content-remover') );
            } else {
                // Send error message if month-year is not provided
                wp_send_json_error( esc_html__('Invalid month-year.','smart-bulk-content-remover') );
            }
            // Terminate the script
            wp_die();
        }

        public function abdfw_delete_images_between_dates_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'date_images_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                wp_die();
            }
            $from_date = isset($_POST['from_date']) ? sanitize_text_field(wp_unslash($_POST['from_date'])) : '';
            $to_date = isset($_POST['to_date']) ? sanitize_text_field(wp_unslash($_POST['to_date'])) : '';
            global $wpdb;
            // Retrieve attachment IDs and file paths for images between selected dates
            $attachments = $wpdb->get_results($wpdb->prepare("
                SELECT ID, meta_value
                FROM $wpdb->posts 
                LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                WHERE post_type = 'attachment' 
                AND post_date BETWEEN %s AND %s",
                $from_date . ' 00:00:00',
                $to_date . ' 23:59:59'
            ));
            // Delete images and associated files
            foreach ($attachments as $attachment) {
                // Delete attachment from database
                wp_delete_attachment($attachment->ID, true);                
                // Retrieve file path
                $file_path = get_attached_file($attachment->ID);
                // Delete file from uploads folder
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            echo esc_html__('Images between selected dates and associated files deleted successfully.','smart-bulk-content-remover');
            wp_die();
        }

        public function abdfw_delete_all_unattached_images_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Verify nonce for security
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_all_unattached_images_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                wp_die();
            }
            global $wpdb;            
            // Get all attachment IDs that are not attached to any post
            $unattached_attachment_ids = $wpdb->get_col("
                SELECT ID
                FROM $wpdb->posts
                WHERE post_type = 'attachment' 
                AND post_parent = 0
            ");            
            // Delete unattached attachments from the database and media library
            foreach ($unattached_attachment_ids as $attachment_id) {
                // Retrieve file path
                $file_path = get_attached_file($attachment_id);                
                // Delete attachment from database
                wp_delete_attachment($attachment_id, true);                
                // Delete file from uploads folder
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }            
            echo esc_html__('All unattached images and associated files deleted successfully.','smart-bulk-content-remover');
            wp_die();
        }

        public function abdfw_delete_all_attached_images_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
           
            // Verify nonce for security
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'delete_all_attached_images_nonce')) {
                wp_send_json_error(__('Invalid nonce', 'smart-bulk-content-remover'));
            }

            global $wpdb;            
            // Get all attachment IDs that are attached to any post
            $attached_attachment_ids = $wpdb->get_col("
                SELECT ID
                FROM $wpdb->posts
                WHERE post_type = 'attachment' 
                AND post_parent != 0
            ");            
            // Delete attached attachments from the database and media library
            foreach ($attached_attachment_ids as $attachment_id) {
                // Retrieve file path
                $file_path = get_attached_file($attachment_id);
                // Delete attachment from database
                wp_delete_attachment($attachment_id, true);                
                // Delete file from uploads folder
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }            
            wp_send_json_success( esc_html__('All attached images and associated files deleted successfully.','smart-bulk-content-remover') );         
            wp_die();
        }

        public function abdfw_delete_media_by_year_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            // Verify nonce for security
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'year_images_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                wp_die();
            }
            // Retrieve the selected year from the AJAX request
            $selected_year = isset($_POST['year']) ? intval($_POST['year']) : 0;
            if ($selected_year > 0) {
                global $wpdb;                
                // Prepare the start and end date range for the selected year
                $start_date = $selected_year . '-01-01 00:00:00';
                $end_date = $selected_year . '-12-31 23:59:59';                
                // Retrieve file paths of attachments within the specified date range
                $attachments = $wpdb->get_results($wpdb->prepare("
                    SELECT ID, meta_value
                    FROM $wpdb->posts 
                    LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id
                    WHERE post_type = 'attachment' 
                    AND post_date >= %s 
                    AND post_date <= %s",
                    $start_date,
                    $end_date
                ));
                if ($attachments) {
                    foreach ($attachments as $attachment) {
                        // Delete attachment from database
                        wp_delete_attachment($attachment->ID, true);
                        // Delete file from uploads folder
                        $file_path = get_attached_file($attachment->ID);
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }                    
                    echo esc_html__('Media for the selected year deleted successfully.','smart-bulk-content-remover');
                } else {                   
                    echo esc_html__('No media found for the selected year.','smart-bulk-content-remover');
                }
            } else {                
                echo esc_html__('Invalid year.','smart-bulk-content-remover'); 
            }
            wp_die();
        }

        public function abdfw_delete_all_images_callback() {
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_all_images_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                wp_die();
            }
            // Ensure the user has the capability
            if (!current_user_can('manage_options')) {
                wp_send_json_error( esc_html__('You do not have sufficient permissions to perform this action.','smart-bulk-content-remover') );
                wp_die();
            }
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            foreach ($attachments as $attachment) {
                // Delete files from uploads directory
                wp_delete_attachment($attachment->ID, true);
            }
            wp_send_json_success( esc_html__('All images have been successfully deleted.','smart-bulk-content-remover') );
            wp_die();
        }            

        // Display settings field when plugin acive
        public function abdfw_addPluginActionLinks($links) {
            $settingsLink = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=advanced-bulk-delete-settings')) . '">' . esc_html__('Settings', 'smart-bulk-content-remover') . '</a>';
            array_unshift($links, $settingsLink);
            return $links;
        }

        public function abdfw_download_all_images() {
            if ( current_user_can( 'manage_options' ) ) {

                // Nonce check
                if ( ! isset( $_POST['nonce'] ) || 
                     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'delete_all_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                    wp_die();
                }

                $upload_dir  = wp_upload_dir();
                $images      = get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1 ) );
                $zip_filename = tempnam( sys_get_temp_dir(), 'Images' ) . '.zip';

                // ✅ Prefer ZipArchive if available
                if ( class_exists( 'ZipArchive' ) ) {
                    $zip = new ZipArchive();
                    if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                        wp_die( esc_html__('Could not create archive','smart-bulk-content-remover') );
                    }

                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        if ( file_exists( $file_path ) ) {
                            $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );
                            $zip->addFile( $file_path, $relative_path );
                        }
                    }
                    $zip->close();

                } else {
                    // ✅ Fallback to WordPress bundled PclZip
                    require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';

                    $file_paths = array();
                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        if ( file_exists( $file_path ) ) {
                            $file_paths[] = $file_path;
                        }
                    }

                    $archive = new PclZip( $zip_filename );
                    $archive->create( $file_paths, PCLZIP_OPT_REMOVE_PATH, $upload_dir['basedir'] );
                }

                // ✅ Output download headers
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="all_images.zip"' );
                header( 'Content-Length: ' . filesize( $zip_filename ) );

                readfile( $zip_filename );
                unlink( $zip_filename ); // cleanup temp file
                exit;
            }
        }

        public function abdfw_download_attached_images() {
            if ( current_user_can( 'manage_options' ) ) {

                // 🔒 Nonce check
                if ( ! isset( $_POST['security'] ) || 
                     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'delete_all_attached_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                    wp_die();
                }

                // 📸 Get only attached images
                $args = array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'post_status'    => 'inherit',
                    'posts_per_page' => -1,
                    'post_parent__not_in' => array(0) // ensure attached to a post/page
                );
                $images = get_posts( $args );

                if ( empty( $images ) ) {
                    wp_die( esc_html__( 'No attached images found.', 'smart-bulk-content-remover' ) );
                }

                $zip_filename = tempnam( sys_get_temp_dir(), 'AttachedImages' ) . '.zip';

                // ✅ Prefer ZipArchive if available
                if ( class_exists( 'ZipArchive' ) ) {
                    $zip = new ZipArchive();
                    if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                        wp_die( esc_html__('Could not create archive','smart-bulk-content-remover') );
                    }

                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        $file_name = basename( $file_path );
                        if ( file_exists( $file_path ) ) {
                            $zip->addFile( $file_path, $file_name );
                        }
                    }
                    $zip->close();

                } else {
                    // ✅ Fallback: use WordPress bundled PclZip
                    require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';

                    $file_paths = array();
                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        if ( file_exists( $file_path ) ) {
                            $file_paths[] = $file_path;
                        }
                    }

                    $archive = new PclZip( $zip_filename );
                    $archive->create( $file_paths, PCLZIP_OPT_REMOVE_PATH, dirname( $file_paths[0] ) );
                }

                // 📦 Serve the file for download
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="attached_images.zip"' );
                header( 'Content-Length: ' . filesize( $zip_filename ) );

                readfile( $zip_filename );
                unlink( $zip_filename ); // cleanup
                exit;
            }
        }        

        public function abdfw_download_unattached_images() {
            if ( current_user_can( 'manage_options' ) ) {

                // 🔒 Nonce check
                if ( ! isset( $_POST['security'] ) || 
                     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'delete_all_unattached_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                    wp_die();
                }

                // 📸 Get only unattached images
                $args = array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'post_status'    => 'inherit',
                    'posts_per_page' => -1,
                    'post_parent'    => 0 // unattached only
                );
                $images = get_posts( $args );

                if ( empty( $images ) ) {
                    wp_die( esc_html__( 'No unattached images found.', 'smart-bulk-content-remover' ) );
                }

                $zip_filename = tempnam( sys_get_temp_dir(), 'UnattachedImages' ) . '.zip';

                // ✅ Use ZipArchive if available
                if ( class_exists( 'ZipArchive' ) ) {
                    $zip = new ZipArchive();
                    if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                        wp_die( esc_html__('Could not create archive','smart-bulk-content-remover') );
                    }

                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        $file_name = basename( $file_path );
                        if ( file_exists( $file_path ) ) {
                            $zip->addFile( $file_path, $file_name );
                        }
                    }
                    $zip->close();

                } else {
                    // ✅ Fallback: use WordPress bundled PclZip
                    require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';

                    $file_paths = array();
                    foreach ( $images as $image ) {
                        $file_path = get_attached_file( $image->ID );
                        if ( file_exists( $file_path ) ) {
                            $file_paths[] = $file_path;
                        }
                    }

                    $archive = new PclZip( $zip_filename );
                    $archive->create( $file_paths, PCLZIP_OPT_REMOVE_PATH, dirname( $file_paths[0] ) );
                }

                // 📦 Serve the file for download
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="unattached_images.zip"' );
                header( 'Content-Length: ' . filesize( $zip_filename ) );

                readfile( $zip_filename );
                unlink( $zip_filename ); // cleanup temp file
                exit;
            }
        }

        function abdfw_get_unattached_image_urls_callback() {
            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'delete_all_unattached_images_nonce' ) ) {
                    wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                    wp_die();
                }
            // Define query arguments to retrieve unattached images
            $args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'post_parent'    => 0 // Only retrieve unattached images
            );
            // Fetch unattached images using WP_Query
            $attachments_query = new WP_Query($args);
            // Initialize an array to store image URLs
            $image_urls = array();
            // Loop through each unattached image and retrieve its URL
            if ($attachments_query->have_posts()) {
                while ($attachments_query->have_posts()) {
                    $attachments_query->the_post();
                    $image_urls[] = esc_url(wp_get_attachment_url(get_the_ID()));
                }
            }
            // Restore global post data
            wp_reset_postdata();
            // Return JSON response with image URLs
            wp_send_json_success($image_urls);
            wp_die(); // Always include this at the end of your AJAX callback function.
        }

        // Callback function
        public function abdfw_download_images_between_dates_callback() {
            // 🔒 Check permissions
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // 🔒 Nonce check
            if ( ! isset( $_POST['security'] ) || 
                 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'date_images_nonce' ) ) {
                wp_send_json_error( esc_html__( 'Invalid nonce', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // 📅 Dates
            $from_date = isset( $_POST['from_date'] ) ? sanitize_text_field( wp_unslash( $_POST['from_date'] ) ) : '';
            $to_date   = isset( $_POST['to_date'] ) ? sanitize_text_field( wp_unslash( $_POST['to_date'] ) ) : '';

            // 📸 Query images in range
            $args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'date_query'     => array(
                    'after'     => $from_date,
                    'before'    => $to_date,
                    'inclusive' => true,
                ),
            );

            $query  = new WP_Query( $args );
            $images = array();

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $file_path = get_attached_file( get_the_ID() );
                    if ( $file_path && file_exists( $file_path ) ) {
                        $images[] = $file_path;
                    }
                }
                wp_reset_postdata();
            }

            if ( empty( $images ) ) {
                wp_send_json_error( esc_html__( 'No images found for the specified date range.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            $zip_filename = tempnam( sys_get_temp_dir(), 'images' ) . '.zip';

            // ✅ Use ZipArchive if available
            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                    wp_send_json_error( esc_html__( 'Could not create a zip file.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }

                foreach ( $images as $file ) {
                    $zip->addFile( $file, basename( $file ) );
                }
                $zip->close();

            } else {
                // ✅ Fallback: use WordPress bundled PclZip
                require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';
                $archive = new PclZip( $zip_filename );
                $archive->create( $images, PCLZIP_OPT_REMOVE_PATH, dirname( $images[0] ) );
            }

            // 📦 Serve file for download
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="downloaded_images.zip"' );
            header( 'Content-Length: ' . filesize( $zip_filename ) );

            readfile( $zip_filename );
            unlink( $zip_filename ); // cleanup
            exit;
        }
        
        public function abdfw_download_images_by_month_year() {

            // 🔒 Permissions check
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // 🔒 Nonce check
            if ( ! isset( $_POST['security'] ) || 
                 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'monthswise_images_nonce' ) ) {
                wp_send_json_error( esc_html__( 'Invalid nonce', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // 📅 Month & Year from input
            $monthYearValue = isset( $_POST['monthYearValue'] ) ? sanitize_text_field( wp_unslash( $_POST['monthYearValue'] ) ) : '';
            if ( empty( $monthYearValue ) ) {
                wp_send_json_error( esc_html__( 'Invalid month/year value.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            $year  = date( 'Y', strtotime( $monthYearValue ) );
            $month = date( 'm', strtotime( $monthYearValue ) );

            // 📸 Query attachments uploaded in the selected month & year
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'year'           => $year,
                'monthnum'       => $month,
            );
            $attachments = get_posts( $args );

            if ( empty( $attachments ) ) {
                wp_send_json_error( esc_html__( 'No images found for the selected month and year.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            $images = array();
            foreach ( $attachments as $attachment ) {
                $file_path = get_attached_file( $attachment->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    $images[] = $file_path;
                }
            }

            if ( empty( $images ) ) {
                wp_send_json_error( esc_html__( 'No valid image files found.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            $zip_filename = tempnam( sys_get_temp_dir(), 'month_year_images' ) . '.zip';

            // ✅ Use ZipArchive if available
            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                    wp_send_json_error( esc_html__( 'Could not create archive.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }

                foreach ( $images as $file ) {
                    $zip->addFile( $file, basename( $file ) );
                }
                $zip->close();

            } else {
                // ✅ Fallback: use WordPress bundled PclZip
                require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';
                $archive = new PclZip( $zip_filename );
                $archive->create( $images, PCLZIP_OPT_REMOVE_PATH, dirname( $images[0] ) );
            }

            // 📦 Serve the zip for download
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="month_year_images.zip"' );
            header( 'Content-Length: ' . filesize( $zip_filename ) );

            readfile( $zip_filename );
            unlink( $zip_filename ); // cleanup
            exit;
        }

        public function abdfw_download_media_by_years() {
            // Ensure the user has the necessary permissions
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'year_images_nonce' ) ) {
                wp_send_json_error( esc_html__( 'Invalid nonce.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Sanitize and extract selected year
            $year_value = isset( $_POST['yearValue'] ) ? sanitize_text_field( wp_unslash( $_POST['yearValue'] ) ) : '';

            if ( empty( $year_value ) || ! is_numeric( $year_value ) ) {
                wp_send_json_error( esc_html__( 'Invalid year selected.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Retrieve attachments uploaded in the selected year
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'year'           => intval( $year_value ),
            );

            $attachments = get_posts( $args );

            if ( empty( $attachments ) ) {
                wp_send_json_error( esc_html__( 'No media files found for the selected year.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Collect file paths
            $files = array();
            foreach ( $attachments as $attachment ) {
                $file_path = get_attached_file( $attachment->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    $files[] = $file_path;
                }
            }

            if ( empty( $files ) ) {
                wp_send_json_error( esc_html__( 'No valid files found to archive.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Temp file for the zip
            $zip_filename = tempnam( sys_get_temp_dir(), 'year_images' ) . '.zip';

            // ✅ Use ZipArchive if available
            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                    wp_send_json_error( esc_html__( 'Could not create archive.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }
                foreach ( $files as $file ) {
                    $zip->addFile( $file, basename( $file ) );
                }
                $zip->close();
            } else {
                // ✅ Fallback: use WordPress bundled PclZip
                require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';
                $archive = new PclZip( $zip_filename );
                $v_list  = $archive->create( $files, PCLZIP_OPT_REMOVE_PATH, dirname( $files[0] ) );
                if ( ! $v_list ) {
                    wp_send_json_error( esc_html__( 'Could not create archive with PclZip.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }
            }

            // Send the zip file as response
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="year_images.zip"' );
            header( 'Content-Length: ' . filesize( $zip_filename ) );
            readfile( $zip_filename );

            // Delete the zip file after sending
            unlink( $zip_filename );

            wp_die();
        }

        public function abdfw_download_author_images_callback() {
            // Ensure the user has the necessary permissions
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( esc_html__( 'You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Verify nonce
            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'author_images_nonce' ) ) {
                wp_send_json_error( esc_html__( 'Invalid nonce.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Sanitize and extract author ID
            $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : 0;

            global $wpdb;

            // Count attachments uploaded by the specified author
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_author = %d",
                $author_id
            );
            $attachment_count = $wpdb->get_var( $count_query );

            // Fetch attachments uploaded by the specified author
            $attachments_query = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_author = %d",
                $author_id
            );
            $attachments = $wpdb->get_results( $attachments_query );

            if ( empty( $attachments ) ) {
                wp_send_json_error( esc_html__( 'No attachments found for this author.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Collect file paths
            $files = array();
            foreach ( $attachments as $attachment ) {
                $file_path = get_attached_file( $attachment->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    $files[] = $file_path;
                }
            }

            if ( empty( $files ) ) {
                wp_send_json_error( esc_html__( 'No valid files found to archive.', 'smart-bulk-content-remover' ) );
                wp_die();
            }

            // Temp file for the zip
            $zip_filename = tempnam( sys_get_temp_dir(), 'author_images' ) . '.zip';

            // ✅ Use ZipArchive if available
            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== TRUE ) {
                    wp_send_json_error( esc_html__( 'Could not create archive.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }
                foreach ( $files as $file ) {
                    $zip->addFile( $file, basename( $file ) );
                }
                $zip->close();
            } else {
                // ✅ Fallback: use WordPress bundled PclZip
                require_once ABDFW_ADMIN_INCLUDES_PATH . 'class-pclzip.php';
                $archive = new PclZip( $zip_filename );
                $v_list  = $archive->create( $files, PCLZIP_OPT_REMOVE_PATH, dirname( $files[0] ) );
                if ( ! $v_list ) {
                    wp_send_json_error( esc_html__( 'Could not create archive with PclZip.', 'smart-bulk-content-remover' ) );
                    wp_die();
                }
            }

            // Send the zip file as response along with the attachment count
            header( 'Content-Type: application/zip' );
            header( 'Content-Disposition: attachment; filename="author_images.zip"' );
            header( 'Content-Length: ' . filesize( $zip_filename ) );
            header( 'Attachment-Count: ' . $attachment_count ); // Custom header for count
            readfile( $zip_filename );

            // Delete the zip file after sending it
            unlink( $zip_filename );

            wp_die();
        }

        public function abdfw_delete_selected_files_callback() {

            // Ensure the user has the necessary permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(esc_html__('You do not have sufficient permissions to perform this action.', 'smart-bulk-content-remover'));
                wp_die(); // Terminate script execution
            }

            if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'abdfw_folders_nonce' ) ) {
                wp_send_json_error( esc_html__('Invalid nonce','smart-bulk-content-remover') );
                wp_die();
            }

            // Retrieve and sanitize selected files
            $selectedFiles = array();
            if ( isset( $_POST['files'] ) && is_array( $_POST['files'] ) ) {
                $selectedFiles = array_map( 'sanitize_text_field', wp_unslash( $_POST['files'] ) );
            }

            $selectedYears   = array();
            $selectedFolders = array();
            $filesDeleted    = false; // Flag to track if any files were deleted

            // Categorize selected files into years and folders
            foreach ( $selectedFiles as $file ) {
                if ( is_string( $file ) && preg_match( '/^\d{4}\/\d{2}$/', $file ) ) {
                    // File is in the format 'yyyy/mm', consider it as a month
                    $selectedFolders[] = $file;
                } elseif ( is_string( $file ) && preg_match( '/^\d{4}$/', $file ) ) {
                    // File is in the format 'yyyy', consider it as a year
                    $selectedYears[] = $file;
                }
            }

            // Loop through each selected year and delete files and folders accordingly
            foreach ($selectedYears as $year) {

                $upload_dir = wp_upload_dir();

                // Path for plugin’s uploads folder
                $plugin_upload_path = trailingslashit( $upload_dir['basedir'] ) . 'smart-bulk-content-remover/';

                // Make sure base folder exists
                if ( ! file_exists( $plugin_upload_path ) ) {
                    wp_mkdir_p( $plugin_upload_path );
                }

                // Build year directory safely
                $yearDirectory = trailingslashit( $plugin_upload_path ) . sanitize_file_name( $year );

                // Check if the year directory exists
                if (is_dir($yearDirectory)) {
                    // Loop through each month directory within the year
                    $months = glob($yearDirectory . '/*', GLOB_ONLYDIR);
                    foreach ($months as $monthDirectory) {
                        // Open the month directory
                        $monthDirHandle = opendir($monthDirectory);
                        // Loop through each file in the month directory
                        while (($monthFile = readdir($monthDirHandle)) !== false) {
                            // Exclude . and .. directories
                            if ($monthFile != '.' && $monthFile != '..') {
                                // Construct the full path of the file
                                $filePath = $monthDirectory . '/' . $monthFile;
                                // Attempt to delete the file from the file system
                                if (unlink($filePath)) {
                                    $filesDeleted = true;
                                    // Convert file path to URL
                                    $upload_dir       = wp_upload_dir();
                                    $plugin_upload_url = trailingslashit( $upload_dir['baseurl'] ) . 'smart-bulk-content-remover/';
                                    $fileUrl          = $plugin_upload_url . basename( $filePath );
                                    // Get attachment ID
                                    $attachment_id = attachment_url_to_postid($fileUrl);
                                    if ($attachment_id) {
                                        // Delete from the media library
                                        wp_delete_attachment($attachment_id, true); // True parameter also deletes media file permanently
                                    } else {
                                        //echo "Attachment ID not found for file: $filePath\n";
                                    }
                                } else {
                                    // File deletion from the file system failed
                                    //echo "Failed to delete from file system: $filePath\n";
                                }
                            }
                        }
                        // Close the month directory handle
                        closedir($monthDirHandle);
                        // Check if the month directory is empty and delete it
                        if (count(glob($monthDirectory . '/*')) === 0) {
                            rmdir($monthDirectory);
                            //echo "Deleted empty month directory: $monthDirectory\n";
                        }
                    }
                    // Check if the year directory is empty and delete it
                    if (count(glob($yearDirectory . '/*')) === 0) {
                        rmdir($yearDirectory);
                       // echo "Deleted empty year directory: $yearDirectory\n";
                    }
                }
            }
            // Loop through each selected folder and delete files accordingly
            foreach ($selectedFolders as $folder) {
                $upload_dir = wp_upload_dir();

                // Path for plugin’s uploads folder
                $plugin_upload_path = trailingslashit( $upload_dir['basedir'] ) . 'smart-bulk-content-remover/';

                // Make sure folder exists
                if ( ! file_exists( $plugin_upload_path ) ) {
                    wp_mkdir_p( $plugin_upload_path );
                }

                // Build the directory path with validated folder
                $directory = trailingslashit( $plugin_upload_path ) . sanitize_file_name( $folder );

                // Check if the directory exists
                if (is_dir($directory)) {
                    // Open the directory
                    $dirHandle = opendir($directory);
                    // Loop through each file in the directory
                    while (($file = readdir($dirHandle)) !== false) {
                        // Exclude . and .. directories
                        if ($file != '.' && $file != '..') {
                            // Construct the full path of the file
                            $filePath = $directory . '/' . $file;
                            // Check if the file is an image
                            if (wp_check_filetype($filePath)['type']) {
                                // Attempt to delete the file from the file system
                                if (unlink($filePath)) {
                                    $filesDeleted = true;
                                    // Convert file path to URL
                                    $upload_dir       = wp_upload_dir();
                                    $plugin_upload_url = trailingslashit( $upload_dir['baseurl'] ) . 'smart-bulk-content-remover/';
                                    $fileUrl          = $plugin_upload_url . basename( $filePath );
                                    // Get attachment ID
                                    $attachment_id = attachment_url_to_postid($fileUrl);
                                    if ($attachment_id) {
                                        // Delete from the media library
                                        wp_delete_attachment($attachment_id, true); // True parameter also deletes media file permanently
                                        //echo "Deleted from media library and file system: $filePath\n";
                                    } else {
                                        //echo "Attachment ID not found for file: $filePath\n";
                                    }
                                } else {
                                    // File deletion from the file system failed
                                    //echo "Failed to delete from file system: $filePath\n";
                                }
                            }
                        }
                    }
                    // Close the directory handle
                    closedir($dirHandle);
                    // Check if the directory is empty and delete it
                    if (count(glob($directory . '/*')) === 0) {
                        rmdir($directory);
                        //echo "Deleted empty directory: $directory\n";
                    }
                } else {
                    // Directory does not exist
                    //echo "Directory not found: $directory\n";
                }
            }
            // If no files were deleted, echo the message
            if (!empty($filesDeleted)) {
                wp_send_json_success( esc_html__('Your selected images are deleted.', 'smart-bulk-content-remover') );
            } else {
                wp_send_json_error(esc_html__('No images were deleted.', 'smart-bulk-content-remover'));
            }
            // It's good practice to exit after echoing the response
            wp_die();
        }

        /**
         * Load pages based on filters
         */
        public function abdfw_load_pages() {
            //check_ajax_referer( 'abdfw_nonce' );

            $search = sanitize_text_field( $_POST['search'] ?? '' );
            $status = sanitize_text_field( $_POST['status'] ?? '' );
            $author = intval( $_POST['author'] ?? 0 );
            $from   = sanitize_text_field( $_POST['from'] ?? '' );
            $to     = sanitize_text_field( $_POST['to'] ?? '' );

            $args = [
                'post_type'      => 'page',
                'posts_per_page' => -1,
                's'              => $search,
                'post_status'    => $status ? $status : [ 'publish','draft','pending','trash' ],
            ];

            if ( $author ) {
                $args['author'] = $author;
            }

            $date_query = [];
            if ( $from ) $date_query['after'] = $from;
            if ( $to )   $date_query['before'] = $to;

            if ( $date_query ) {
                $args['date_query'] = [ array_merge( $date_query, [ 'inclusive' => true ] ) ];
            }

            $pages = get_posts( $args );

            ob_start();
            if ( $pages ) { ?>
                <label><input type="checkbox" id="abdfw-page-select-all"><?php esc_html_e( 'Select All', 'smart-bulk-content-remover' ); ?></label>
                <ul>
                    <?php 
                    foreach ( $pages as $p ) {
                        echo '<li>
                            <label>
                                <input type="checkbox" name="pages[]" value="' . esc_attr( $p->ID ) . '">
                                ' . esc_html( $p->post_title ) . ' (status: ' . esc_html( $p->post_status ) . ')
                            </label>
                        </li>';
                    } 
                    ?>
                </ul> <?php 
            } else { ?>
                <p><?php esc_html_e( 'No pages found.', 'smart-bulk-content-remover' ); ?></p>
                <?php 
            }
            $html = ob_get_clean();

            wp_send_json_success( [ 'html' => $html ] );
        }

        /**
         * Delete selected pages (trash or permanent)
         */
        public function abdfw_delete_pages() {
            //check_ajax_referer( 'abdfw_nonce' );

            $pages     = isset( $_POST['pages'] ) ? array_map( 'intval', (array) $_POST['pages'] ) : [];
            $permanent = !empty( $_POST['permanent'] );

            if ( empty( $pages ) ) {
                wp_send_json_error( [ 'message' => 'No pages selected.' ] );
            }

            foreach ( $pages as $id ) {
                if ( $permanent ) {
                    wp_delete_post( $id, true );
                } else {
                    wp_trash_post( $id );
                }
            }

            wp_send_json_success( [ 'message' => 'Selected pages deleted.' ] );
        }

        /**
         * Add custom cron schedules.
         */
        public function abdfw_add_cron_schedules( $schedules ) {
            if ( ! isset( $schedules['weekly'] ) ) {
                $schedules['weekly'] = [
                    'interval' => 7 * DAY_IN_SECONDS,
                    'display'  => __( 'Once Weekly', 'smart-bulk-content-remover' ),
                ];
            }

            if ( ! isset( $schedules['monthly'] ) ) {
                $schedules['monthly'] = [
                    'interval' => 30 * DAY_IN_SECONDS,
                    'display'  => __( 'Once Monthly', 'smart-bulk-content-remover' ),
                ];
            }

            return $schedules;
        }

        /**
         * Ensure schedule exists on load.
         */
        public function abdfw_maybe_schedule_page_cleanup() {
            $settings = get_option( 'abdfw_page_cleanup_schedule', [] );
            if ( empty( $settings['enabled'] ) ) {
                return;
            }

            if ( ! wp_next_scheduled( 'abdfw_run_scheduled_page_cleanup' ) ) {
                $this->abdfw_reschedule_page_cleanup( $settings );
            }
        }

        /**
         * Save schedule settings and re-schedule cron.
         */
        public function abdfw_save_page_cleanup_schedule() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Permission denied.', 'smart-bulk-content-remover' ) ] );
            }

            check_ajax_referer( 'abdfw_page_schedule', 'nonce' );

            $enabled   = ! empty( $_POST['enabled'] );
            $frequency = sanitize_key( $_POST['frequency'] ?? 'daily' );
            if ( ! in_array( $frequency, [ 'daily', 'weekly', 'monthly' ], true ) ) {
                $frequency = 'daily';
            }

            $time = sanitize_text_field( $_POST['time'] ?? '02:00' );
            if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
                $time = '02:00';
            }

            $status = sanitize_key( $_POST['status'] ?? '' );
            if ( ! in_array( $status, [ 'publish', 'draft', 'pending', 'trash', '' ], true ) ) {
                $status = '';
            }

            $author = absint( $_POST['author'] ?? 0 );
            $from   = sanitize_text_field( $_POST['from'] ?? '' );
            $to     = sanitize_text_field( $_POST['to'] ?? '' );
            $search = sanitize_text_field( $_POST['search'] ?? '' );
            $permanent = ! empty( $_POST['permanent'] );

            $settings = [
                'enabled'   => $enabled ? 1 : 0,
                'frequency' => $frequency,
                'time'      => $time,
                'status'    => $status,
                'author'    => $author,
                'from'      => $from,
                'to'        => $to,
                'search'    => $search,
                'permanent' => $permanent ? 1 : 0,
            ];

            update_option( 'abdfw_page_cleanup_schedule', $settings );
            $this->abdfw_reschedule_page_cleanup( $settings );

            $next_run = wp_next_scheduled( 'abdfw_run_scheduled_page_cleanup' );
            $next_run_str = $next_run ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) : '';

            wp_send_json_success( [
                'message'  => __( 'Schedule saved.', 'smart-bulk-content-remover' ),
                'next_run' => $next_run_str,
            ] );
        }

        /**
         * Schedule or clear cron event.
         */
        private function abdfw_reschedule_page_cleanup( $settings ) {
            wp_clear_scheduled_hook( 'abdfw_run_scheduled_page_cleanup' );

            if ( empty( $settings['enabled'] ) ) {
                return;
            }

            $timestamp  = $this->abdfw_get_next_run_timestamp( $settings['time'] ?? '02:00' );
            $recurrence = $settings['frequency'] ?? 'daily';
            wp_schedule_event( $timestamp, $recurrence, 'abdfw_run_scheduled_page_cleanup' );
        }

        /**
         * Compute next run timestamp from time-of-day.
         */
        private function abdfw_get_next_run_timestamp( $time ) {
            $timezone = wp_timezone();
            $now = new DateTime( 'now', $timezone );
            $run = DateTime::createFromFormat( 'H:i', $time, $timezone );

            if ( ! $run ) {
                $run = clone $now;
                $run->setTime( 2, 0 );
            }

            $run->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'm' ), (int) $now->format( 'd' ) );

            if ( $run->getTimestamp() <= $now->getTimestamp() ) {
                $run->modify( '+1 day' );
            }

            return $run->getTimestamp();
        }

        /**
         * Cron: run scheduled page cleanup.
         */
        public function abdfw_run_scheduled_page_cleanup() {
            $settings = get_option( 'abdfw_page_cleanup_schedule', [] );
            if ( empty( $settings['enabled'] ) ) {
                return;
            }

            $args = [
                'post_type'      => 'page',
                'posts_per_page' => -1,
                's'              => sanitize_text_field( $settings['search'] ?? '' ),
                'post_status'    => ! empty( $settings['status'] ) ? sanitize_key( $settings['status'] ) : [ 'publish', 'draft', 'pending', 'trash' ],
                'fields'         => 'ids',
            ];

            $author = absint( $settings['author'] ?? 0 );
            if ( $author ) {
                $args['author'] = $author;
            }

            $date_query = [];
            if ( ! empty( $settings['from'] ) ) {
                $date_query['after'] = sanitize_text_field( $settings['from'] );
            }
            if ( ! empty( $settings['to'] ) ) {
                $date_query['before'] = sanitize_text_field( $settings['to'] );
            }
            if ( $date_query ) {
                $args['date_query'] = [ array_merge( $date_query, [ 'inclusive' => true ] ) ];
            }

            $pages = get_posts( $args );
            if ( empty( $pages ) ) {
                return;
            }

            $permanent = ! empty( $settings['permanent'] );
            foreach ( $pages as $id ) {
                if ( $permanent ) {
                    wp_delete_post( $id, true );
                } else {
                    wp_trash_post( $id );
                }
            }
        }
    

        /**
         * Load posts based on filters
         */
        public function abdfw_post_load_posts() {
            //check_ajax_referer( 'apr_nonce' );
            $post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
            $search = sanitize_text_field( $_POST['search'] ?? '' );
            $status = sanitize_text_field( $_POST['status'] ?? '' );
            $author = intval( $_POST['author'] ?? 0 );
            $from   = sanitize_text_field( $_POST['from'] ?? '' );
            $to     = sanitize_text_field( $_POST['to'] ?? '' );

            $args = [
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                's'              => $search,
                'post_status'    => $status ? $status : [ 'publish','draft','pending','trash' ],
            ];

            if ( $author ) {
                $args['author'] = $author;
            }

            $date_query = [];
            if ( $from ) $date_query['after'] = $from;
            if ( $to )   $date_query['before'] = $to;

            if ( $date_query ) {
                $args['date_query'] = [ array_merge( $date_query, [ 'inclusive' => true ] ) ];
            }

            $posts = get_posts( $args );

            ob_start();
            if ( $posts ) { ?>
                <label><input type="checkbox" id="abdfw_post_select_all"><?php esc_html_e( 'Select All', 'smart-bulk-content-remover' ); ?></label>
                <ul>
                    <?php 
                    foreach ( $posts as $p ) {
                        echo '<li>
                            <label>
                                <input type="checkbox" name="posts[]" value="' . esc_attr( $p->ID ) . '">
                                ' . esc_html( $p->post_title ) . ' (status: ' . esc_html( $p->post_status ) . ')
                            </label>
                        </li>';
                    } ?>
                </ul>
                <?php 
            } else { ?>
                <p><?php esc_html_e( 'No posts found.', 'smart-bulk-content-remover' ); ?></p>
                <?php 
            }
            $html = ob_get_clean();

            wp_send_json_success( [ 'html' => $html ] );
        }

        /**
         * Delete selected posts (trash or permanent)
         */
        public function abdfw_post_delete_posts() {
           // check_ajax_referer( 'apr_nonce' );

            $posts     = isset( $_POST['posts'] ) ? array_map( 'intval', (array) $_POST['posts'] ) : [];
            $permanent = !empty( $_POST['permanent'] );

            if ( empty( $posts ) ) {
                wp_send_json_error( [ 'message' => 'No posts selected.' ] );
            }

            foreach ( $posts as $id ) {
                if ( $permanent ) {
                    wp_delete_post( $id, true );
                } else {
                    wp_trash_post( $id );
                }
            }

            wp_send_json_success( [ 'message' => 'Selected posts deleted.' ] );
        }

        // comments delete code
        /** AJAX — Load comments */
        public function abdfw_load_comments() {
            // check_ajax_referer( 'acd_nonce' );

            $post_type = sanitize_key( $_POST['post_type'] ?? '' );
            $search    = sanitize_text_field( $_POST['search'] ?? '' );
            $status    = sanitize_text_field( $_POST['status'] ?? '' );
            $author    = intval( $_POST['author'] ?? 0 );
            $from      = sanitize_text_field( $_POST['from'] ?? '' );
            $to        = sanitize_text_field( $_POST['to'] ?? '' );

            $args = [
                'status'       => $status ? $status : 'all',
                'search'       => $search,
                'number'       => 0, // no limit
            ];

            if ( $author ) {
                $args['user_id'] = $author;
            }

            // Filter by date range
            if ( $from || $to ) {
                $args['date_query'] = [ [
                    'after'     => $from ?: '',
                    'before'    => $to ?: '',
                    'inclusive' => true,
                ] ];
            }

            // Filter by post type
            if ( $post_type ) {
                // get post IDs of this post type
                $post_ids = get_posts( [
                    'post_type'      => $post_type,
                    'posts_per_page' => -1,
                    'fields'         => 'ids'
                ] );
                $args['post__in'] = $post_ids;
            }

            $comments = get_comments( $args );

            ob_start();
            if ( $comments ) { ?>
                <label><input type="checkbox" id="abdfw-comment-select-all"><?php esc_html_e( 'Select All', 'smart-bulk-content-remover' ); ?></label>
                <ul>
                    <?php 
                    foreach ( $comments as $c ) {
                        $post_title = get_the_title( $c->comment_post_ID );
                        echo '<li>
                            <label>
                                <input type="checkbox" name="comments[]" value="' . esc_attr( $c->comment_ID ) . '">
                                <strong>' . esc_html( $c->comment_author ) . ':</strong> '
                                . esc_html( wp_trim_words( $c->comment_content, 12 ) ) .
                                ' <em>on ' . esc_html( $post_title ) . '</em>
                                (status: ' . esc_html( $c->comment_approved ) . ')
                            </label>
                        </li>';
                    }
                    ?>
                </ul>
                <?php 
            } else { ?>
                <p><?php esc_html_e( 'No comments found.', 'smart-bulk-content-remover' ); ?></p>
                    <?php 
            }
            $html = ob_get_clean();

            wp_send_json_success( [ 'html' => $html ] );
        }

        /** AJAX — Delete comments */
        public function abdfw_delete_comments() {
            // check_ajax_referer( 'acd_nonce' );

            $comments  = isset( $_POST['comments'] ) ? array_map( 'intval', (array) $_POST['comments'] ) : [];
            $permanent = ! empty( $_POST['permanent'] );

            if ( empty( $comments ) ) {
                wp_send_json_error( [ 'message' => 'No comments selected.' ] );
            }

            foreach ( $comments as $id ) {
                if ( $permanent ) {
                    wp_delete_comment( $id, true );
                } else {
                    wp_trash_comment( $id );
                }
            }

            wp_send_json_success( [ 'message' => 'Selected comments deleted.' ] );
        }

    }
}