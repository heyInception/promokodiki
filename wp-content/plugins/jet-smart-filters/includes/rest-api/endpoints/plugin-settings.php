<?php
namespace Jet_Smart_Filters\Endpoints;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define Posts class
 */
class Plugin_Settings extends Base {
	/**
	 * Returns route name
	 */
	public function get_name() {

		return 'plugin-settings';
	}

	public function get_args() {

		return array(
			'key'      => array(
				'required' => false,
			),
			'settings' => array(
				'required' => true,
			),
		);
	}

	public function callback( $request ) {

		$args     = $request->get_params();
		$key      = $args['key'];
		$settings = $args['settings'];

		if ( $key ) {

			// update specified option by key
			$data = array_map(
				function( $setting ) {
					return is_array( $setting ) ? $setting : esc_attr( $setting );
				},
				$settings
			);

			jet_smart_filters()->settings->update( $key, $data );

			if ( $key === 'seo_sitemap_rules' ) {
				jet_smart_filters()->seo_sitemap->update_seo_sitemap();
			}

		} else {

			// update all settings
			$data = array_map(
				function( $setting ) {
					return is_array( $setting ) ? $setting : esc_attr( $setting );
				},
				$settings
			);

			jet_smart_filters()->seo_sitemap->process_seo_sitemap_settings( $data );

			update_option( jet_smart_filters()->settings->key, $data );

		}

		return rest_ensure_response( [
			'status'  => 'success',
			'message' => __( 'Settings have been saved', 'jet-smart-filters' ),
		] );
	}

	/**
	 * Check user access to current end-popint
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'manage_options' );
	}
}
