<?php
/**
 * Deterministic raw Admitad payload fixtures.
 *
 * @package Promokodiki_Admitad
 */

/**
 * Builds end-to-end API payloads without production data.
 */
final class Promokodiki_Admitad_Fixtures {
	/**
	 * Unique fixture prefix.
	 */
	private string $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Unique test prefix.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Return a named raw coupon fixture.
	 *
	 * @param string               $name      Fixture name.
	 * @param array<string,mixed>  $overrides Field overrides.
	 * @return array<string,mixed>
	 */
	public function coupon( string $name, array $overrides = array() ): array {
		$base = array(
			'id'                 => $this->prefix . '-' . $name,
			'status'             => 'active',
			'name'               => 'Fixture ' . $name,
			'description'        => 'Fixture description',
			'short_name'         => 'Fixture',
			'campaign'           => array(
				'id'       => '880001',
				'name'     => 'Fixture Campaign',
				'site_url' => 'https://example.test',
			),
			'categories'         => array(),
			'types'              => array(),
			'species'            => 'promocode',
			'promocode'          => 'FIXTURE',
			'goto_link'          => 'https://example.test/go/' . $name,
			'frameset_link'      => 'https://example.test/frame/' . $name,
			'image'              => '',
			'date_start'         => gmdate( 'c', time() - HOUR_IN_SECONDS ),
			'date_end'           => gmdate( 'c', time() + DAY_IN_SECONDS ),
			'discount'           => '',
			'language'           => 'ru',
			'regions'            => array( 'ru' ),
			'has_affiliate_link' => true,
		);
		return array_replace_recursive( $base, $overrides );
	}
}
