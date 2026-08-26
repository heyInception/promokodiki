<?php
/**
 * Public visibility policy for imported coupons.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps inactive imported coupons out of lists while preserving their URLs.
 */
final class Promokodiki_Admitad_Visibility {
	/**
	 * Register visibility hooks.
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', array( self::class, 'filter_query' ) );
		add_filter( 'the_content', array( self::class, 'prepend_inactive_notice' ) );
	}

	/**
	 * Return the reusable active-or-unspecified meta predicate.
	 *
	 * @return array<string, mixed>
	 */
	public static function active_meta_clause(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => '_promocode_is_active',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_promocode_is_active',
				'value'   => 'no',
				'compare' => '!=',
			),
		);
	}

	/**
	 * Exclude inactive coupons from public non-singular promocode queries.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_query( WP_Query $query ): void {
		if (
			is_admin()
			|| $query->is_singular()
			|| $query->get( 'promokodiki_include_inactive' )
			|| ! self::is_promocode_query( $query )
		) {
			return;
		}

		$current = $query->get( 'meta_query' );
		$current = is_array( $current ) ? $current : array();
		if ( $current ) {
			$current = array(
				'relation' => 'AND',
				$current,
				self::active_meta_clause(),
			);
		} else {
			$current = array( self::active_meta_clause() );
		}
		$query->set( 'meta_query', $current );
	}

	/**
	 * Return an inactive notice for one imported coupon.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function inactive_notice( int $post_id ): string {
		if (
			'no' !== get_post_meta( $post_id, '_promocode_is_active', true )
			|| '' === (string) get_post_meta( $post_id, 'admitad_coupon_id', true )
		) {
			return '';
		}

		return '<div class="promokodiki-admitad-inactive-notice" role="status">'
			. esc_html__( 'Этот промокод больше не действует.', 'promokodiki-admitad' )
			. '</div>';
	}

	/**
	 * Prepend the notice on an inactive coupon singular.
	 *
	 * @param string $content Post content.
	 */
	public static function prepend_inactive_notice( string $content ): string {
		if ( ! is_singular( 'promocode' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return self::inactive_notice( (int) get_the_ID() ) . $content;
	}

	/**
	 * Determine whether a query explicitly lists promocodes.
	 *
	 * @param WP_Query $query Query.
	 */
	private static function is_promocode_query( WP_Query $query ): bool {
		$post_type = $query->get( 'post_type' );
		return 'promocode' === $post_type
			|| ( is_array( $post_type ) && in_array( 'promocode', $post_type, true ) )
			|| $query->is_post_type_archive( 'promocode' )
			|| $query->is_tax( array( 'promocode_category', 'shops_category' ) );
	}
}
