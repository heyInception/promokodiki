<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      2.0.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/admin
 */

class Rate_My_Post_Admin
{
    // plugin name - string
    private $rate_my_post;

    // plugin version - string
    private $version;

    // init
    public function __construct($rate_my_post, $version)
    {
        $this->rate_my_post = $rate_my_post;
        $this->version      = $version;

        require_once dirname(__FILE__) . '/analytics/analytics.php';
        require_once dirname(__FILE__) . '/stats/stats.php';

        add_action('admin_notices', [$this, 'cpt_header_design'], 1);

        add_action('admin_head', [$this, 'fix_current_item']);

        $this->posts_column_rating();
    }

    /**
     * Make sure our admin menu item is highlighted.
     */
    public function fix_current_item()
    {
        global $parent_file, $submenu_file, $post_type;

        if ('crw' === $post_type) {
            $parent_file  = 'rate-my-post';
            $submenu_file = 'edit.php?post_type=crw';
        }
    }

    private function is_rmp_page()
    {
        global $pagenow, $typenow;

        $screen = function_exists('get_current_screen') ? get_current_screen() : false;
        if ($screen && $screen->is_block_editor()) return false;

        if ($typenow == 'crw') return true;

        if (isset($_GET['page']) && strstr($_GET['page'], 'rate-my-post')) return true;

        return false;
    }

    public function cpt_header_design()
    {
        if ( ! $this->is_rmp_page()) return;

        echo '<div class="rmp-header-wrap">';
        Rate_My_Post_Admin::admin_header_design();
        echo '</div>';
    }

    private static function rmp_pro_upsell_sidebar_content()
    {
        $integrations = [
            esc_html__('Custom post types support', 'rate-my-post'),
            esc_html__('Support for different rating icons', 'rate-my-post'),
            esc_html__('Robust structured data for rich snippets', 'rate-my-post'),
            esc_html__('Create custom rating widgets', 'rate-my-post'),
            esc_html__('Bulk rate existing posts', 'rate-my-post'),
            esc_html__('Premium support', 'rate-my-post'),
        ];

        $upsell_url = 'https://feedbackwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=sidebar_upsell';

        $content = '<p>';
        $content .= sprintf(
            esc_html__('Enhance the power of FeedbackWP with the premium version that include more features. %sLearn more%s', 'rate-my-post'),
            '<a target="_blank" href="' . $upsell_url . '">', '</a>'
        );
        $content .= '</p>';

        $content .= '<ul>';

        foreach ($integrations as $integration) :
            $content .= sprintf('<li>%s</li>', $integration);
        endforeach;

        $content .= '</ul>';

        $content .= '<a href="' . $upsell_url . '" target="__blank" class="button">' . esc_html__('Get FeedbackWP Premium →', 'rate-my-post') . '</a>';

        return $content;
    }

    private static function available_shortcodes_content()
    {
        $content = '<p style="border-bottom: 1px solid #eee">';
        $content .= sprintf(
            esc_html__('%s[ratemypost]%s displays rating widget.', 'rate-my-post'),
            '<code>', '</code>'
        );
        $content .= '</p>';
        $content .= sprintf(
            esc_html__('%s[ratemypost id="1"]%s displays rating widget for the post with the ID of 1.', 'rate-my-post'),
            '<code>', '</code>'
        );
        $content .= '</p>';

        $content .= '<p style="border-bottom: 1px solid #eee">';
        $content .= sprintf(
            esc_html__('%s[ratemypost-result]%s displays results widget.', 'rate-my-post'),
            '<code>', '</code>'
        );
        $content .= '</p>';
        $content .= '<p style="border-bottom: 1px solid #eee">';
        $content .= sprintf(
            esc_html__('%s[ratemypost-result id="1"]%s displays results widget for the post with the ID of 1.', 'rate-my-post'),
            '<code>', '</code>'
        );
        $content .= '</p>';

        $content .= '<p>';
        $content .= sprintf(
            esc_html__('%s[ratemypost-top-rated]%s displays a list of top-rated posts. %sLearn more%s', 'rate-my-post'),
            '<code>', '</code>', '<a href="https://feedbackwp.com/docs/#Top-rated_Posts_Widget" target="__blank">', '</a>'
        );
        $content .= '</p>';

        return $content;
    }

    private static function rmp_support_docs_sidebar_content($support_url, $review_url)
    {
        $link_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg>';

        $content = '<p>';

        $content .= sprintf(
            esc_html__('Whether you need help or have a new feature request, let us know. %sRequest Support%s', 'rate-my-post'),
            '<a class="feedbackwp-link" href="' . $support_url . '" target="_blank">', $link_icon . '</a>'
        );

        $content .= '</p>';

        $content .= '<p>';
        $content .= sprintf(
            esc_html__('Detailed documentation is also available on the plugin website. %sView Knowledge Base%s', 'rate-my-post'),
            '<a class="feedbackwp-link" href="https://feedbackwp.com/docs/" target="_blank">', $link_icon . '</a>'
        );

        $content .= '</p>';

        $content .= '<p>';
        $content .= sprintf(
            esc_html__('If you are enjoying FeedbackWP and find it useful, please consider leaving a ★★★★★ review on WordPress.org. %sLeave a Review%s', 'rate-my-post'),
            '<a target="_blank" class="feedbackwp-link" href="' . $review_url . '">', $link_icon . '</a>'
        );
        $content .= '</p>';

        return $content;
    }

    public static function sidebar_content()
    {
        $support_url = 'https://wordpress.org/support/plugin/rate-my-post/';
        $review_url  = 'https://wordpress.org/support/plugin/rate-my-post/reviews/?filter=5#new-post';

        if (class_exists('Rate_My_Post_Pro')) {
            $support_url = 'https://feedbackwp.com/support/';
        }

        if ( ! class_exists('Rate_My_Post_Pro')) { ?>
            <div class="postbox">
                <div class="postbox-header">
                    <h3 class="hndle is-non-sortable">
                        <span><?php esc_html_e('Upgrade to Premium', 'rate-my-post'); ?></span>
                    </h3>
                </div>

                <div class="inside">
                    <?php echo self::rmp_pro_upsell_sidebar_content() ?>
                </div>
            </div>
        <?php } ?>

        <div class="postbox">
            <div class="postbox-header">
                <h3 class="hndle is-non-sortable">
                    <span><?php esc_html_e('Available Shortcodes', 'rate-my-post'); ?></span>
                </h3>
            </div>
            <div class="inside">
                <?php echo self::available_shortcodes_content() ?>
            </div>
        </div>

        <div class="postbox">
            <div class="postbox-header">
                <h3 class="hndle is-non-sortable">
                    <span><?php esc_html_e('Docs & Support', 'rate-my-post'); ?></span>
                </h3>
            </div>

            <div class="inside">
                <?php echo self::rmp_support_docs_sidebar_content($support_url, $review_url) ?>
            </div>
        </div>
        <?php
    }

    //---------------------------------------------------
    // REGISTER AND ENQUEUE ADMIN CSS
    //---------------------------------------------------

    public function enqueue_styles()
    {
        wp_register_style(
            $this->rate_my_post . '-block',
            plugin_dir_url(__FILE__) . 'css/rate-my-post-admin.css',
            array(),
            $this->version,
            'all'
        );

        // enqueue style
        wp_enqueue_style($this->rate_my_post . '-block');
    }

    //---------------------------------------------------
    // REGISTER AND ENQUEUE ADMIN JS
    //---------------------------------------------------

    public function enqueue_scripts()
    {
        // register JS
        wp_register_script(
            $this->rate_my_post,
            plugin_dir_url(__FILE__) . 'js/rate-my-post-admin.min.js',
            array('jquery'),
            $this->version,
            false
        );
        // enqueue JS
        wp_enqueue_script($this->rate_my_post);
        // localize script
        wp_localize_script(
            $this->rate_my_post,
            'rmp_options',
            array(
                'admin_ajax'   => admin_url('admin-ajax.php'),
                'postID'       => get_the_id(),
                'save'         => (esc_html__('Saving', 'rate-my-post')),
                'resetConfirm' => (esc_html__('Are your sure you want to reset the settings?', 'rate-my-post')),
                'nonce'        => wp_create_nonce('rmp_admin_save'),
            )
        );
    }

    //---------------------------------------------------
    // META BOX - RATINGS UPDATE AND FEEDBACK DISPLAY
    //---------------------------------------------------
    public function meta_boxes()
    {
        $post_id = get_the_id();
        if ( ! $this->has_required_capability($post_id)) return;

        if ( ! Rate_My_Post_Common::is_show_for_post_edit_screen()) return;

        add_meta_box('rmp-rate-id', 'FeedbackWP Ratings', array($this, 'display_metabox'), self::define_post_types());
    }

    public function display_metabox()
    {
        ob_start();
        include_once plugin_dir_path(__FILE__) . 'templates/meta-box.php';
        echo ob_get_clean();
    }

    public function display_customization_metabox()
    {
        ob_start();
        include_once plugin_dir_path(__FILE__) . 'templates/meta-box-customization.php';
        echo ob_get_clean();
    }

    //---------------------------------------------------
    // ADMIN OR AUTHOR UPDATE RESULTS - AJAX
    //---------------------------------------------------
    public function backend_results_update()
    {
        if (wp_doing_ajax()) {

            $data = [
                'valid'      => true,
                'successMsg' => esc_html__('Successfully Saved!', 'rate-my-post'),
                'errorMsg'   => []
            ];
            // variables
            $vote_count = absint($_POST['votes']);
            $avg_rating = floatval($_POST['avg']);
            $post_id    = absint($_POST['postID']);
            $nonce      = isset($_POST['nonce']) ? $_POST['nonce'] : false;

            // security checks
            if ( ! $this->has_required_capability($post_id)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            // die if failed a security check
            if ( ! $data['valid']) wp_send_json($data);

            $action = Rate_My_Post_Common::update_post_id_rating($post_id, $vote_count, $avg_rating);

            if (is_wp_error($action)) {
                $data['valid']      = false;
                $data['errorMsg'][] = $action->get_error_message();
            }

            wp_send_json($data);
        }
    }

    //---------------------------------------------------
    // ADMIN OR AUTHOR RESET RATINGS
    //---------------------------------------------------

    public function backend_results_reset()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Successfully Saved!', 'rate-my-post'),
                'errorMsg'   => array()
            );
            // variables
            $post_id = absint($_POST['postID']);
            $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : false;

            // security checks
            if ( ! $this->has_required_capability($post_id)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            // die if failed a security check
            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            delete_post_meta($post_id, 'rmp_avg_rating');
            delete_post_meta($post_id, 'rmp_vote_count');
            delete_post_meta($post_id, 'rmp_rating_val_sum');
            echo json_encode($data);
        }
        die();
    }


    //---------------------------------------------------
    // DELETE FEEDBACK - AJAX
    //---------------------------------------------------
    public function delete_feedback()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Feedback successfully deleted!', 'rate-my-post'),
                'errorMsg'   => array()
            );
            // variables
            $post_id = absint($_POST['postID']);
            $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : false;
            // security checks
            if ( ! $this->has_required_capability($post_id)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }
            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }
            // die if failed a security check
            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }
            //delete feedback
            delete_post_meta($post_id, 'rmp_feedback_val');
            delete_post_meta($post_id, 'rmp_feedback_val_new');
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // INDIVIDUALLY DELETE FEEDBACK - AJAX
    //---------------------------------------------------
    public function individually_delete_feedback()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'feedbackID' => false,
                'errorMsg'   => array()
            );

            // variables
            $post_id     = absint($_POST['postID']);
            $feedback_id = sanitize_text_field($_POST['feedbackID']);
            $nonce       = $_POST['nonce'] ?? false;

            // security checks
            if ( ! $this->has_required_capability($post_id)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            // die if failed a security check
            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            $feedback = get_post_meta($post_id, 'rmp_feedback_val_new', true);
            if (is_array($feedback) && ! empty($feedback)) {
                foreach ($feedback as $key => $value) {
                    if ($value['id'] == $feedback_id) {
                        unset($feedback[$key]); //remove this feedback from array
                    }
                }
            }
            update_post_meta($post_id, 'rmp_feedback_val_new', $feedback);
            $data['feedbackID'] = $feedback_id;
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // MENU SECTION
    //---------------------------------------------------
    public function menu_section()
    {
        // main item
        add_menu_page(
            'FeedbackWP',
            'FeedbackWP',
            'edit_others_posts',
            'rate-my-post',
            array($this, 'menu_section_display'),
            'dashicons-thumbs-up',
            24
        );
        // settings item
        add_submenu_page(
            'rate-my-post',
            'FeedbackWP Settings',
            esc_html__('Settings', 'rate-my-post'),
            'edit_others_posts',
            'rate-my-post'
        );
        // stats item
        $stats_hook = add_submenu_page(
            'rate-my-post',
            'FeedbackWP Stats',
            esc_html__('Stats', 'rate-my-post'),
            'edit_others_posts',
            'rate-my-post-stats',
            ['Rate_My_Post_Stats', 'admin_page_callback']
        );
        // analytics item
        $analytics_hook = add_submenu_page(
            'rate-my-post',
            'FeedbackWP Analytics',
            esc_html__('Analytics', 'rate-my-post'),
            'edit_others_posts',
            'rate-my-post-analytics',
            ['Rate_My_Post_Analytics', 'admin_page_callback']
        );

        add_action("load-$stats_hook", ['Rate_My_Post_Stats', 'screen_option']);
        add_action("load-$analytics_hook", ['Rate_My_Post_Analytics', 'screen_option']);

        // custom rating widgets
        if (class_exists('Rate_My_Post_Pro')) { // PRO only
            add_submenu_page(
                'rate-my-post',
                esc_html__('Custom Rating Widgets', 'rate-my-post'),
                esc_html__('Custom Rating Widgets', 'rate-my-post'),
                'edit_others_posts',
                'edit.php?post_type=crw'
            );
        }
    }

    public function menu_section_display()
    {
        if (current_user_can('manage_options')) {
            ob_start();
            include_once plugin_dir_path(__FILE__) . 'templates/menu.php';
            echo ob_get_clean();
        } else {
            echo '<p>' . esc_html__('You do not have adequate permissions for this action!', 'rate-my-post') . '</p>';
        }
    }

    //---------------------------------------------------
    // UPDATE OPTIONS
    //---------------------------------------------------
    public function options_update()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Successfully Saved!', 'rate-my-post'),
                'errorMsg'   => array()
            );

            // variables
            $default_options = Rate_My_Post_Settings::default_options();
            $updated_options = array();
            $nonce           = $_POST['nonce'] ?? false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            foreach ($_POST as $key => $value) {
                if (array_key_exists($key, $default_options)) {
                    //sanitize options before saving them
                    $value = $this->sanitize_options($key, $value);
                    //push new options to array
                    $updated_options[$key] = $value;
                }
            }

            // update options
            update_option('rmp_options', $updated_options);
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // RESET OPTIONS
    //---------------------------------------------------
    public function options_reset()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Settings Reset Done', 'rate-my-post'),
                'errorMsg'   => array()
            );

            $nonce = $_POST['nonce'] ?? false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            delete_option('rmp_options');
            $default_options = Rate_My_Post_Settings::default_options();
            add_option('rmp_options', $default_options);
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // UPDATE CUSTOMIZATION
    //---------------------------------------------------
    public function customization_update()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Successfully Saved!', 'rate-my-post'),
                'errorMsg'   => array()
            );

            // variables
            $default_customization = Rate_My_Post_Settings::default_customization();
            $updated_customization = array();
            $nonce                 = isset($_POST['nonce']) ? $_POST['nonce'] : false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }
            // proceed
            foreach ($_POST as $key => $value) {
                if (array_key_exists($key, $default_customization)) {
                    $value = $this->sanitize_customization($key, $value);
                    //push new customization to array
                    $updated_customization[$key] = $value;
                }
            }
            // verify that all customization was provided
            $errored_customization = $this->verify_options($default_customization, $updated_customization);
            // fix if not all customization was provided
            if ($errored_customization) {
                $updated_customization  = $this->fix_options(
                    $default_customization,
                    $updated_customization,
                    $errored_customization
                );
                $errored_options_string = implode(', ', $errored_customization);
                $data['successMsg']     = esc_html__(
                                              'Settings Partially Saved. Unable to save: ',
                                              'rate-my-post'
                                          ) . $errored_options_string . '. ' . esc_html__(
                                              'Try clearing all caches, especially your browser cache!',
                                              'rate-my-post'
                                          );
            }
            // update customization
            update_option('rmp_customize_strings', $updated_customization);
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // RESET CUSTOMIZATION
    //---------------------------------------------------
    public function customization_reset()
    {
        //only admin can update customization options
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Settings Reset Done', 'rate-my-post'),
                'errorMsg'   => array()
            );

            $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            delete_option('rmp_customize_strings');
            $default_customization = Rate_My_Post_Settings::default_customization();
            add_option('rmp_customize_strings', $default_customization);
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // UPDATE SECURITY OPTIONS
    //---------------------------------------------------
    public function security_update()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => esc_html__('Successfully Saved!', 'rate-my-post'),
                'errorMsg'   => array()
            );

            // variables
            $default_security = Rate_My_Post_Settings::security_options();
            $updated_security = array();
            $nonce            = isset($_POST['nonce']) ? $_POST['nonce'] : false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            foreach ($_POST as $key => $value) {
                if (array_key_exists($key, $default_security)) {
                    $value                  = $this->sanitize_security($key, $value);
                    $updated_security[$key] = $value;
                }
            }
            // verify that all options were provided
            $errored_security = $this->verify_options($default_security, $updated_security);
            // fix if not all options were provided
            if ($errored_security) {
                $updated_security        = $this->fix_options($default_security, $updated_security, $errored_security);
                $errored_security_string = implode(', ', $errored_security);
                $data['successMsg']      = esc_html__(
                                               'Settings Partially Saved. Unable to save: ',
                                               'rate-my-post'
                                           ) . $errored_security_string . '. ' . esc_html__(
                                               'Try clearing all caches, especially your browser cache!',
                                               'rate-my-post'
                                           );
            }
            // update security options
            update_option('rmp_security', $updated_security);
            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // HIDE RATE MY POST CUSTOM FIELDS IN EDIT POST SECTION
    //---------------------------------------------------

    // prevents custom fields from getting reverted on post update
    public function hide_custom_fields($protected, $meta_key)
    {
        if ($meta_key === 'rmp_feedback_val' || $meta_key === 'rmp_rating_val_sum' || $meta_key === 'rmp_vote_count' || $meta_key === 'rmp_avg_rating') {
            return true;
        } else {
            return $protected;
        }
    }

    //---------------------------------------------------
    // REGISTER WIDGETS
    //---------------------------------------------------
    public function register_widgets()
    {
        // top rated posts widget
        register_widget('Rate_My_Post_Top_Rated_Widget');
    }

    //---------------------------------------------------
    // WIPE DATA - AJAX
    //---------------------------------------------------
    public function wipe_data()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => false,
                'errorMsg'   => array()
            );

            $nonce = $_POST['nonce'] ?? false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            delete_post_meta_by_key('rmp_vote_count');
            delete_post_meta_by_key('rmp_rating_val_sum');
            delete_post_meta_by_key('rmp_avg_rating');
            delete_post_meta_by_key('rmp_feedback_val');
            delete_post_meta_by_key('rmp_feedback_val_new');

            global $wpdb;
            $table_name = $wpdb->prefix . 'rmp_analytics';
            $wpdb->query("TRUNCATE TABLE $table_name");

            $data['successMsg'] = esc_html__('All data deleted', 'rate-my-post');

            echo json_encode($data);
        }
        die();
    }

    //---------------------------------------------------
    // DISMISS NOTICE - AJAX
    //---------------------------------------------------

    public function dismiss_notice()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => false,
                'errorMsg'   => array()
            );

            $nonce  = $_POST['nonce'] ?? false;
            $notice = sanitize_text_field($_POST['noticeKey']);

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            $admin_notices = get_option('rmp_admin_notices');

            if (array_key_exists($notice, $admin_notices)) {
                $admin_notices[$notice] = true;
                update_option('rmp_admin_notices', $admin_notices);
            }

            echo json_encode($data);;
        }
        die();
    }


    //---------------------------------------------------
    // MIGRATION TOOLS - AJAX
    //---------------------------------------------------
    public function migration_tools()
    {
        if (wp_doing_ajax()) {
            $data = array(
                'valid'      => true,
                'successMsg' => false,
                'errorMsg'   => array()
            );

            $nonce = $_POST['nonce'] ?? false;

            if ( ! $this->is_administrator()) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__(
                    'You do not have adequate permissions for this action!',
                    'rate-my-post'
                );
            }

            if ( ! $this->has_valid_nonce($nonce)) {
                $data['valid']      = false;
                $data['errorMsg'][] = esc_html__('Invalid nonce!', 'rate-my-post');
            }

            if ( ! $data['valid']) {
                echo json_encode($data);
                die();
            }

            // proceed
            if (function_exists('kk_star_ratings')) { // kk star ratings
                $migrated_posts     = $this->migrate_ratings('_kksr_ratings', '_kksr_casts');
                $data['successMsg'] = $this->verify_migration($migrated_posts);
            } elseif (function_exists('the_ratings')) { // wp post ratings
                $migrated_posts     = $this->migrate_ratings('ratings_score', 'ratings_users');
                $data['successMsg'] = $this->verify_migration($migrated_posts);
            } elseif (function_exists('yasr_fs') || class_exists('\YasrDB') || defined('YASR_VERSION_NUM')) { // yasr
                $migrated_posts     = $this->migrate_ratings('', '', 'yasr');
                $data['successMsg'] = $this->verify_migration($migrated_posts);
            } else {
                $data['successMsg'] = esc_html__('No rating plugin found! Migration is not possible!', 'rate-my-post');
            }
            echo json_encode($data);
        }
        die();
    }

    // strings for completed migration
    private function verify_migration($migrated_posts)
    {
        if ($migrated_posts) {
            return esc_html__(
                       'Migration successful! Number of posts affected',
                       'rate-my-post'
                   ) . ': ' . $migrated_posts;
        } else {
            return esc_html__('No existing ratings found! Nothing to migrate!', 'rate-my-post');
        }
    }

    private function yasr_get_votes($post_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(id) as count, SUM(vote) as votes FROM " .
                YASR_LOG_TABLE .
                " WHERE post_id=%d",
                absint($post_id)
            )
        );
    }

    // migrate from other plugins to rate my post
    private function migrate_ratings($ratings_sum_field, $vote_count_field, $specific_plugin = false)
    {
        $count = 0;
        $merge = false;
        if (has_filter('rmp_migrate_merge')) {
            $merge = apply_filters('rmp_migrate_merge', $merge);
        }

        $args = array(
            'fields'         => 'ids',
            'post_type'      => array(
                'post',
                'page',
            ),
            'posts_per_page' => -1,
        );

        if ('_kksr_ratings' == $ratings_sum_field) {
            $locations = (array)\Bhittani\StarRating\core\functions\option('locations');
            unset($locations['home']);
            unset($locations['archives']);

            if ( ! empty($locations)) {
                $args['post_type'] = array_merge($locations, $args['post_type']);
            }
        }

        $the_query = new WP_Query($args);
        if ($the_query->have_posts()) {
            while ($the_query->have_posts()) {
                $the_query->the_post();
                $post_id = get_the_id();

                if ( ! $specific_plugin) { // migration based on post meta
                    $ratings_sum = absint(get_post_meta($post_id, $ratings_sum_field, true));
                    $vote_count  = absint(get_post_meta($post_id, $vote_count_field, true));

                    if ($ratings_sum && $vote_count) { // post is rated in another plugin
                        $count++;
                        if ($merge) { // merge the ratings if enabled via filter
                            $ratings_sum = get_post_meta($post_id, 'rmp_rating_val_sum', true) + $ratings_sum;
                            $vote_count  = get_post_meta($post_id, 'rmp_vote_count', true) + $vote_count;
                        }
                        //update ratings
                        update_post_meta($post_id, 'rmp_rating_val_sum', $ratings_sum);
                        update_post_meta($post_id, 'rmp_vote_count', $vote_count);
                    }
                } // end migration based on post meta

                // migration for yasr plugin
                if ($specific_plugin === 'yasr') {
                    $yasr_info = $this->yasr_get_votes($post_id);

                    if ($yasr_info) {
                        $vote_count  = $yasr_info->count;
                        $ratings_sum = $yasr_info->votes;

                        if ($ratings_sum && $vote_count) { // post is rated in yasr
                            $count++;
                            update_post_meta($post_id, 'rmp_rating_val_sum', $ratings_sum);
                            update_post_meta($post_id, 'rmp_vote_count', $vote_count);
                        }
                    }
                } // end yasr

            } // end while
        } // endif

        wp_reset_postdata();

        // ensure structured datatype is set.
        $existing                       = get_option("rmp_options");
        $existing['structuredDataType'] = 'CreativeWorkSeries';
        update_option('rmp_options', $existing);

        return $count;
    }

    // check for an existing rating plugin
    private function existing_rating_plugin()
    {
        if (function_exists('kk_star_ratings')) {
            return 'KK StarRatings';
        } elseif (function_exists('the_ratings')) {
            return 'WP-PostRatings';
        } elseif (function_exists('yasr_fs') || class_exists('\YasrDB') || defined('YASR_VERSION_NUM')) {
            return 'Yasr – Yet Another Stars Rating';
        } else {
            return false;
        }
    }

    //---------------------------------------------------
    // ADMIN NOTICES
    //---------------------------------------------------

    public function admin_notices()
    {
        ob_start();
        include_once plugin_dir_path(__FILE__) . 'templates/admin-notices.php';
        echo ob_get_clean();
    }

    //---------------------------------------------------
    // OPTIONS VERIFICATION AND SANITIZATION
    //---------------------------------------------------

    // return empty string rather than 0 - for options
    private function numeric_option($number)
    {
        if (absint($number)) {
            return absint($number);
        }

        return '';
    }

    // verfiy that all option were sent to the server (prevent cache issues)
    private function verify_options($deafult_options, $updated_options)
    {
        $diff            = array_diff_key($deafult_options, $updated_options);
        $errored_options = array();
        foreach ($diff as $key => $value) {
            $errored_options[] = $key;
        }

        return $errored_options;
    }

    // fallback in case that not all options are sent (cache)
    private function fix_options($deafult_options, $updated_options, $missing_keys)
    {
        foreach ($missing_keys as $missing_key) {
            $updated_options[$missing_key] = $deafult_options[$missing_key];
        }

        return $updated_options;
    }

    // sanitize options
    private function sanitize_options($key, $value)
    {
        // numeric options
        if (in_array($key, Rate_My_Post_Settings::$numeric_options)) {
            return absint($value);
        };
        // url options
        if (in_array($key, Rate_My_Post_Settings::$url_options)) {
            return esc_url(str_replace(' ', '', $value));
        }

        // string options
        if (in_array($key, Rate_My_Post_Settings::$string_options)) {
            return sanitize_text_field($value);
        }
        // exclude option
        if ($key === 'exclude') { // exclude is an array
            $new_array     = array();
            $input_numbers = explode(',', ($value));
            foreach ($input_numbers as $input_number) {
                $input_number = absint($input_number);
                if ($input_number) {
                    $new_array[] = $input_number;
                }
            }

            return $new_array;
        }
        // cpt options
        if ($key === 'cptRating' || $key === 'cptResult') { // cpt rating and cpt result are arrays
            $sanitized_array = array();
            $cpt_array       = explode(',', ($value));
            foreach ($cpt_array as $cpt) {
                $cpt = sanitize_text_field($cpt);
                if ($cpt) {
                    $sanitized_array[] = $cpt;
                }
            }

            return $sanitized_array;
        }

        return '';
    }

    // sanitize customization
    private function sanitize_customization($key, $value)
    {
        if (in_array($key, Rate_My_Post_Settings::$customization_numeric)) {
            return absint($value);
        } else {
            return sanitize_text_field($value);
        }
    }

    // sanitize security options
    private function sanitize_security($key, $value)
    {
        if (in_array($key, Rate_My_Post_Settings::$security_numeric)) {
            return absint($value);
        } else {
            return sanitize_text_field($value);
        }
    }

    //---------------------------------------------------
    // SECURITY METHODS
    //---------------------------------------------------

    // checks required capability for manipulating votes and feedback
    private function has_required_capability($post_id)
    {
        $security = get_option('rmp_security');
        if ($security['privileges'] == 1) { // author required
            // Admins and editors do actions on all posts
            if (current_user_can('edit_others_posts') || current_user_can('manage_options')) {
                return true;
            }
            // Authors can only do actions on their own posts
            if (current_user_can('publish_posts')) {
                $postAuthorId  = absint(get_post_field('post_author', $post_id));
                $currentUserId = absint(get_current_user_id());
                if ($postAuthorId === $currentUserId) {
                    return true;
                }
            }

            return false;
        } elseif ($security['privileges'] == 2) { // editor required
            return current_user_can('edit_others_posts');
        } else { // admin required
            return current_user_can('manage_options');
        }
    }

    // checks nonce for admin ajax
    private function has_valid_nonce($nonce)
    {
        return wp_verify_nonce($nonce, 'rmp_admin_save');
    }

    // check if current user is admin
    private function is_administrator()
    {
        return current_user_can('manage_options');
    }

    //---------------------------------------------------
    // POST TYPES
    //---------------------------------------------------

    // post type to which the ratings apply
    public static function define_post_types()
    {
        $args                  = array(
            'public' => true,
        );
        $registered_post_types = get_post_types($args);
        if (array_search('attachment', $registered_post_types)) {
            unset($registered_post_types['attachment']);
        }
        $applicable_post_types   = array_values($registered_post_types);
        $applicable_post_types[] = 'crw';

        return $applicable_post_types;
    }

    // returns a string of custom post types on the website
    private function custom_post_types()
    {
        $args = array(
            'public' => true,
        );

        $all_post_types = get_post_types($args);
        //remove default wordpress post types
        if (array_search('attachment', $all_post_types)) {
            unset($all_post_types['attachment']);
        }
        if (array_search('page', $all_post_types)) {
            unset($all_post_types['page']);
        }
        if (array_search('post', $all_post_types)) {
            unset($all_post_types['post']);
        }
        // array of custom post types
        $cpt = array_values($all_post_types);
        if ( ! empty($cpt)) {
            return substr(implode(', ', $cpt), 0, 100);
        }

        return false;
    }

    //---------------------------------------------------
    // FEEDBACK
    //---------------------------------------------------

    // returns an array of feedback for the post
    public static function feedbacks($post_id = false)
    {
        $post_id = $post_id ?: get_the_id();

        // get feedback before version 2.7.0
        $legacy_feedback = get_post_meta($post_id, 'rmp_feedback_val', true);
        // get feedback after version 2.7.0
        $feedback = get_post_meta($post_id, 'rmp_feedback_val_new', true);

        // restructure legacy feedback
        if ($legacy_feedback && ! is_array($legacy_feedback)) {
            $legacy_feedback = str_replace('<b>Feedback:</b> ', '', $legacy_feedback);
            $legacy_feedback = explode('<br />', $legacy_feedback);

            foreach ($legacy_feedback as $key => $value) {
                if ($value) {
                    $legacy_feedback[$key] = array(
                        'feedback' => $value,
                        'time'     => false,
                        'id'       => false,
                        'user'     => false,
                        'ratingID' => false,
                    );
                } else {
                    unset($legacy_feedback[$key]);
                }
            }
        }

        // merge legacy feedback and new feedback
        $feedback_array = array();
        // populate legacy feedback
        if (is_array($legacy_feedback) && ! empty($legacy_feedback)) {
            foreach ($legacy_feedback as $single_legacy_feedback) {
                $feedback_array[] = $single_legacy_feedback;
            }
        }
        // populate new feedback
        if (is_array($feedback) && ! empty($feedback)) {
            foreach ($feedback as $single_feedback) {
                $feedback_array[] = $single_feedback;
            }
        }

        if ( ! empty($feedback_array)) {
            return $feedback_array;
        }

        return false;
    }

    // returns user who left feedback
    private function feedback_user($user_id)
    {
        if ($user_id) {
            $user     = get_userdata($user_id);
            $username = $user->user_login;
            // allow hiding username in admin panel
            if (has_filter('rmp_rater_username')) {
                $username = apply_filters('rmp_rater_username', $username);
            }

            return $username;
        }

        return false;
    }

    //---------------------------------------------------
    // OTHER METHODS
    //---------------------------------------------------

    // check if uses caching plugin that requires ajax load
    private function has_incompatible_caching()
    {
        if (class_exists('HyperCache')) {
            return true;
        }
        if (class_exists('Cache_Enabler')) {
            return true;
        }

        return false;
    }

    public static function admin_header_design($header_text = '')
    {
        $header_text = ! empty($header_text) ? $header_text : 'FeedbackWP';

        $support_url = 'https://wordpress.org/support/plugin/rate-my-post/';
        $review_url  = 'https://wordpress.org/support/plugin/rate-my-post/reviews/?filter=5#new-post';
        $upgrade_url = 'https://feedbackwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=feedbackwp-header-top';

        if (class_exists('Rate_My_Post_Pro')) {
            $support_url = 'https://feedbackwp.com/support/';
        }

        ?>
        <div id="rmp-header" class="rmp-header">
            <div class="rmp-header-left"><h3 class="rmp-heading"><?php echo $header_text; ?></h3></div>
            <div class="rmp-header-right">
                <a href="<?php echo $review_url ?>" target="_blank" class="rmp-header-action review">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2.5" d="m12 2l3.104 6.728l7.358.873l-5.44 5.03l1.444 7.268L12 18.28L5.534 21.9l1.444-7.268L1.538 9.6l7.359-.873L12 2Z"></path>
                    </svg>
                    <?php esc_html_e('Review', 'rate-my-post'); ?>
                </a> <a href="https://feedbackwp.com/docs" target="_blank" class="rmp-header-action translate">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 56 56">
                        <path fill="currentColor" d="M15.555 53.125h24.89c4.852 0 7.266-2.461 7.266-7.336V24.508c0-3.024-.328-4.336-2.203-6.258L32.57 5.102c-1.78-1.829-3.234-2.227-5.882-2.227H15.555c-4.828 0-7.266 2.484-7.266 7.36v35.554c0 4.898 2.438 7.336 7.266 7.336m.187-3.773c-2.414 0-3.68-1.29-3.68-3.633V10.305c0-2.32 1.266-3.657 3.704-3.657h10.406v13.618c0 2.953 1.5 4.406 4.406 4.406h13.36v21.047c0 2.343-1.243 3.633-3.68 3.633ZM31 21.132c-.914 0-1.29-.374-1.29-1.312V7.375l13.5 13.758Z"/>
                    </svg>
                    <?php esc_html_e('Documentation', 'rate-my-post'); ?>
                </a>

                <a href="<?php echo $support_url ?>" target="_blank" class="rmp-header-action feedback">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16">
                        <path fill="currentColor" d="M1 2.75C1 1.784 1.784 1 2.75 1h10.5c.966 0 1.75.784 1.75 1.75v7.5A1.75 1.75 0 0 1 13.25 12H9.06l-2.573 2.573A1.458 1.458 0 0 1 4 13.543V12H2.75A1.75 1.75 0 0 1 1 10.25Zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h2a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h4.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25Z"></path>
                    </svg>
                    <?php esc_html_e('Get Help', 'rate-my-post'); ?>
                </a>
                <?php if ( ! class_exists('Rate_My_Post_Pro')) { ?>
                    <a href="<?php echo $upgrade_url; ?>" target="_blank" class="button-primary plugin-upgrade"> Get
                        FeedbackWP Premium </a>
                <?php } ?>
            </div>
        </div>
        <?php // necessary so all admin notices will be under the top header above
        ?>
        <h1 style="display:none">FeedbackWP</h1>
        <?php
    }

    public function posts_column_rating()
    {
        foreach (Rate_My_Post_Common::enabled_post_types() as $post_type) {

            add_filter("manage_{$post_type}_posts_columns", function ($columns) {
                $columns['rmp-average-rating'] = __('Average Rating', 'rate-my-post');

                return $columns;
            }, 1);

            add_action("manage_{$post_type}_posts_custom_column", function ($column_name, $post_id) {
                if ($column_name == 'rmp-average-rating') {
                    echo get_post_meta($post_id, 'rmp_avg_rating', true);
                }
            }, 10, 2);

            static $cache = null;

            if (is_null($cache)) {

                $cache = true;

                add_action('admin_head', function () {

                    $screen = get_current_screen();

                    if ('edit' === $screen->base) {
                        ?>
                        <style>
                            .fixed .column-rmp-average-rating {
                                width: 10%;
                            }
                        </style>
                        <?php
                    }
                });
            }
        }
    }
}
