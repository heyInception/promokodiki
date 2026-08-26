<?php
/**
 * Conservative suspected duplicate detection.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds strong duplicate candidates without mutating or merging them.
 */
final class Promokodiki_Admitad_Duplicate_Detector {
	/**
	 * Find separate coupons sharing campaign, code, title, and overlapping dates.
	 *
	 * @param array<string, mixed> $coupon Normalized coupon.
	 * @return array<int, int> Candidate post IDs.
	 */
	public function find( array $coupon ): array {
		$campaign_id = sanitize_text_field( (string) ( $coupon['campaign']['id'] ?? '' ) );
		$code        = sanitize_text_field( (string) ( $coupon['promocode'] ?? '' ) );
		$external_id = sanitize_text_field( (string) ( $coupon['external_id'] ?? '' ) );
		$title       = Promokodiki_Admitad_Text_Normalizer::normalize( (string) ( $coupon['title'] ?? '' ) );
		if ( '' === $campaign_id || '' === $code || '' === $external_id || '' === $title ) {
			return array();
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The lookup is bounded to 20 candidates by two exact canonical meta values.
		$candidates = get_posts(
			array(
				'post_type'                    => 'promocode',
				'post_status'                  => 'any',
				'posts_per_page'               => 20,
				'fields'                       => 'ids',
				'no_found_rows'                => true,
				'promokodiki_include_inactive' => true,
				'meta_query'                   => array(
					'relation' => 'AND',
					array(
						'key'   => 'campaign_id',
						'value' => $campaign_id,
					),
					array(
						'key'   => '_promocode_code',
						'value' => $code,
					),
				),
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$matches = array();
		foreach ( $candidates as $post_id ) {
			if ( (string) get_post_meta( $post_id, 'admitad_coupon_id', true ) === $external_id ) {
				continue;
			}
			if ( Promokodiki_Admitad_Text_Normalizer::normalize( get_the_title( $post_id ) ) !== $title ) {
				continue;
			}
			if ( ! $this->dates_overlap( $coupon, (int) $post_id ) ) {
				continue;
			}
			$matches[] = (int) $post_id;
		}
		sort( $matches );
		return $matches;
	}

	/**
	 * Check inclusive date overlap.
	 *
	 * @param array<string, mixed> $coupon  Coupon.
	 * @param int                  $post_id Candidate post.
	 */
	private function dates_overlap( array $coupon, int $post_id ): bool {
		$new_start = strtotime( (string) ( $coupon['date_start'] ?? '' ) );
		$new_end   = strtotime( (string) ( $coupon['date_end'] ?? '' ) );
		$old_start = strtotime( (string) get_post_meta( $post_id, 'date_start', true ) );
		$old_end   = strtotime( (string) get_post_meta( $post_id, 'date_end', true ) );
		if ( false === $new_start || false === $new_end || false === $old_start || false === $old_end ) {
			return false;
		}
		return $new_start <= $old_end && $old_start <= $new_end;
	}
}
