<?php
/**
 * Filter settings and validation.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Settings {
	public const OPTION_NAME = 'promokodiki_filter_settings';

	private const SORTS = array( 'newest', 'popular', 'expiring', 'oldest' );

	public static function defaults(): array {
		return array(
			'initial_count'     => 8,
			'load_more_count'   => 8,
			'popular_days'      => 7,
			'default_sort'      => 'newest',
			'enabled_sorts'     => self::SORTS,
			'show_expired'      => false,
			'category_label'    => 'Категории',
			'brand_label'       => 'Бренды',
			'popular_label'     => 'Популярное за неделю',
			'sort_label'        => 'Без сортировки',
			'load_more_label'   => 'Показать ещё',
			'retry_label'       => 'Повторить',
			'apply_label'       => 'Применить',
			'empty_label'       => 'Промокоды не найдены.',
			'weekly_empty_label'=> 'Данных пока нет.',
		);
	}

	public static function get(): array {
		$value = get_option( self::OPTION_NAME, array() );
		return self::sanitize( is_array( $value ) ? $value : array() );
	}

	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = $defaults;

		$output['initial_count']   = self::bounded_int( $input['initial_count'] ?? $defaults['initial_count'], 1, 100 );
		$output['load_more_count'] = self::bounded_int( $input['load_more_count'] ?? $defaults['load_more_count'], 1, 100 );
		$output['popular_days']    = self::bounded_int( $input['popular_days'] ?? $defaults['popular_days'], 1, 31 );
		$output['show_expired']    = ! empty( $input['show_expired'] );

		$requested_sorts = isset( $input['enabled_sorts'] ) && is_array( $input['enabled_sorts'] )
			? array_map( 'sanitize_key', $input['enabled_sorts'] )
			: $defaults['enabled_sorts'];
		$enabled_sorts  = array_values( array_intersect( self::SORTS, $requested_sorts ) );
		$output['enabled_sorts'] = $enabled_sorts ?: $defaults['enabled_sorts'];

		$default_sort = sanitize_key( (string) ( $input['default_sort'] ?? $defaults['default_sort'] ) );
		$output['default_sort'] = in_array( $default_sort, $output['enabled_sorts'], true )
			? $default_sort
			: $output['enabled_sorts'][0];

		foreach ( self::label_keys() as $key ) {
			$output[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? $defaults[ $key ] ) );
			if ( '' === $output[ $key ] ) {
				$output[ $key ] = $defaults[ $key ];
			}
		}

		return $output;
	}

	public static function allowed_sorts(): array {
		return self::SORTS;
	}

	private static function bounded_int( mixed $value, int $minimum, int $maximum ): int {
		return max( $minimum, min( $maximum, (int) $value ) );
	}

	private static function label_keys(): array {
		return array(
			'category_label',
			'brand_label',
			'popular_label',
			'sort_label',
			'load_more_label',
			'retry_label',
			'apply_label',
			'empty_label',
			'weekly_empty_label',
		);
	}
}
