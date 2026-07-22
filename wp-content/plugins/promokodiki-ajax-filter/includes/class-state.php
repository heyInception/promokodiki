<?php
/**
 * Request state normalization.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_State {
	public static function from_request( array $request, array $settings, string $context_type ): array {
		$category_id = self::non_negative_int( $request['paf_category'] ?? 0 );
		$brand_id    = self::non_negative_int( $request['paf_brand'] ?? 0 );
		$page        = max( 1, self::non_negative_int( $request['paf_page'] ?? 1 ) );
		$popular     = 'home' === $context_type && self::is_truthy( $request['paf_popular'] ?? false );

		$enabled_sorts = isset( $settings['enabled_sorts'] ) && is_array( $settings['enabled_sorts'] )
			? $settings['enabled_sorts']
			: Promokodiki_Filter_Settings::defaults()['enabled_sorts'];
		$default_sort = (string) ( $settings['default_sort'] ?? 'newest' );
		$requested_sort = sanitize_key( (string) ( $request['paf_sort'] ?? $default_sort ) );
		$sort = in_array( $requested_sort, $enabled_sorts, true ) ? $requested_sort : $default_sort;

		if ( $popular ) {
			$category_id = 0;
			$brand_id    = 0;
			$sort        = '';
		}

		return array(
			'category_id' => $category_id,
			'brand_id'    => $brand_id,
			'sort'        => $sort,
			'popular'     => $popular,
			'page'        => $page,
		);
	}

	private static function non_negative_int( mixed $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		return max( 0, (int) $value );
	}

	private static function is_truthy( mixed $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}
}
