<?php
/**
 * Plugin settings.
 *
 * @package DeleteDuplicatePosts
 */

namespace DeleteDuplicatePosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class DDP_Settings {

	public static $options_name = 'delete_duplicate_posts_options_v4';
	public static $options      = null;

	/**
	 * Return default options
	 *
	 * @author   Lars Koudal
	 * @since    v0.0.1
	 * @version  v1.0.0  Friday, July 2nd, 2021.
	 * @access   public static
	 * @return   mixed
	 */
	public static function default_options() {
		$defaults = array(
			'ddp_running'              => 'false',
			'ddp_keep'                 => 'oldest',
			'ddp_deletemode'           => 'trash',
			'ddp_pts'                  => array( 'post', 'page' ),
			'ddp_statusmail_recipient' => '',
			'ddp_statusmail'           => 0,
			'ddp_resultslimit'         => 0,
			'ddp_enabled'              => 0,
			'ddp_pstati'               => array( 'publish' ),
			'ddp_debug'                => 0,
			'ddp_redirects'            => 0,
		);
		return $defaults;
	}


	/**
	 * get plugin's options
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @return  mixed
	 */
	public static function get_options() {
		if ( null !== DDP_Settings::$options ) {
			return DDP_Settings::$options;
		}

		$options = get_option( DDP_Settings::$options_name, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = array_merge( DDP_Settings::default_options(), $options );

		DDP_Settings::$options = $options;

		return $options;
	}


	/**
	 * Saves options
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $newoptions
	 * @return  mixed
	 */
	public static function save_options( $newoptions ) {
		DDP_Settings::$options = $newoptions;
		return update_option( DDP_Settings::$options_name, $newoptions );
	}


	/**
	 * Parse a comma- or semicolon-separated list of email addresses.
	 *
	 * @param string $raw Raw recipient field value.
	 * @return string[] Valid, unique email addresses.
	 */
	public static function parse_email_recipients( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[,;]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$valid = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( $email && is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return array_values( array_unique( $valid ) );
	}


	/**
	 * Fetch plugin version from plugin PHP header
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @return  mixed
	 */
	public static function get_plugin_version() {
		$plugin_data = get_file_data( DDP_PLUGIN_FILE, array( 'version' => 'Version' ), 'plugin' );
		return $plugin_data['version'];
	}


	/**
	 * add_freemius_extra_permission.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $permissions
	 * @return  mixed
	 */
	public static function add_freemius_extra_permission( $permissions ) {

		$permissions['helpscout'] = array(
			'icon-class' => 'dashicons dashicons-sos',
			'label'      => 'Help Scout',
			'desc'       => __( 'Rendering Help Scouts beacon for easy help and support', 'delete-duplicate-posts' ),
			'priority'   => 16,
		);

		$permissions['newsletter'] = array(
			'icon-class' => 'dashicons dashicons-email-alt2',
			'label'      => 'Newsletter',
			'desc'       => __( 'Your email is added to cleverplugins.com newsletter. Unsubscribe any time.', 'delete-duplicate-posts' ),
			'priority'   => 18,
		);

		return $permissions;
	}

}
