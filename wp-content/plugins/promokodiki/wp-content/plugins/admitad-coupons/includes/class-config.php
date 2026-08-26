<?php
/**
 * Admitad automation configuration.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and sanitizes plugin configuration.
 */
final class Promokodiki_Admitad_Config {
	/**
	 * Settings option name.
	 */
	public const OPTION_NAME = 'promokodiki_admitad_settings';

	/**
	 * Return safe defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'coupon_interval'        => HOUR_IN_SECONDS,
			'reference_interval'     => DAY_IN_SECONDS,
			'reconcile_interval'     => DAY_IN_SECONDS,
			'batch_size'             => 200,
			'max_retries'            => 3,
			'retry_base_seconds'     => 30,
			'missing_threshold'      => 2,
			'confidence_high'        => 80,
			'confidence_medium'      => 50,
			'weight_coupon_category' => 100,
			'weight_campaign'        => 60,
			'weight_company'         => 40,
			'weight_title'           => 20,
			'weight_description'     => 10,
			'max_categories'         => 3,
			'candidate_evidence'     => 5,
			'candidate_campaigns'    => 2,
			'candidate_conflicts'    => 0,
			'auto_tags'              => true,
			'email_alerts'           => true,
			'email_recipient'        => sanitize_email( get_option( 'admin_email', '' ) ),
			'queue_warning_count'    => 25,
			'editor_review_enabled'  => true,
			'log_retention_days'     => 90,
		);
	}

	/**
	 * Read one setting or credential.
	 *
	 * @param string $key Configuration key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$credential_constants = array(
			'client_id'     => 'PROMOKODIKI_ADMITAD_CLIENT_ID',
			'client_secret' => 'PROMOKODIKI_ADMITAD_CLIENT_SECRET',
			'website_id'    => 'PROMOKODIKI_ADMITAD_WEBSITE_ID',
		);

		if ( isset( $credential_constants[ $key ] ) ) {
			$constant = $credential_constants[ $key ];
			if ( defined( $constant ) ) {
				return sanitize_text_field( (string) constant( $constant ) );
			}

			return sanitize_text_field( (string) get_option( 'promokodiki_admitad_' . $key, '' ) );
		}

		$settings = self::sanitize( (array) get_option( self::OPTION_NAME, array() ) );
		return $settings[ $key ] ?? null;
	}

	/**
	 * Sanitize settings and enforce operational bounds.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$settings = array_merge( self::defaults(), $input );

		$settings['coupon_interval']    = self::clamp( $settings['coupon_interval'], 300, WEEK_IN_SECONDS );
		$settings['reference_interval'] = self::clamp( $settings['reference_interval'], HOUR_IN_SECONDS, WEEK_IN_SECONDS );
		$settings['reconcile_interval'] = self::clamp( $settings['reconcile_interval'], HOUR_IN_SECONDS, WEEK_IN_SECONDS );
		$settings['batch_size']         = self::clamp( $settings['batch_size'], 1, 500 );
		$settings['max_retries']        = self::clamp( $settings['max_retries'], 0, 10 );
		$settings['retry_base_seconds'] = self::clamp( $settings['retry_base_seconds'], 5, HOUR_IN_SECONDS );
		$settings['missing_threshold']  = self::clamp( $settings['missing_threshold'], 1, 10 );
		$settings['confidence_high']    = self::clamp( $settings['confidence_high'], 1, 100 );
		$settings['confidence_medium']  = self::clamp( $settings['confidence_medium'], 1, 100 );

		foreach ( array( 'weight_coupon_category', 'weight_campaign', 'weight_company', 'weight_title', 'weight_description' ) as $key ) {
			$settings[ $key ] = self::clamp( $settings[ $key ], 0, 1000 );
		}

		$settings['max_categories']        = self::clamp( $settings['max_categories'], 1, 3 );
		$settings['candidate_evidence']    = self::clamp( $settings['candidate_evidence'], 1, 100 );
		$settings['candidate_campaigns']   = self::clamp( $settings['candidate_campaigns'], 1, 100 );
		$settings['candidate_conflicts']   = self::clamp( $settings['candidate_conflicts'], 0, 100 );
		$settings['queue_warning_count']   = self::clamp( $settings['queue_warning_count'], 1, 10000 );
		$settings['log_retention_days']    = self::clamp( $settings['log_retention_days'], 7, 3650 );
		$settings['auto_tags']             = self::to_bool( $settings['auto_tags'] );
		$settings['email_alerts']          = self::to_bool( $settings['email_alerts'] );
		$settings['editor_review_enabled'] = self::to_bool( $settings['editor_review_enabled'] );
		$settings['email_recipient']       = sanitize_email( (string) $settings['email_recipient'] );

		return $settings;
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 */
	private static function clamp( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Normalize checkbox-like input.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function to_bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}
}
