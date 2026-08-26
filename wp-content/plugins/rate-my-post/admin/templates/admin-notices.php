<?php

/**
 * Template for admin notices
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      3.3.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/admin/partials
 */
?>

<?php
  if ( ! defined( 'WPINC' ) ) {
  	die;
  }
  $admin_notices = get_option( 'rmp_admin_notices' );
?>

<?php if( !$admin_notices['ls'] && class_exists( 'LiteSpeed_Cache_API' ) && (3 < 2) ): ?>
  <div class="js-rmp-admin-notice rmp-admin-notice notice notice-warning is-dismissible" data-admin-notice-key="ls">
    <h2>FeedbackWP <?php echo esc_html__('Notice', 'rate-my-post'); ?>: LiteSpeed Cache</h2>
    <p><?php echo sprintf( esc_html__( 'We detected %s plugin installed on your website. %s works best if %s is configured as shown %shere%s.', 'rate-my-post' ), 'LiteSpeed Cache', 'FeedbackWP', 'LiteSpeed cache', '<a href="https://feedbackwp.com/wp-content/uploads/2024/01/LiteSpeed-Settings-for-RMP.jpg" target="_blank">', '</a>' ); ?></p>
  </div>
<?php endif; ?>

<?php if( !$admin_notices['ampforwp'] && function_exists( 'ampforwp_is_amp_endpoint' ) ): ?>
  <div class="js-rmp-admin-notice rmp-admin-notice notice notice-warning is-dismissible" data-admin-notice-key="ampforwp">
    <h2>FeedbackWP <?php echo esc_html__('Notice', 'rate-my-post'); ?>: AMP for WP</h2>
    <p><?php echo sprintf( esc_html__( 'We detected %s plugin installed on your website. If you want to use %s on %sAMP pages%s, additional configuration is required. Learn more %shere%s.', 'rate-my-post' ), 'AMP for WP', 'FeedbackWP', '<b>', '</b>', '<a href="https://feedbackwp.com/docs/#AMP_Compatibility_BETA" target="_blank">', '</a>' ); ?></p>
  </div>
<?php endif; ?>

<?php if( ! class_exists( 'Rate_My_Post_Pro' ) && !$admin_notices['pro'] && ( ( time() - $admin_notices['installed'] ) > 864000 ) ): ?>
  <div class="js-rmp-admin-notice rmp-admin-notice notice notice-info is-dismissible" data-admin-notice-key="pro">
    <h2><?php echo sprintf( esc_html__( '%s: Are you ready to boost your SEO with %s?', 'rate-my-post' ), 'FeedbackWP', 'FeedbackWP Premium' ); ?></h2>
    <p><?php echo sprintf( esc_html__( 'You\'ve been using %1$s for a while now. Maybe it\'s time to check out the PREMIUM version which comes some neat features such as %2$scustom post type support%3$s, %2$sdifferent icon types%3$s, %2$sadvanced structured data%3$s and %2$custom rating widgets%3$s. If not, keep enjoying the free version :)', 'rate-my-post' ), 'FeedbackWP', '<b>', '</b>' ); ?></p>
      <a target="_blank" href="https://feedbackwp.com/" class="rmp-btn rmp-btn--info">
        <?php echo esc_html__('See FeedbackWP Premium', 'rate-my-post' ); ?>
      </a>
  </div>
<?php endif; ?>
