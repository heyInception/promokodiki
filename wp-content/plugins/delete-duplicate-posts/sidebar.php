<?php

namespace DeleteDuplicatePosts;

// this is an include only WP file
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

?>

<div id="sidebar-container">
	<?php

	global $ddp_fs;

	$my_current_user = wp_get_current_user();

	$ddp_deleted_duplicates = get_option( 'ddp_deleted_duplicates' );

	if ( $ddp_deleted_duplicates ) {
		?>
		<div class="sidebarrow">
			<h3>
				<?php
				printf(
					/* translators: %s: Number of deleted posts */
					esc_html__( '%s duplicates deleted!', 'delete-duplicate-posts' ),
					esc_html( number_format_i18n( $ddp_deleted_duplicates ) )
				);
				?>
			</h3>
		</div>
		<?php
	}
	?>

	<?php

	if ( ddp_fs()->is_not_paying() ) {
		// Adds a marketing sections with a link to in-dashboard pricing page.
		echo '<section><h1>' . esc_html__( 'Awesome Features', 'delete-duplicate-posts' ) . '</h1>';
		printf( '<a href="%s">%s</a>', esc_url( ddp_fs()->get_upgrade_url() ), esc_html__( 'Upgrade Now!', 'delete-duplicate-posts' ) );
		echo '</section>';
	}
	?>

	<div class="sidebarrow">
		<p class="warning">
			<?php esc_html_e( 'We recommend you always make a backup before running this tool.', 'delete-duplicate-posts' ); ?>
		</p>
	</div>

	<div class="sidebarrow ddpnewsletter">
		<h3><?php esc_html_e( 'Newsletter', 'delete-duplicate-posts' ); ?></h3>
		<p><?php esc_html_e( 'Sign up to our newsletter to receive the latest tips and updates directly to your inbox. Ensure your WordPress site remains efficient and duplicate-free!', 'delete-duplicate-posts' ); ?></p>
		<form class="ddp-newsletter-form" action="https://assets.mailerlite.com/jsonp/16490/forms/106309157552916248/subscribe" method="post" target="_blank">
			<p>
				<label class="screen-reader-text" for="ddp-nl-name"><?php esc_html_e( 'Name', 'delete-duplicate-posts' ); ?></label>
				<input type="text" id="ddp-nl-name" name="fields[name]" placeholder="<?php esc_attr_e( 'Name', 'delete-duplicate-posts' ); ?>" autocomplete="given-name" value="<?php echo esc_attr( $my_current_user->display_name ); ?>">
			</p>
			<p>
				<label class="screen-reader-text" for="ddp-nl-email"><?php esc_html_e( 'Email', 'delete-duplicate-posts' ); ?></label>
				<input type="email" id="ddp-nl-email" name="fields[email]" placeholder="<?php esc_attr_e( 'Email', 'delete-duplicate-posts' ); ?>" autocomplete="email" value="<?php echo esc_attr( $my_current_user->user_email ); ?>" required="required">
			</p>
			<input type="hidden" name="fields[signupsource]" value="PluginInstall">
			<input type="hidden" name="ml-submit" value="1">
			<input type="hidden" name="anticsrf" value="true">
			<p class="ddp-newsletter-consent">
				<label for="ddp-nl-consent">
					<input type="checkbox" id="ddp-nl-consent" name="ddp_nl_consent" value="1" required="required">
					<?php esc_html_e( 'Yes, sign me up for the newsletter. I agree to receive emails and can unsubscribe anytime.', 'delete-duplicate-posts' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Subscribe', 'delete-duplicate-posts' ); ?></button>
			</p>
		</form>
		<p class="ppolicy">
			<?php
			printf(
				/* translators: %s: Privacy Policy link, linked text is "Privacy Policy". */
				esc_html__( 'You can unsubscribe anytime. For more details, review our %s.', 'delete-duplicate-posts' ),
				'<a href="https://cleverplugins.com/privacy-policy/" target="_blank" class="privacy-policy" rel="noopener">' . esc_html__( 'Privacy Policy', 'delete-duplicate-posts' ) . '</a>'
			);
			?>
		</p>
	</div><!-- .sidebarrow -->


	<div class="sidebarrow">
		<h3><?php esc_html_e( 'Our other plugins', 'delete-duplicate-posts' ); ?></h3>
		<a href="https://wpsecurityninja.com" target="_blank" style="float: right;" rel="noopener"><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/security-ninja-logo.png' ); ?>" alt="<?php esc_attr_e( 'Visit wpsecurityninja.com', 'delete-duplicate-posts' ); ?>" class="logo"></a>

		<strong><?php esc_html_e( 'WordPress Security made easy', 'delete-duplicate-posts' ); ?></strong>
		<p><?php esc_html_e( 'Complete WordPress site protection with firewall, malware scanner, scheduled scans, security tests and much more - all you need to keep your website secure. Free trial.', 'delete-duplicate-posts' ); ?></p>

		<p><a href="https://wpsecurityninja.com/" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Visit wpsecurityninja.com', 'delete-duplicate-posts' ); ?></a></p>
		<br />
	</div><!-- .sidebarrow -->
	<div class="sidebarrow">
		<h3><?php esc_html_e( 'Need help?', 'delete-duplicate-posts' ); ?></h3>
		<p><?php esc_html_e( 'Email support only for pro customers.', 'delete-duplicate-posts' ); ?></p>
		<p><?php esc_html_e( 'Free users:', 'delete-duplicate-posts' ); ?> <a href="https://wordpress.org/support/plugin/delete-duplicate-posts/" target="_blank" rel="noopener"><?php esc_html_e( 'Support Forum on WordPress.org', 'delete-duplicate-posts' ); ?></a></p>
		<form method="post" id="ddp_reactivate">
			<?php wp_nonce_field( 'ddp_reactivate_nonce' ); ?>
			<input class="button button-secondary button-small" type="submit" name="ddp_reactivate" value="<?php esc_html_e( 'Recreate Databases', 'delete-duplicate-posts' ); ?>" />
		</form>
	</div><!-- .sidebarrow -->
</div>