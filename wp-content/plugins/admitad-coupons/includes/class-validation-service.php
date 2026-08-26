<?php
/**
 * Human-reviewed classification validation samples.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores bounded, stratified samples and calculates rollout metrics.
 */
final class Promokodiki_Admitad_Validation_Service {
	/**
	 * Create a sample across confidence and campaign strata.
	 *
	 * @param int $size Requested sample size.
	 * @return string Sample UUID.
	 */
	public function create_sample( int $size ): string {
		$size     = max( 1, min( 500, $size ) );
		$ids      = get_posts(
			array(
				'post_type'      => 'promocode',
				'post_status'    => 'any',
				'posts_per_page' => $size,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The bounded validation sample requires classified coupons only.
					array(
						'key'     => '_admitad_classification_confidence',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$buckets  = array();
		$profiles = new Promokodiki_Admitad_Company_Profile_Repository();
		foreach ( $ids as $post_id ) {
			$confidence = sanitize_key( (string) get_post_meta( $post_id, '_admitad_classification_confidence', true ) );
			$campaign   = absint( get_post_meta( $post_id, 'campaign_id', true ) );
			$term_ids   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			sort( $term_ids );
			$profile           = $profiles->profile_for_campaign( $campaign );
			$key               = $confidence . '|' . $campaign;
			$buckets[ $key ][] = array(
				'post_id'          => (int) $post_id,
				'confidence'       => $confidence,
				'campaign_id'      => $campaign,
				'term_ids'         => $term_ids,
				'primary_term_id'  => (int) get_post_meta( $post_id, '_admitad_primary_term_id', true ),
				'locked'           => Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ),
				'locked_term_ids'  => array_map( 'intval', (array) get_post_meta( $post_id, '_admitad_locked_term_ids', true ) ),
				'allowed_term_ids' => $profile['allowed_term_ids'] ?? array(),
			);
		}
		$rows           = array();
		$selected_count = 0;
		while ( $selected_count < $size && $buckets ) {
			foreach ( array_keys( $buckets ) as $key ) {
				$row = array_shift( $buckets[ $key ] );
				if ( $row ) {
					$rows[] = $row;
					++$selected_count;
				}
				if ( ! $buckets[ $key ] ) {
					unset( $buckets[ $key ] );
				}
				if ( $selected_count >= $size ) {
					break;
				}
			}
		}
		$sample_id = wp_generate_uuid4();
		update_option(
			$this->key( $sample_id ),
			array(
				'id'         => $sample_id,
				'owner_id'   => get_current_user_id(),
				'created_at' => time(),
				'rows'       => $rows,
				'reviews'    => array(),
			),
			false
		);
		return $sample_id;
	}

	/**
	 * Read a stored sample.
	 *
	 * @param string $sample_id Sample UUID.
	 * @return array<string,mixed>|null
	 */
	public function sample( string $sample_id ): ?array {
		if ( ! $this->valid_id( $sample_id ) ) {
			return null;
		}
		$sample = get_option( $this->key( $sample_id ), null );
		return is_array( $sample ) && isset( $sample['rows'], $sample['reviews'] ) ? $sample : null;
	}

	/**
	 * Store reviewer-expected categories for one sampled coupon.
	 *
	 * @param string          $sample_id     Sample UUID.
	 * @param int             $post_id       Sampled post ID.
	 * @param array<int, int> $expected_terms Expected existing terms.
	 * @throws InvalidArgumentException For invalid sample data.
	 */
	public function record_review( string $sample_id, int $post_id, array $expected_terms ): void {
		$sample = $this->sample( $sample_id );
		if ( ! $sample || ! in_array( $post_id, array_column( $sample['rows'], 'post_id' ), true ) ) {
			throw new InvalidArgumentException( 'A stored validation sample row is required.' );
		}
		$expected_terms = array_values( array_unique( array_map( 'absint', $expected_terms ) ) );
		foreach ( $expected_terms as $term_id ) {
			if ( ! get_term( $term_id, 'promocode_category' ) instanceof WP_Term ) {
				throw new InvalidArgumentException( 'Every expected category must exist.' );
			}
		}
		sort( $expected_terms );
		$sample['reviews'][ (string) $post_id ] = $expected_terms;
		update_option( $this->key( $sample_id ), $sample, false );
	}

	/**
	 * Calculate acceptance metrics as percentages.
	 *
	 * @param string $sample_id Sample UUID.
	 * @return array<string,float|int>
	 */
	public function report( string $sample_id ): array {
		$sample = $this->sample( $sample_id );
		if ( ! $sample ) {
			return array();
		}
		$other           = get_term_by( 'slug', 'other', 'promocode_category' );
		$other_id        = $other instanceof WP_Term ? (int) $other->term_id : 0;
		$high_total      = 0;
		$high_correct    = 0;
		$non_other       = 0;
		$locked_total    = 0;
		$locks_preserved = 0;
		$profile_total   = 0;
		$out_of_profile  = 0;
		foreach ( $sample['rows'] as $row ) {
			$post_id = (int) $row['post_id'];
			if ( 'high' === $row['confidence'] && isset( $sample['reviews'][ (string) $post_id ] ) ) {
				++$high_total;
				if ( $sample['reviews'][ (string) $post_id ] === $row['term_ids'] ) {
					++$high_correct;
				}
			}
			if ( $other_id <= 0 || (int) $row['primary_term_id'] !== $other_id ) {
				++$non_other;
			}
			if ( $row['locked'] ) {
				++$locked_total;
				$current = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
				sort( $current );
				$locked = array_map( 'intval', $row['locked_term_ids'] );
				sort( $locked );
				if ( $current === $locked ) {
					++$locks_preserved;
				}
			}
			if ( $row['allowed_term_ids'] ) {
				++$profile_total;
				if ( array_diff( $row['term_ids'], $row['allowed_term_ids'] ) ) {
					++$out_of_profile;
				}
			}
		}
		$total = count( $sample['rows'] );
		return array(
			'sample_size'              => $total,
			'reviewed'                 => count( $sample['reviews'] ),
			'high_confidence_accuracy' => $this->percentage( $high_correct, $high_total ),
			'non_other_coverage'       => $this->percentage( $non_other, $total ),
			'lock_preservation'        => $this->percentage( $locks_preserved, $locked_total ),
			'out_of_profile_rate'      => $this->percentage( $out_of_profile, $profile_total ),
		);
	}

	/**
	 * Calculate a one-decimal percentage, using zero for an empty denominator.
	 *
	 * @param int $part  Numerator.
	 * @param int $whole Denominator.
	 */
	private function percentage( int $part, int $whole ): float {
		return $whole > 0 ? round( 100 * $part / $whole, 1 ) : 0.0;
	}

	/**
	 * Return a sample option key.
	 *
	 * @param string $sample_id Sample UUID.
	 */
	private function key( string $sample_id ): string {
		return 'promokodiki_admitad_validation_' . sanitize_key( $sample_id );
	}

	/**
	 * Validate an exact UUID so arbitrary option names are never accepted.
	 *
	 * @param string $id UUID.
	 */
	private function valid_id( string $id ): bool {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $id );
	}
}
