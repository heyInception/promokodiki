<?php

/**
 * Plugin Name:        FeedbackWP - Rate My Post - WP Rating System
 * Plugin URI:        https://feedbackwp.com
 * Description:       Allows you to easily add rating functionality to your WordPress website.
 * Version:           4.4.4
 * Author:            FeedbackWP
 * Author URI:        https://feedbackwp.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rate-my-post
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined('WPINC')) die;

// PRO version is installed
if (class_exists('Rate_My_Post')) {
    add_action('admin_notices', 'rmp_disable_notice');

    return;
}

// Show admin notice if PRO version is detected
function rmp_disable_notice()
{
    ?>
    <div class="rmp-admin-notice notice notice-error">
        <h2>FeedbackWP <?php echo esc_html__('Notice: Plugin Deactivated', 'rate-my-post'); ?></h2>
        <p><?php echo esc_html__('Plugin has been deactivated because the PRO version was detected.', 'rate-my-post'); ?></p>
    </div>
    <?php
}

// Plugin version
define('RATE_MY_POST_VERSION', '4.4.4');
define('RATE_MY_POST_SYSTEM_FILE_PATH', __FILE__);

// Plugin activation
function activate_rate_my_post()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-rate-my-post-activator.php';
    Rate_My_Post_Activator::activate();
}

// Plugin deactivation
function deactivate_rate_my_post()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-rate-my-post-deactivator.php';
    Rate_My_Post_Deactivator::deactivate();
}

// Plugin upgrade
function upgrade_rate_my_post()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-rate-my-post-upgrader.php';
    Rate_My_Post_Upgrader::upgrade();
}

register_activation_hook(__FILE__, 'activate_rate_my_post');
register_deactivation_hook(__FILE__, 'deactivate_rate_my_post');
add_action('plugins_loaded', 'upgrade_rate_my_post');

//developer functions
require plugin_dir_path(__FILE__) . 'includes/dev-functions.php';
require plugin_dir_path(__FILE__) . 'includes/Shogun.php';

// The core plugin class
require plugin_dir_path(__FILE__) . 'includes/class-rate-my-post.php';

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}


// Execute the plugin
function run_rate_my_post()
{
    $plugin = new Rate_My_Post();
    $plugin->run();
}

run_rate_my_post();
