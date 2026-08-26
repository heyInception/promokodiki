<?php

/**
 * Admin template
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      3.0.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/admin/partials
 */

?>

<?php
if ( ! defined('WPINC')) {
    die;
}

if (class_exists('Rate_My_Post_Pro')) {
    do_action('rmp_license_section');

    return;
}
?>

<div class="rmp-tab-content js-rmp-tab-content js-rmp-tab-content--6">
    <h2 class="rmp-tab-content__title">
        <?php echo(esc_html__('FeedbackWP Premium', 'rate-my-post')); ?>
    </h2>
    <div class="rmp-pro">

        <p class="rmp-pro__subtitle">
            <?php echo(esc_html__('FeedbackWP Premium includes the following features:', 'rate-my-post')); ?>
        </p>
        <div class="rmp-pro__features">
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Add rating widget to custom post types.', 'rate-my-post')); ?>
            </p>
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Support for different rating icons other than Stars.', 'rate-my-post')); ?>
            </p>
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Add structured data for rich snippets with not only the required fields but also the optional fields.', 'rate-my-post')); ?>
            </p>
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Create custom rating widgets which work independently of posts. ', 'rate-my-post')); ?>
            </p>
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Add ratings to existing posts, pages and custom post types without any votes and ratings.', 'rate-my-post')); ?>
            </p>
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Each custom widget is customizable, including structured data for rich snippets.', 'rate-my-post')); ?>
            </p>
            <!-- <p class="rmp-pro__feature">
        <?php echo(esc_html__('Shortcode generator.', 'rate-my-post')); ?>
      </p> -->
            <!-- <p class="rmp-pro__feature">
        <?php echo(esc_html__('Read-only ratings with structured data for reviews (review rating).', 'rate-my-post')); ?>
      </p> -->
            <p class="rmp-pro__feature">
                <?php echo(esc_html__('Email support', 'rate-my-post')); ?>
            </p>
        </div>
        <div class="rmp-pro__link">
            <a class="button-primary" href="https://feedbackwp.com/" target="_blank">
                <?php echo(esc_html__('Get FeedbackWP Premium', 'rate-my-post')); ?>
            </a>
        </div>

    </div>
</div>
