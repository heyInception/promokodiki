<?php
/**
 * Immutable classification result.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries selected terms, confidence, and an audit explanation.
 */
final class Promokodiki_Admitad_Classification_Result {
	/**
	 * Primary term ID.
	 *
	 * @var int
	 */
	private int $primary_term_id;

	/**
	 * Selected term IDs.
	 *
	 * @var array<int, int>
	 */
	private array $term_ids;

	/**
	 * Confidence label.
	 *
	 * @var string
	 */
	private string $confidence;

	/**
	 * Explainable evidence.
	 *
	 * @var array<string, mixed>
	 */
	private array $explanation;

	/**
	 * Constructor.
	 *
	 * @param array<int, int>     $term_ids       Selected terms.
	 * @param int                 $primary_term_id Primary term.
	 * @param string              $confidence     Confidence.
	 * @param array<string,mixed> $explanation    Evidence.
	 */
	public function __construct( array $term_ids, int $primary_term_id, string $confidence, array $explanation ) {
		$this->term_ids        = array_values( array_unique( array_map( 'absint', $term_ids ) ) );
		$this->primary_term_id = absint( $primary_term_id );
		$this->confidence      = sanitize_key( $confidence );
		$this->explanation     = $explanation;
	}

	/**
	 * Build an absolute manual-lock result.
	 *
	 * @param array<int, int> $term_ids       Locked terms.
	 * @param int             $primary_term_id Locked primary.
	 */
	public static function locked( array $term_ids, int $primary_term_id ): self {
		return new self(
			$term_ids,
			$primary_term_id,
			'locked',
			array(
				'algorithm_version' => '1.0',
				'locked'            => true,
				'signals'           => array(),
				'rejected'          => array(),
				'conflicts'         => array(),
				'rule_versions'     => array(),
			)
		);
	}

	/**
	 * Return the primary category term.
	 */
	public function primary_term_id(): int {
		return $this->primary_term_id;
	}

	/**
	 * Return selected category terms in rank order.
	 *
	 * @return array<int, int>
	 */
	public function term_ids(): array {
		return $this->term_ids;
	}

	/**
	 * Return the confidence label.
	 */
	public function confidence(): string {
		return $this->confidence;
	}

	/**
	 * Return the complete explanation.
	 *
	 * @return array<string, mixed>
	 */
	public function explanation(): array {
		return $this->explanation;
	}
}
