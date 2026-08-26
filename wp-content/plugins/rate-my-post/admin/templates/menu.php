<?php

/**
 * Admin template
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      2.0.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/admin/partials
 */

?>

<?php
if ( ! defined('WPINC')) {
    die;
}

$pro = esc_html__('Premium Version', 'rate-my-post');

$support_url = 'https://wordpress.org/support/plugin/rate-my-post/';
$review_url  = 'https://wordpress.org/support/plugin/rate-my-post/reviews/?filter=5#new-post';
$upgrade_url = 'https://feedbackwp.com/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=feedbackwp-header-top';

if (class_exists('Rate_My_Post_Pro')) {
    $pro         = esc_html__('License', 'rate-my-post');
    $support_url = 'https://feedbackwp.com/support/';
}

?>
<div class="wrap rmp">

    <div id="poststuff">

        <div id="post-body" class="metabox-holder columns-2">

            <div id="post-body-content">

                <div class="rmp-menu js-rmp-menu">

                    <h2 class="nav-tab-wrapper">
                        <span class="nav-tab nav-tab-active js-rmp-tab" data-tab="1">
                          <?php echo(esc_html__('Settings', 'rate-my-post')); ?>
                        </span> <span class="nav-tab js-rmp-tab" data-tab="2">
                          <?php echo(esc_html__('Customize', 'rate-my-post')); ?>
                        </span> <span class="nav-tab js-rmp-tab" data-tab="3">
                          <?php echo(esc_html__('Security', 'rate-my-post')); ?>
                        </span> <span class="nav-tab js-rmp-tab" data-tab="4">
                          <?php echo(esc_html__('Tools', 'rate-my-post')); ?>
                        </span> <span class="nav-tab js-rmp-tab" data-tab="6">
                          <?php echo $pro; ?>
                        </span>
                    </h2>

                    <!-- alerts -->
                    <?php include_once plugin_dir_path(__FILE__) . 'menu-alerts.php'; ?>
                    <!-- options -->
                    <?php include_once plugin_dir_path(__FILE__) . 'menu-options.php'; ?>
                    <!-- customization -->
                    <?php include_once plugin_dir_path(__FILE__) . 'menu-customize.php'; ?>
                    <!-- security options -->
                    <?php include_once plugin_dir_path(__FILE__) . 'menu-security.php'; ?>
                    <!-- migration tools -->
                    <?php include_once plugin_dir_path(__FILE__) . 'menu-migration.php'; ?>

                    <!-- pro version -->
                    <?php include_once plugin_dir_path(__FILE__) . 'pro-version.php'; ?>

                </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">

                <div class="meta-box-sortables">
                    <?php Rate_My_Post_Admin::sidebar_content(); ?>
                </div>

            </div>

        </div>

        <br class="clear">
    </div>

</div>
