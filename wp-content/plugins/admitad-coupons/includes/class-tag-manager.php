<?php
/**
 * Controlled imported-coupon tags.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds and removes only plugin-managed structured tags.
 */
final class Promokodiki_Admitad_Tag_Manager {
	/**
	 * Synchronize managed tags while preserving editorial tags.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $coupon  Normalized coupon.
	 */
	public function sync( int $post_id, array $coupon ): void {
		if ( ! Promokodiki_Admitad_Config::get( 'auto_tags' ) || 'promocode' !== get_post_type( $post_id ) ) {
			return;
		}
		$text  = Promokodiki_Admitad_Text_Normalizer::normalize(
			(string) ( $coupon['title'] ?? '' ) . ' '
			. (string) ( $coupon['description'] ?? '' ) . ' '
			. implode( ' ', array_map( static fn( array $type ): string => (string) ( $type['name'] ?? '' ), (array) ( $coupon['types'] ?? array() ) ) )
		);
		$names = array();
		if ( '' !== trim( (string) ( $coupon['discount'] ?? '' ) ) ) {
			$names[] = 'Скидка';
		}
		$traits = array(
			'Бесплатная доставка' => array( 'бесплатная доставка', 'доставка бесплатно' ),
			'Подарок'             => array( 'подарок', 'в подарок' ),
			'Новым клиентам'      => array( 'новым клиент', 'первый заказ' ),
			'Эксклюзив'           => array( 'эксклюзив' ),
			'Персональный'        => array( 'персональ' ),
		);
		foreach ( $traits as $name => $phrases ) {
			foreach ( $phrases as $phrase ) {
				if ( str_contains( $text, $phrase ) ) {
					$names[] = $name;
					break;
				}
			}
		}

		$desired_ids = array();
		foreach ( array_unique( $names ) as $name ) {
			$term = term_exists( $name, 'post_tag' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'post_tag' );
			}
			if ( ! is_wp_error( $term ) ) {
				$desired_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}
		$current_ids = array_map( 'intval', wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) ) );
		$old_managed = array_map( 'intval', (array) get_post_meta( $post_id, '_admitad_managed_tag_ids', true ) );
		$editorial   = array_values( array_diff( $current_ids, $old_managed ) );
		wp_set_post_terms( $post_id, array_values( array_unique( array_merge( $editorial, $desired_ids ) ) ), 'post_tag', false );
		update_post_meta( $post_id, '_admitad_managed_tag_ids', $desired_ids );
	}
}
