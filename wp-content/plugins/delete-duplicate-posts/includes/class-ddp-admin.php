<?php

/**
 * Admin UI and settings page.
 *
 * @package DeleteDuplicatePosts
 */
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class DDP_Admin {
    /**
     * Returns a Pro badge label for settings teasers.
     *
     * @return string
     */
    public static function pro_badge() {
        return '<span class="ddp-pro-badge">' . esc_html__( 'Pro', 'delete-duplicate-posts' ) . '</span>';
    }

    /**
     * Freemius checkout URL for upgrades.
     *
     * @param string $billing Billing cycle (annually or lifetime).
     * @return string
     */
    public static function upgrade_url( $billing = 'annually' ) {
        $url = 'https://checkout.freemius.com/mode/dialog/plugin/925/plan/9473/licenses/1/?billing_cycle=' . rawurlencode( $billing );
        $user = wp_get_current_user();
        if ( $user && $user->user_email ) {
            $url = add_query_arg( 'user_email', $user->user_email, $url );
        }
        return $url;
    }

    /**
     * Short upgrade link for Pro-only setting rows.
     *
     * @return string
     */
    public static function pro_upgrade_link() {
        return sprintf( '<a href="%s" class="ddp-pro-upgrade" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( self::upgrade_url() ), esc_html__( 'Upgrade to Pro', 'delete-duplicate-posts' ) );
    }

    /**
     * Renders a locked Pro option as a deliberate upgrade promo.
     *
     * Instead of a disabled form control (which looks broken and is skipped by
     * keyboard/screen-reader users), the whole row is a link to the upgrade page.
     *
     * @param string $label       The option label (plain text).
     * @param string $description  Optional supporting text (plain text).
     * @param string $note         Optional extra note appended to the label, e.g. a warning (plain text).
     * @return string
     */
    public static function pro_locked_row( $label, $description = '', $note = '' ) {
        $html = '<a class="ddp-pro-lock" href="' . esc_url( self::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">';
        $html .= '<span class="ddp-pro-lock__icon dashicons dashicons-lock" aria-hidden="true"></span>';
        $html .= '<span class="ddp-pro-lock__text">';
        $html .= '<span class="ddp-pro-lock__label">' . esc_html( $label ) . '</span>';
        $html .= self::pro_badge();
        if ( '' !== $note ) {
            $html .= '<span class="ddp-pro-lock__note">' . esc_html( $note ) . '</span>';
        }
        if ( '' !== $description ) {
            $html .= '<span class="ddp-pro-lock__desc">' . esc_html( $description ) . '</span>';
        }
        $html .= '</span>';
        $html .= '<span class="ddp-pro-lock__cta">' . esc_html__( 'Upgrade to Pro', 'delete-duplicate-posts' ) . '</span>';
        $html .= '</a>';
        return $html;
    }

    /**
     * Enqueues scripts and styles
     *
     * @author   Lars Koudal
     * @since    v0.0.1
     * @version  v1.0.0  Monday, January 11th, 2021.
     * @access   public static
     * @return   void
     */
    public static function admin_enqueue_scripts() {
        $screen = get_current_screen();
        if ( is_object( $screen ) && 'tools_page_delete-duplicate-posts' === $screen->id ) {
            $pluginver = DDP_Settings::get_plugin_version();
            wp_enqueue_script( 'jquery' );
            wp_enqueue_style(
                'delete-duplicate-posts',
                plugins_url( '/css/delete-duplicate-posts.css', DDP_PLUGIN_FILE ),
                array(),
                $pluginver
            );
            wp_enqueue_script(
                'dataTables',
                // Unique handle for your script
                plugin_dir_url( DDP_PLUGIN_FILE ) . 'js/DataTables/datatables.js',
                // Path to your script file
                array('jquery'),
                // Dependencies, if any. This script depends on jQuery
                $pluginver,
                array(
                    'in_footer' => true,
                )
            );
            wp_enqueue_style(
                'dataTables',
                plugins_url( '/js/DataTables/datatables.css', DDP_PLUGIN_FILE ),
                array(),
                $pluginver
            );
            wp_register_script(
                'delete-duplicate-posts',
                plugins_url( '/js/delete-duplicate-posts.js', DDP_PLUGIN_FILE ),
                array('jquery', 'dataTables'),
                $pluginver,
                true
            );
            $js_vars = array(
                'nonce'                => wp_create_nonce( 'cp_ddp_return_duplicates' ),
                'loglines_nonce'       => wp_create_nonce( 'cp_ddp_return_loglines' ),
                'deletedupes_nonce'    => wp_create_nonce( 'cp_ddp_delete_loglines' ),
                'dismiss_notice_nonce' => wp_create_nonce( 'ddp_dismiss_notice' ),
                'text_areyousure'      => __( 'Are you sure you want to delete duplicates? There is no undo feature.', 'delete-duplicate-posts' ),
                'text_selectsomething' => __( 'You have to select which duplicates to delete. Tip: You can click the top or bottom checkbox to select all.', 'delete-duplicate-posts' ),
                'fromUrlTitle'         => __( 'From URL', 'delete-duplicate-posts' ),
                'targetUrlTitle'       => __( 'Target URL', 'delete-duplicate-posts' ),
                'refreshingText'       => __( 'Refreshing...', 'delete-duplicate-posts' ),
                'refreshText'          => __( 'Refresh', 'delete-duplicate-posts' ),
                'errorDetailsText'     => __( 'Error details: ', 'delete-duplicate-posts' ),
                'redirectsErrorText'   => __( 'Redirects DataTables error occurred. ', 'delete-duplicate-posts' ),
                'processingMessage'    => __( 'Looking for duplicates', 'delete-duplicate-posts' ),
                'requestTimeText'      => __( 'Request: ', 'delete-duplicate-posts' ),
                'failedToLoadDataText' => __( 'Failed to load data. ', 'delete-duplicate-posts' ),
                'duplicateTitle'       => __( 'Duplicate', 'delete-duplicate-posts' ),
                'originalTitle'        => __( 'Original', 'delete-duplicate-posts' ),
                'selectRowAlert'       => __( 'Please select at least one row to delete.', 'delete-duplicate-posts' ),
                'serverResponseText'   => __( 'Response from the server: ', 'delete-duplicate-posts' ),
                'errorOccurredText'    => __( 'An error occurred: ', 'delete-duplicate-posts' ),
                'deleteSelectedText'   => __( 'Delete Selected', 'delete-duplicate-posts' ),
                'selectVisibleText'    => __( 'Select Visible', 'delete-duplicate-posts' ),
                'selectNoneText'       => __( 'Select None', 'delete-duplicate-posts' ),
                'dataTablesErrorText'  => __( 'DataTables error occurred. ', 'delete-duplicate-posts' ),
                'unknownErrorText'     => __( 'Unknown error occurred', 'delete-duplicate-posts' ),
            );
            wp_localize_script( 'delete-duplicate-posts', 'cp_ddp', $js_vars );
            wp_enqueue_script( 'delete-duplicate-posts' );
        }
    }

    /**
     * Returns the current user's dismissed-notice map.
     *
     * @return array Map of notice key => dismissal Unix timestamp.
     */
    private static function get_dismissed_notices() {
        $dismissed = get_user_meta( get_current_user_id(), 'ddp_dismissed_notices', true );
        return ( is_array( $dismissed ) ? $dismissed : array() );
    }

    /**
     * Checks whether an admin notice has been dismissed by the current user.
     *
     * @param string $key         Notice identifier.
     * @param int    $snooze_days Days to keep it hidden after dismissal. 0 means hide permanently.
     * @return bool True when the notice should stay hidden.
     */
    public static function is_notice_dismissed( $key, $snooze_days = 0 ) {
        $dismissed = self::get_dismissed_notices();
        if ( !isset( $dismissed[$key] ) ) {
            return false;
        }
        if ( 0 === $snooze_days ) {
            return true;
        }
        return time() - (int) $dismissed[$key] < $snooze_days * DAY_IN_SECONDS;
    }

    /**
     * Stores the dismissal timestamp for a notice against the current user.
     *
     * @param string $key Notice identifier.
     * @return void
     */
    private static function set_notice_dismissed( $key ) {
        $dismissed = self::get_dismissed_notices();
        $dismissed[$key] = time();
        update_user_meta( get_current_user_id(), 'ddp_dismissed_notices', $dismissed );
    }

    /**
     * AJAX handler that persists dismissal of a plugin admin notice.
     *
     * @return void
     */
    public static function dismiss_notice_ajax() {
        check_ajax_referer( 'ddp_dismiss_notice' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error();
        }
        $notice = ( isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '' );
        $allowed = array('welcome', 'leavereview');
        if ( !in_array( $notice, $allowed, true ) ) {
            wp_send_json_error();
        }
        self::set_notice_dismissed( $notice );
        wp_send_json_success();
    }

    /**
     * Adds link to menu under Tools
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @return  void
     */
    public static function admin_menu_link() {
        // only for admins
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        add_management_page(
            'Delete Duplicate Posts',
            'Delete Duplicate Posts',
            'manage_options',
            'delete-duplicate-posts',
            array(__CLASS__, 'admin_options_page'),
            41
        );
        add_filter(
            'plugin_action_links_' . plugin_basename( DDP_PLUGIN_FILE ),
            array(__CLASS__, 'filter_plugin_actions'),
            10,
            2
        );
    }

    /**
     * filter_plugin_actions.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @param   mixed   $links
     * @param   mixed   $file
     * @return  mixed
     */
    public static function filter_plugin_actions( $links, $file ) {
        $settings_link = '<a href="tools.php?page=delete-duplicate-posts">' . __( 'Settings', 'delete-duplicate-posts' ) . '</a>';
        array_unshift( $links, $settings_link );
        // before other links
        return $links;
    }

    /**
     * Adds help content to plugin page
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @access  public static
     * @return  void
     */
    public static function set_custom_help_content() {
        $screen = get_current_screen();
        if ( 'tools_page_delete-duplicate-posts' === $screen->id ) {
            $screen->add_help_tab( array(
                'id'      => 'ddp_help',
                'title'   => __( 'Usage and FAQ', 'delete-duplicate-posts' ),
                'content' => '<h4>' . __( 'What does this plugin do?', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'Helps you clean duplicate posts from your blog. The plugin checks for blogposts on your blog with the same title.', 'delete-duplicate-posts' ) . '</p><p>' . __( "It can run automatically via WordPress's own internal CRON-system, or you can run it automatically.", 'delete-duplicate-posts' ) . '</p><p>' . __( 'It also has a nice feature that can send you an e-mail when Delete Duplicate Posts finds and deletes something (if you have turned on the CRON feature).', 'delete-duplicate-posts' ) . '</p><h4>' . __( 'Help! Something was deleted that was not supposed to be deleted!', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'I am sorry for that, I can only recommend you restore the database you took just before you ran this plugin.', 'delete-duplicate-posts' ) . '</p><p>' . __( 'If you run this plugin, manually or automatically, it is at your OWN risk!', 'delete-duplicate-posts' ) . '</p><p>' . __( 'We have done our best to avoid deleting something that should not be deleted, but if it happens, there is nothing we can do to help you.', 'delete-duplicate-posts' ) . "</p><p><a href='https://cleverplugins.com' target='_blank'>cleverplugins.com</a>.</p>",
            ) );
        }
    }

    /**
     * admin_options_page.
     *
     * @author  Lars Koudal
     * @since   v0.0.1
     * @version v1.0.0  Thursday, June 9th, 2022.
     * @version v1.0.1  Thursday, June 9th, 2022.
     * @access  public static
     * @return  void
     */
    public static function admin_options_page() {
        global $ddp_fs, $wpdb;
        // Hard gate: this screen saves settings, clears the log, recreates tables
        // and can trigger deletion, so require full admin capability before any
        // POST handling below runs.
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'delete-duplicate-posts' ) );
        }
        // SAVING OPTIONS
        if ( isset( $_POST['delete_duplicate_posts_save'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp-update-options' ) ) {
                die( esc_html( __( 'Whoops! There was a problem with the data you posted. Please go back and try again.', 'delete-duplicate-posts' ) ) );
            }
            $options = DDP_Settings::get_options();
            $posttypes = array();
            if ( isset( $_POST['ddp_pts'] ) && is_array( $_POST['ddp_pts'] ) ) {
                $option_array = wp_unslash( $_POST['ddp_pts'] );
                foreach ( $option_array as $post_type ) {
                    $posttypes[] = sanitize_text_field( $post_type );
                }
            }
            if ( isset( $_POST['ddp_enabled'] ) ) {
                $options['ddp_enabled'] = 'on' === sanitize_text_field( wp_unslash( $_POST['ddp_enabled'] ) );
            } else {
                $options['ddp_enabled'] = false;
            }
            $options['ddp_statusmail'] = isset( $_POST['ddp_statusmail'] ) && 'on' === sanitize_text_field( wp_unslash( $_POST['ddp_statusmail'] ) );
            $options['ddp_debug'] = isset( $_POST['ddp_debug'] ) && 'on' === sanitize_text_field( wp_unslash( $_POST['ddp_debug'] ) );
            if ( isset( $_POST['ddp_statusmail_recipient'] ) ) {
                $recipients = DDP_Settings::parse_email_recipients( wp_unslash( $_POST['ddp_statusmail_recipient'] ) );
                $options['ddp_statusmail_recipient'] = implode( ', ', $recipients );
            }
            if ( isset( $_POST['ddp_schedule'] ) ) {
                $options['ddp_schedule'] = sanitize_text_field( wp_unslash( $_POST['ddp_schedule'] ) );
            }
            if ( isset( $_POST['ddp_keep'] ) ) {
                $options['ddp_keep'] = sanitize_text_field( wp_unslash( $_POST['ddp_keep'] ) );
            }
            $options['ddp_method'] = 'titlecompare';
            if ( isset( $_POST['ddp_resultslimit'] ) ) {
                $options['ddp_resultslimit'] = sanitize_text_field( wp_unslash( $_POST['ddp_resultslimit'] ) );
            }
            $options['ddp_redirects'] = false;
            $options['ddp_pts'] = $posttypes;
            $interval = ( isset( $options['ddp_schedule'] ) ? $options['ddp_schedule'] : 'hourly' );
            if ( !$interval ) {
                $interval = 'hourly';
            }
            $schedules = wp_get_schedules();
            if ( !isset( $schedules[$interval] ) ) {
                $interval = 'hourly';
            }
            $previous_interval = ( isset( $options['last_interval'] ) ? $options['last_interval'] : '' );
            if ( !empty( $options['ddp_enabled'] ) ) {
                $nextscheduled = wp_next_scheduled( 'ddp_cron' );
                $interval_changed = $previous_interval !== $interval;
                if ( !$nextscheduled || $interval_changed ) {
                    wp_clear_scheduled_hook( 'ddp_cron' );
                    wp_schedule_event( time(), $interval, 'ddp_cron' );
                }
                $options['last_interval'] = $interval;
            } else {
                wp_clear_scheduled_hook( 'ddp_cron' );
            }
            DDP_Settings::save_options( $options );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( __( 'Settings saved.', 'delete-duplicate-posts' ) ) . '</p></div>';
        }
        // CLEARING THE LOG
        if ( isset( $_POST['ddp_clearlog'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp_clearlog_nonce' ) ) {
                die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
            }
            $table_name_log = $wpdb->prefix . 'ddp_log';
            $wpdb->query( "TRUNCATE {$table_name_log};" );
            //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            echo '<div class="updated"><p>' . esc_html( __( 'The log was cleared.', 'delete-duplicate-posts' ) ) . '</p></div>';
        }
        // REACTIVATE THE DATABASE
        if ( isset( $_POST['ddp_reactivate'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp_reactivate_nonce' ) ) {
                die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
            }
            DDP_Install::install( false );
            DDP_Logger::log( 'Reinstalled databases' );
        }
        $options = DDP_Settings::get_options();
        $is_pro = false;
        $show_redirects = false;
        $css_classes = ' free';
        $display_ads = true;
        ?>

		<div class="wrap fs-section <?php 
        echo esc_attr( $css_classes );
        ?>">
			<h1>Delete Duplicate Posts <span>v. <?php 
        echo esc_html( DDP_Settings::get_plugin_version() );
        ?></span></h1>
			<?php 
        $totaldeleted = get_option( 'ddp_deleted_duplicates' );
        if ( isset( $_GET['welcome-message'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['welcome-message'] ) ) && !self::is_notice_dismissed( 'welcome' ) ) {
            ?>
				<div class="notice notice-success is-dismissible ddp-dismissible-notice ddp-welcome-message" data-ddp-dismiss="welcome">
					<h2>Delete Duplicate Posts</h2>
					<p><?php 
            esc_html_e( 'Thanks for installing. Scan your site for duplicate posts below to get started.', 'delete-duplicate-posts' );
            ?></p>
				</div>
				<?php 
        }
        ?>

			<h2 class="nav-tab-wrapper">
				<a href="#duplicates-tab" class="nav-tab fs-tab nav-tab-active home"><?php 
        esc_html_e( 'Duplicates', 'delete-duplicate-posts' );
        ?></a>
				<a href="#log-tab" class="nav-tab"><?php 
        esc_html_e( 'Log', 'delete-duplicate-posts' );
        ?></a>
				<a href="#settings-tab" class="nav-tab"><?php 
        esc_html_e( 'Settings', 'delete-duplicate-posts' );
        ?></a>
				<a href="#redirects-tab" class="nav-tab<?php 
        echo ( $show_redirects ? '' : ' pro' );
        ?>"><?php 
        esc_html_e( 'Redirects', 'delete-duplicate-posts' );
        ?></a>
			</h2>

			<div class="ddp_content_wrapper">
				<div class="ddp_content_cell">
					<div id="delete-duplicate-posts-tabs">
						<div id="duplicates-tab" class="tab-content">
							<div id="ddp-dashboard">
								<?php 
        if ( $options['ddp_enabled'] ) {
            $interval = $options['ddp_schedule'];
            if ( !$interval ) {
                $interval = 'hourly';
            }
            $nextscheduled = wp_next_scheduled( 'ddp_cron' );
            if ( !$nextscheduled ) {
                // plugin active, but the cron needs to be activated also..
                $options['last_interval'] = $interval;
                DDP_Settings::save_options( $options );
                wp_schedule_event( time(), $interval, 'ddp_cron' );
                //}
            }
        } else {
            wp_unschedule_hook( 'ddp_cron' );
        }
        $totaldeleted = get_option( 'ddp_deleted_duplicates' );
        ?>
								<div class="statusdiv">
									<div class="statusmessage"></div>
									<div class="errormessage"></div>
									<div class="dupelist">
										<div id="requestTime"></div>
										<table id="ddp_dupetable" class="wp-list-table widefat fixed striped table-view-list"></table>
									</div>
								</div>
								<?php 
        if ( false !== $totaldeleted && 0 < $totaldeleted && $display_ads && !self::is_notice_dismissed( 'leavereview', 180 ) ) {
            $totaldeleted = number_format_i18n( $totaldeleted );
            ?>
									<div id="cp-ddp-reviewlink" class="updated notice notice-success is-dismissible ddp-dismissible-notice" data-ddp-dismiss="leavereview">
										<h3>
											<?php 
            /* translators: %s: Total number of deleted duplicates since install. */
            printf( esc_html__( '%s duplicates deleted in total since install!', 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
            ?>
										</h3>
										<p>
											<?php 
            /* translators: %s: Total number of deleted duplicates since install. */
            printf( esc_html__( "Hey, I noticed this plugin has deleted %s duplicate posts in total since install - that's awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress? Just to help us spread the word and boost our motivation.", 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
            ?>
										</p>
										<p>
											<a href="https://wordpress.org/support/plugin/delete-duplicate-posts/reviews/#new-post" class="button-secondary button button-small" target="_blank" rel="noopener"><?php 
            esc_html_e( 'Ok, you deserve it', 'delete-duplicate-posts' );
            ?></a>
										</p>
									</div>
									<?php 
        }
        ?>



		<?php 
        $display_promotion = true;
        if ( $display_promotion ) {
            $target_url = self::upgrade_url( 'annually' );
            $lifetime_url = self::upgrade_url( 'lifetime' );
            ?>
	<div class="innerpromotion ddppro">
	<h3><?php 
            esc_html_e( 'Delete Duplicate Posts Pro', 'delete-duplicate-posts' );
            ?></h3>
	<p class="ddp-pro-intro"><?php 
            esc_html_e( 'Pro adds deeper cleanup controls for sites that need more than a basic title scan.', 'delete-duplicate-posts' );
            ?></p>
	<ul class="linklist">
		<li><strong><?php 
            esc_html_e( 'Delete permanently:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Remove duplicates from the database instead of moving them to trash.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( '301 redirects:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Send visitors from deleted URLs to the original post.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( 'Compare by meta:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Find duplicates by SKU, custom fields, or any post meta value.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( 'Any post status:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Include drafts, scheduled, private, and other statuses in the scan.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( 'Redirect management:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'View and manage redirects created when duplicates are removed.', 'delete-duplicate-posts' );
            ?></li>
	</ul>

			<a href="<?php 
            echo esc_url( $target_url );
            ?>" class="ddpprobutton button button-primary button-hero" target="_blank" rel="noopener noreferrer">
				<?php 
            /* translators: %s: Yearly price, e.g. $29.99/year. */
            printf( esc_html__( '%s/year', 'delete-duplicate-posts' ), '$29.99' );
            ?>
			</a>
	<p class="ddp-lifetime-offer">
			<?php 
            printf( 
                /* translators: 1: Lifetime price, 2: Link to lifetime checkout. */
                esc_html__( 'Prefer a one-time purchase? %1$s — %2$s', 'delete-duplicate-posts' ),
                '$59.99',
                '<a href="' . esc_url( $lifetime_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get lifetime access', 'delete-duplicate-posts' ) . '</a>'
             );
            ?>
	</p>
	<div class="moneybackguarantee">
		<p><strong><?php 
            esc_html_e( '30-day money-back guarantee', 'delete-duplicate-posts' );
            ?></strong></p>
		<p><?php 
            esc_html_e( 'If the plugin does not work as expected and we cannot resolve the issue, we will consider a full refund within 30 days of purchase.', 'delete-duplicate-posts' );
            ?></p>
	</div>
	</div><!-- .sidebarrow -->

			<?php 
        }
        ?>









	
							</div><!-- #dashboard -->
						</div>

						<div id="log-tab" class="tab-content" style="display: none;">
							<div id="log">
								<h3><?php 
        esc_html_e( 'The Log', 'delete-duplicate-posts' );
        ?></h3>
								<div class="spinner is-active"></div>
								<ul class="large-text" name="ddp_log" id="ddp_log"></ul>
							</div>
							<p>
							<form method="post" id="ddp_clearlog">
								<?php 
        wp_nonce_field( 'ddp_clearlog_nonce' );
        ?>
								<input class="button-secondary" type="submit" name="ddp_clearlog" value="<?php 
        esc_html_e( 'Reset log', 'delete-duplicate-posts' );
        ?>" />
							</form>
							</p>
						</div>

						<div id="settings-tab" class="tab-content" style="display: none;">
							<div id="ddp-configuration">
								<h3><?php 
        esc_html_e( 'Settings', 'delete-duplicate-posts' );
        ?></h3>
								<p>
									<?php 
        $nextscheduled = wp_next_scheduled( 'ddp_cron' );
        if ( $nextscheduled ) {
            ?>
								<div class="notice notice-info is-dismissible">
									<h3><span class="dashicons dashicons-saved"></span> <?php 
            esc_html_e( 'Automatically Deleting Duplicates', 'delete-duplicate-posts' );
            ?></h3>
										<?php 
            echo '<p class="cronstatus center">' . esc_html__( 'You have enabled automatic deletion, so I am running on automatic. I will take care of everything...', 'delete-duplicate-posts' ) . '</p>';
            echo '<p class="center">';
            printf( 
                // translators: Showing when the next check happens and what the current time is
                esc_html( __( 'Next automated check %1$s. Current time %2$s', 'delete-duplicate-posts' ) ),
                '<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $nextscheduled ) ) . '</strong>',
                '<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() ) ) . '</strong>'
             );
            echo '</p>';
            ?>
								</div>
										<?php 
        }
        ?>
							</p>
							<form method="post" id="delete_duplicate_posts_options">
								<?php 
        wp_nonce_field( 'ddp-update-options' );
        ?>
								<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
									<tr valign="top">
										<th><label for="ddp_pts"><?php 
        esc_html_e( 'Which post types?:', 'delete-duplicate-posts' );
        ?></label>
										</th>
										<td>
											<?php 
        $builtin = array('post', 'page', 'attachment');
        $args = array(
            'public'   => true,
            '_builtin' => false,
        );
        $output = 'names';
        $operator = 'and';
        $post_types = get_post_types( $args, $output, $operator );
        $post_types = array_merge( $builtin, $post_types );
        $checked_post_types = $options['ddp_pts'];
        if ( $post_types ) {
            ?>
												<ul class="radio">
													<?php 
            $step = 0;
            if ( !is_array( $checked_post_types ) ) {
                $checked_post_types = array();
            }
            foreach ( $post_types as $pt ) {
                $checked = array_search( $pt, $checked_post_types, true );
                ?>
														<li><input type="checkbox" name="ddp_pts[]" id="ddp_pt-<?php 
                echo esc_attr( $step );
                ?>" value="<?php 
                echo esc_html( $pt );
                ?>" 
														<?php 
                if ( false !== $checked ) {
                    echo ' checked';
                }
                ?>
																																																																												/>
															<label for="ddp_pt-<?php 
                echo esc_attr( $step );
                ?>"><?php 
                echo esc_html( $pt );
                ?></label>
															<?php 
                if ( 'attachment' === $pt ) {
                    echo '<small> ' . esc_html__( '(Media files are matched by title and removed with their files.)', 'delete-duplicate-posts' ) . '</small>';
                }
                // Count for each post type
                $postinfo = wp_count_posts( $pt );
                $othercount = 0;
                foreach ( $postinfo as $pi ) {
                    $othercount = $othercount + intval( $pi );
                }
                // translators: Total number of deleted duplicates
                echo '<small>' . sprintf( esc_html__( '(%s total found)', 'delete-duplicate-posts' ), esc_html( number_format_i18n( $othercount ) ) ) . '</small>';
                ?>
														</li>
														<?php 
                ++$step;
            }
            ?>
												</ul>
												<?php 
        }
        ?>
											<p class="description">
												<?php 
        esc_html_e( 'Choose which post types to scan for duplicates.', 'delete-duplicate-posts' );
        ?>
											</p>
										</td>
									</tr>

									<tr>
										<th><label for="ddp_pstati"><?php 
        esc_html_e( 'Post status', 'delete-duplicate-posts' );
        ?></label>
										</th>
										<td>
											<?php 
        $stati = get_post_stati( array(), 'objects' );
        $checked_post_stati = $options['ddp_pstati'];
        if ( $stati ) {
            $locked_status_labels = array();
            ?>
												<ul class="checkbox">
													<?php 
            foreach ( $stati as $key => $st ) {
                if ( !$st->show_in_admin_status_list ) {
                    continue;
                }
                $is_free_status = 'publish' === $key;
                if ( !$is_pro && !$is_free_status ) {
                    $locked_status_labels[] = $st->label;
                    continue;
                }
                $checked = array_search( $key, $checked_post_stati, true );
                ?>
														<li>
															<input type="checkbox"
																name="ddp_pstati[]"
																id="ddp_pstatus-<?php 
                echo esc_attr( $key );
                ?>"
																value="<?php 
                echo esc_attr( $key );
                ?>"
																<?php 
                if ( false !== $checked ) {
                    echo ' checked';
                }
                if ( $is_free_status && !$is_pro ) {
                    echo ' checked';
                }
                ?>
															/>
															<label for="ddp_pstatus-<?php 
                echo esc_attr( $key );
                ?>">
																<?php 
                echo esc_html( $key . ' (' . $st->label . ')' );
                if ( 'trash' === $key ) {
                    echo ' <small>' . esc_html__( 'Warning: Enabling this can give false results. Only enable if you know what you are doing.', 'delete-duplicate-posts' ) . '</small>';
                }
                ?>
															</label>
														</li>
														<?php 
            }
            if ( !empty( $locked_status_labels ) ) {
                $locked_status_labels = array_map( static function ( $label ) {
                    return ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label ) );
                }, $locked_status_labels );
                $status_list = wp_sprintf_l( '%l', $locked_status_labels );
                ?>
														<li class="ddp-pro-teaser">
															<?php 
                echo wp_kses_post( self::pro_locked_row( __( 'Scan more post statuses', 'delete-duplicate-posts' ), sprintf( 
                    /* translators: %s: natural-language list of post statuses, e.g. "scheduled, draft, and private". */
                    __( 'Also scan %s posts.', 'delete-duplicate-posts' ),
                    $status_list
                 ) ) );
                ?>
														</li>
														<?php 
            }
            ?>
												</ul>
												<?php 
        }
        ?>
										</td>
									</tr>
									<?php 
        $comparemethod = 'titlecompare';
        global $ddp_fs;
        ?>
									<tr valign="top">
										<th><?php 
        esc_html_e( 'Comparison Method', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<ul class="ddpcomparemethod">

												<li>
													<label>
														<input type="radio" name="ddp_method" value="titlecompare" <?php 
        checked( 'titlecompare', $comparemethod );
        ?> />
														<?php 
        esc_html_e( 'Compare by title (default)', 'delete-duplicate-posts' );
        ?>
														<span class="optiondesc"><?php 
        esc_html_e( 'Looks at the title of the post itself.', 'delete-duplicate-posts' );
        ?></span>
													</label>

												</li>

												<?php 
        if ( $is_pro ) {
            ?>
													<li>
														<label>
															<input type="radio" name="ddp_method" value="metacompare" <?php 
            checked( 'metacompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by meta tag', 'delete-duplicate-posts' );
            ?>
															<?php 
            echo wp_kses_post( self::pro_badge() );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Compare by any meta tag.', 'delete-duplicate-posts' );
            ?></span>
														</label>
														<?php 
            $metavalues = $wpdb->get_results( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER by meta_key;", ARRAY_A );
            ?>
														<div class="ddp-compare-details">

															<select name="ddp_compare_metatag" id="ddp_compare_metatag">
																<?php 
            if ( is_array( $metavalues ) ) {
                $selectedmeta = false;
                if ( isset( $options['ddp_compare_metatag'] ) ) {
                    $selectedmeta = $options['ddp_compare_metatag'];
                }
                if ( !$selectedmeta ) {
                    $selectedmeta = '';
                    $options['ddp_compare_metatag'] = '';
                }
                foreach ( $metavalues as $mv ) {
                    ?>
																		<option value="<?php 
                    echo esc_attr( $mv['meta_key'] );
                    ?>" <?php 
                    selected( esc_attr( $mv['meta_key'] ), $options['ddp_compare_metatag'] );
                    ?>>
																			<?php 
                    echo esc_attr( $mv['meta_key'] );
                    ?></option>
																		<?php 
                }
            }
            ?>
															</select>
														</div>

													</li>

													<li>
														<label>
															<input type="radio" name="ddp_method" value="excerptcompare" <?php 
            checked( 'excerptcompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by excerpt', 'delete-duplicate-posts' );
            ?>
															<?php 
            echo wp_kses_post( self::pro_badge() );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Looks at the excerpt of the post. Posts with empty excerpts are never treated as duplicates.', 'delete-duplicate-posts' );
            ?></span>
														</label>
													</li>
												<?php 
        }
        ?>

												<?php 
        if ( !$is_pro ) {
            ?>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Compare by meta tag', 'delete-duplicate-posts' ), __( 'Find duplicates by SKU, custom fields, or any post meta value.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Compare by excerpt', 'delete-duplicate-posts' ), __( 'Catch posts that share a title but differ in content by matching on the excerpt instead. Empty excerpts are ignored.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
												<?php 
        }
        ?>
											</ul>
										</td>
									</tr>
									<tr>
										<th><label for="ddp_keep"><?php 
        esc_html_e( 'Delete which posts?:', 'delete-duplicate-posts' );
        ?></label></th>
										<td>

											<select name="ddp_keep" id="ddp_keep">
												<option value="oldest" 
												<?php 
        if ( 'oldest' === $options['ddp_keep'] ) {
            echo 'selected="selected"';
        }
        ?>
																								><?php 
        esc_html_e( 'Keep oldest', 'delete-duplicate-posts' );
        ?></option>
												<option value="latest" 
												<?php 
        if ( 'latest' === $options['ddp_keep'] ) {
            echo 'selected="selected"';
        }
        ?>
																								><?php 
        esc_html_e( 'Keep latest', 'delete-duplicate-posts' );
        ?></option>
											</select>
											<p class="description">
												<?php 
        esc_html_e( 'Keep the oldest or the latest version of duplicates? Default is keeping the oldest, and deleting any subsequent duplicate posts', 'delete-duplicate-posts' );
        ?>
											</p>
										</td>
									</tr>

									<?php 
        $deletemode = ( isset( $options['ddp_deletemode'] ) ? $options['ddp_deletemode'] : 'trash' );
        if ( !$is_pro ) {
            $deletemode = 'trash';
        }
        ?>
									<tr valign="top">
										<th><?php 
        esc_html_e( 'Deletion method:', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<ul class="ddpcomparemethod">

												<li>
													<label>
														<input type="radio" name="ddp_deletemode" value="trash" <?php 
        checked( 'trash', $deletemode );
        ?> />
														<?php 
        esc_html_e( 'Move to trash (default)', 'delete-duplicate-posts' );
        ?>
														<span class="optiondesc"><?php 
        esc_html_e( 'Keeps posts recoverable from the WordPress trash.', 'delete-duplicate-posts' );
        ?></span>
													</label>
												</li>

												<?php 
        if ( $is_pro ) {
            ?>
													<li>
														<label>
															<input type="radio" name="ddp_deletemode" value="permanent" <?php 
            checked( 'permanent', $deletemode );
            ?> />
															<?php 
            esc_html_e( 'Delete permanently', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Removes posts from the database. This cannot be undone.', 'delete-duplicate-posts' );
            ?></span>
														</label>
													</li>
												<?php 
        }
        ?>

												<?php 
        if ( !$is_pro ) {
            ?>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Delete permanently', 'delete-duplicate-posts' ), __( 'Remove duplicates from the database instead of moving them to trash.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
												<?php 
        }
        ?>
											</ul>
										</td>
									</tr>

									<tr valign="top">
										<th><?php 
        esc_html_e( 'Enable 301 redirects?:', 'delete-duplicate-posts' );
        ?> <?php 
        echo wp_kses_post( self::pro_badge() );
        ?></th>
										<td>
											<?php 
        if ( $is_pro ) {
            ?>
												<label for="ddp_redirects">
													<input type="checkbox" id="ddp_redirects" name="ddp_redirects"
													<?php 
            if ( true === $options['ddp_redirects'] ) {
                echo 'checked="checked"';
            }
            ?>
													>
													<span class="description"><?php 
            esc_html_e( 'Automatically 301 redirect deleted posts to the original.', 'delete-duplicate-posts' );
            ?></span>
												</label>
											<?php 
        }
        ?>
											<?php 
        if ( !$is_pro ) {
            ?>
												<div class="ddp-pro-teaser">
													<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Automatically 301 redirect deleted posts to the original.', 'delete-duplicate-posts' ) ) );
            ?>
												</div>
											<?php 
        }
        ?>
										</td>
									</tr>

									<tr>
										<td colspan="2">
											<hr>
											<h3><?php 
        esc_html_e( 'Delete Duplicates Automatically', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>

									<tr valign="top">
										<th><?php 
        esc_html_e( 'Enable automatic deletion?:', 'delete-duplicate-posts' );
        ?>
										</th>
										<td><label for="ddp_enabled">
												<input type="checkbox" id="ddp_enabled" name="ddp_enabled" 
												<?php 
        if ( true === $options['ddp_enabled'] ) {
            echo 'checked="checked"';
        }
        ?>
																																										>
												<p class="description">
													<?php 
        esc_html_e( 'Clean duplicates automatically.', 'delete-duplicate-posts' );
        ?></p>
											</label>
										</td>
									</tr>

									<tr>
										<th><label for="ddp_resultslimit"><?php 
        esc_html_e( 'How many:', 'delete-duplicate-posts' );
        ?></label>
										</th>
										<td>

											<?php 
        $dupe_options = array(
            0     => __( 'No limit', 'delete-duplicate-posts' ),
            10000 => number_format_i18n( '10000' ),
            5000  => number_format_i18n( '5000' ),
            2500  => number_format_i18n( '2500' ),
            1000  => number_format_i18n( '1000' ),
            500   => '500',
            250   => '250',
            100   => '100',
            50    => '50',
            10    => '10',
        );
        ?>
											<select name="ddp_resultslimit" id="ddp_resultslimit">
												<?php 
        foreach ( $dupe_options as $key => $label ) {
            ?>
													<option value="<?php 
            echo esc_attr( $key );
            ?>" <?php 
            selected( $options['ddp_resultslimit'], $key );
            ?>>
														<?php 
            echo esc_attr( $label );
            ?></option>
													<?php 
        }
        ?>
											</select>

											<p class="description">
												<?php 
        esc_html_e( 'If you have many duplicates, the plugin might time out before finding them all. Try limiting the amount of duplicates here. Default: Unlimited.', 'delete-duplicate-posts' );
        ?><br>
												<strong><?php 
        esc_html_e( 'This only applies to automatic (CRON) jobs.', 'delete-duplicate-posts' );
        ?></strong>
											</p>
										</td>
									</tr>

									<tr>
										<th><label for="ddp_schedule"><?php 
        esc_html_e( 'How often?:', 'delete-duplicate-posts' );
        ?></label>
										</th>
										<td>

											<select name="ddp_schedule" id="ddp_schedule">
												<?php 
        $schedules = wp_get_schedules();
        if ( $schedules ) {
            foreach ( $schedules as $key => $sch ) {
                ?>
														<option value="<?php 
                echo esc_attr( $key );
                ?>" 
														<?php 
                if ( isset( $options['ddp_schedule'] ) && esc_attr( $key ) === $options['ddp_schedule'] ) {
                    echo esc_html( 'selected="selected"' );
                }
                ?>
																																					><?php 
                echo esc_html( $sch['display'] );
                ?></option>
														<?php 
            }
        }
        ?>
											</select>
											<p class="description">
												<?php 
        esc_html_e( 'How often should the cron job run?', 'delete-duplicate-posts' );
        ?></p>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<hr>
										</td>
									</tr>

									<tr>
										<th><?php 
        esc_html_e( 'Send status mail?:', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<label for="ddp_statusmail">
												<input type="checkbox" id="ddp_statusmail" name="ddp_statusmail" 
												<?php 
        if ( isset( $options['ddp_statusmail'] ) && true === $options['ddp_statusmail'] ) {
            ?>
																																													checked="checked" 
																																													<?php 
        }
        ?>
																																																							>
												<p class="description">
													<?php 
        esc_html_e( 'Sends a status email if duplicates have been found.', 'delete-duplicate-posts' );
        ?>
												</p>
											</label>
										</td>
									</tr>

									<tr>
										<th><?php 
        esc_html_e( 'Email recipient:', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<label for="ddp_statusmail_recipient">

												<input type="text" class="regular-text" id="ddp_statusmail_recipient" name="ddp_statusmail_recipient" value="<?php 
        echo esc_attr( $options['ddp_statusmail_recipient'] );
        ?>">
												<p class="description">
													<?php 
        esc_html_e( 'Who should get the notification email. Separate multiple addresses with commas.', 'delete-duplicate-posts' );
        ?></p>
											</label>
										</td>
									</tr>



									<tr>
										<td colspan="2">
											<hr>
										</td>
									</tr>

									<tr>
										<th><?php 
        esc_html_e( 'Enable debug logging?:', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<label for="ddp_debug">
												<input type="checkbox" id="ddp_debug" name="ddp_debug" 
												<?php 
        if ( isset( $options['ddp_debug'] ) && true === $options['ddp_debug'] ) {
            echo 'checked="checked"';
        }
        ?>
																																								>
												<p class="description">
													<?php 
        esc_html_e( 'Should only be enabled if debugging a problem.', 'delete-duplicate-posts' );
        ?>
												</p>
											</label>
										</td>
									</tr>
									<th colspan=2><input type="submit" class="button-primary" name="delete_duplicate_posts_save" value="<?php 
        esc_html_e( 'Save Settings', 'delete-duplicate-posts' );
        ?>" /></th>
									</tr>
								</table>
							</form>
							</div><!-- #configuration -->
						</div>


						<div id="redirects-tab" class="tab-content<?php 
        echo ( $show_redirects ? '' : ' pro' );
        ?>" style="display: none;">
							<?php 
        if ( $show_redirects ) {
            ?>
								<h3><?php 
            esc_html_e( 'Redirects', 'delete-duplicate-posts' );
            ?></h3>
								<p><?php 
            esc_html_e( 'This table shows all redirects created by the plugin.', 'delete-duplicate-posts' );
            ?></p>
								<table id="ddp_redirtable" class="wp-list-table widefat fixed striped table-view-list">
									<thead>
										<tr>
											<th><?php 
            esc_html_e( 'ID', 'delete-duplicate-posts' );
            ?></th>
											<th><?php 
            esc_html_e( 'From URL', 'delete-duplicate-posts' );
            ?></th>
											<th><?php 
            esc_html_e( 'Target URL', 'delete-duplicate-posts' );
            ?></th>
										</tr>
									</thead>
									<tbody>
										<!-- DataTables will populate this -->
									</tbody>
								</table>
							<?php 
        }
        ?>
							<?php 
        if ( !$show_redirects ) {
            ?>
								<h3><?php 
            esc_html_e( 'Redirects', 'delete-duplicate-posts' );
            ?></h3>
								<p><?php 
            esc_html_e( 'Redirects are a premium feature. Please upgrade to access this functionality.', 'delete-duplicate-posts' );
            ?></p>
							<?php 
        }
        ?>
						</div>

					</div>
				</div>

				<?php 
        include_once DDP_PLUGIN_DIR . 'sidebar.php';
        if ( function_exists( 'ddp_fs' ) ) {
            global $ddp_fs;
        }
        ?>
			</div>

		</div>



		<script>
			jQuery(document).ready(function($) {
				const navTabWrapper = $('.nav-tab-wrapper');
				const currentTabs = $('.nav-tab-wrapper a');

				currentTabs.each(function() {
					$(this).on('click', function(e) {
						const href = $(this).attr('href');

						if (!href.startsWith('#')) {
							e.preventDefault(); // Prevent default tab behavior for full URLs
							window.location.href = href; // Load the page to the URL in the same window
						} else {
							e.preventDefault(); // Prevent default anchor behavior

							// Switch as a regular tab for href starting with '#'
							currentTabs.removeClass('nav-tab-active');
							$(this).addClass('nav-tab-active');

							// Hide all tab content
							$('.tab-content').hide();

							// Show the content for the clicked tab
							$(href).show();
						}
					});
				});

				// Initially hide all tab content except the active one
				$('.tab-content').hide();
				$('.nav-tab-active').each(function() {
					const activeHref = $(this).attr('href');
					$(activeHref).show();
				});
			});
		</script>








		<?php 
    }

}
