<?php

/**
 * The core plugin class
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      2.0.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/includes
 */


// Defines internationalization, admin-specific hooks and public hooks
class Rate_My_Post
{

    // Registers all hooks for the plugin
    protected $loader;

    // FeedbackWP - string
    protected $rate_my_post;

    //Plugin current version - string
    protected $version;

    // Set the FeedbackWP and the plugin version, and fire the methods!
    public function __construct()
    {
        $this->version = RATE_MY_POST_VERSION;

        $this->rate_my_post = 'rate-my-post';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    //Load the required dependencies for this plugin.

    private function load_dependencies()
    {
        // Manages hooks
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rate-my-post-loader.php';

        // Manages admin side
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rate-my-post-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rmp-data-import-export.php';

        // Manages public side
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-rate-my-post-public.php';

        // Common methods for public and admin side
        require_once plugin_dir_path(dirname(__FILE__)) . 'common/class-rate-my-post-common.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'common/class-rate-my-post-blocks.php';

        // Plugin default settings
        require_once plugin_dir_path(dirname(__FILE__)) . 'common/class-rate-my-post-settings.php';

        // The WP_Lock class
        require_once plugin_dir_path(dirname(__FILE__)) . 'common/class-rate-my-post-mutex.php';

        // Top Rated Posts Widget
        require_once plugin_dir_path(dirname(__FILE__)) . 'widgets/rate-my-post-top-rated-widget.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'widgets/top-rated-widget-shortcode.php';

        //Fire the loader
        $this->loader = new Rate_My_Post_Loader();
        new Rate_My_Post_Data_Import_Export();
    }

    // Register admin hooks
    private function define_admin_hooks()
    {
        /** @var Rate_My_Post_Admin $plugin_admin */
        $plugin_admin = new Rate_My_Post_Admin($this->get_rate_my_post(), $this->get_version());
        //CSS
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        //JS
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        //META BOX
        $this->loader->add_action('add_meta_boxes', $plugin_admin, 'meta_boxes');
        //UPDATE RESULTS AJAX
        $this->loader->add_action('wp_ajax_update_results', $plugin_admin, 'backend_results_update');
        //RESET RESULTS AJAX
        $this->loader->add_action('wp_ajax_reset_results', $plugin_admin, 'backend_results_reset');
        //DELETE FEEDBACK AJAX
        $this->loader->add_action('wp_ajax_delete_feedback', $plugin_admin, 'delete_feedback');
        //INDIVIDUALLY DELETE FEEDBACK AJAX
        $this->loader->add_action('wp_ajax_individually_delete_feedback', $plugin_admin, 'individually_delete_feedback');
        //MENU SECTION
        $this->loader->add_action('admin_menu', $plugin_admin, 'menu_section');
        //OPTIONS UPDATE AJAX
        $this->loader->add_action('wp_ajax_rmp_update_options', $plugin_admin, 'options_update');
        //OPTIONS RESET AJAX
        $this->loader->add_action('wp_ajax_reset_options', $plugin_admin, 'options_reset');
        //CUSTOMIZATION UPDATE AJAX
        $this->loader->add_action('wp_ajax_update_customization', $plugin_admin, 'customization_update');
        //CUSTOMIZATION RESET AJAX
        $this->loader->add_action('wp_ajax_reset_customization', $plugin_admin, 'customization_reset');
        //SECURITY UPDATE AJAX
        $this->loader->add_action('wp_ajax_update_security', $plugin_admin, 'security_update');
        //HIDE RMP FIELDS IN EDIT POST
        $this->loader->add_filter('is_protected_meta', $plugin_admin, 'hide_custom_fields', 10, 2);
        //MIGRATION TOOLS
        $this->loader->add_action('wp_ajax_migrate_data', $plugin_admin, 'migration_tools');
        //WIDGETS
        $this->loader->add_action('widgets_init', $plugin_admin, 'register_widgets');
        // WIPE DATA
        $this->loader->add_action('wp_ajax_wipe_data', $plugin_admin, 'wipe_data');
        // ADMIN NOTICES
        $this->loader->add_action('admin_notices', $plugin_admin, 'admin_notices');
        // DISMISS ADMIN NOTICE
        $this->loader->add_action('wp_ajax_rmp_dismiss_notice', $plugin_admin, 'dismiss_notice');

        Rate_My_Post_Analytics::init();
        Rate_My_Post_Stats::init();
    }

    public function avada_theme_compat()
    {
        // Avada theme incompatibility fix
        add_action('awb_remove_third_party_the_content_changes', function () {

            global $plugin_public;

            remove_filter('the_content', [$plugin_public, 'automatically_add_rating_widget']);
            remove_filter('the_content', [$plugin_public, 'automatically_add_result_widget']);
        });

        add_action('awb_readd_third_party_the_content_changes', function () {

            global $plugin_public;

            add_filter('the_content', [$plugin_public, 'automatically_add_rating_widget']);
            add_filter('the_content', [$plugin_public, 'automatically_add_result_widget']);
        });
    }

    // Register public hooks
    private function define_public_hooks()
    {
        global $plugin_public;

        Rate_My_Post_Top_Rated_Widget_Shortcode::init();

        $plugin_public = new Rate_My_Post_Public($this->get_rate_my_post(), $this->get_version());

        Rate_My_Post_Blocks::get_instance();

        //CSS
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        //JS
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        //SHORTCODES
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
        //INCLUDE RATING WIDGET AUTOMATICALLY - FILTER CONTENT
        $this->loader->add_filter('the_content', $plugin_public, 'automatically_add_rating_widget');
        //INCLUDE RESULT WIDGET AUTOMATICALLY - FILTER CONTENT
        $this->loader->add_filter('the_content', $plugin_public, 'automatically_add_result_widget');
        //LOAD RESULTS AJAX PRIV
        $this->loader->add_action('wp_ajax_load_results', $plugin_public, 'load_results');
        //LOAD RESULTS AJAX NOPRIV
        $this->loader->add_action('wp_ajax_nopriv_load_results', $plugin_public, 'load_results');
        //PROCESS RATING AJAX PRIV
        $this->loader->add_action('wp_ajax_process_rating', $plugin_public, 'process_rating');
        //PROCESS RATING AJAX NOPRIV
        $this->loader->add_action('wp_ajax_nopriv_process_rating', $plugin_public, 'process_rating');
        //PROCESS FEEDBACK AJAX PRIV
        $this->loader->add_action('wp_ajax_process_feedback', $plugin_public, 'process_feedback');
        //PROCESS FEEDBACK AJAX NOPRIV
        $this->loader->add_action('wp_ajax_nopriv_process_feedback', $plugin_public, 'process_feedback');
        //PROCESS AMP RATING PRIV
        $this->loader->add_action('wp_ajax_process_rating_amp', $plugin_public, 'process_rating_amp');
        //PROCESS AMP RATING NO PRIV
        $this->loader->add_action('wp_ajax_nopriv_process_rating_amp', $plugin_public, 'process_rating_amp');
        //RATINGS ON ARCHIVE PAGES
        $this->loader->add_filter('the_title', $plugin_public, 'ratings_archive_pages', 10, 2);
        // PRELOAD CUSTOM FONT
        $this->loader->add_action('wp_head', $plugin_public, 'preload_fonts', 1);
        // STYLE FOR AMP PLUGINS https://wordpress.org/plugins/amp/ and https://wordpress.org/plugins/accelerated-mobile-pages/
        $this->loader->add_action('amp_post_template_css', $plugin_public, 'amp_plugin_style', 1, 1);
        $this->loader->add_action('wp_head', $plugin_public, 'amp_plugin_style', 1, 1);
    }

    // Run the loader to execute all hooks
    public function run()
    {
        $this->loader->run();

        $this->avada_theme_compat();
    }

    // FeedbackWP for identification
    public function get_rate_my_post()
    {
        return $this->rate_my_post;
    }

    // The reference to the class that orchestrates the hooks with the plugin
    public function get_loader()
    {
        return $this->loader;
    }

    // Retrieve the plugin version
    public function get_version()
    {
        return $this->version;
    }
}
