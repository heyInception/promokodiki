<?php
/** Bounded background queue for shop deeplinks. @package Promokodiki_Admitad */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Promokodiki_Admitad_Deeplink_Queue {
	private const OPTION = 'promokodiki_admitad_deeplink_queue';
	private const HOOK   = 'promokodiki_admitad_deeplink_batch';
	/** @var callable */
	private $processor;

	public function __construct( ?callable $processor = null ) {
		$this->processor = $processor ?: static fn( int $term_id ) => ( new Promokodiki_Admitad_Deeplink_Service() )->process_term( $term_id );
	}

	public static function handle(): void { ( new self() )->run_batch(); }

	public function enqueue( int $term_id ): void {
		if ( $term_id < 1 ) { return; }
		$queue   = $this->queue();
		$queue[] = $term_id;
		update_option( self::OPTION, array_values( array_unique( array_map( 'absint', $queue ) ) ), false );
		if ( ! wp_next_scheduled( self::HOOK ) ) { wp_schedule_single_event( time() + 5, self::HOOK ); }
	}

	public function enqueue_all(): int {
		$ids = $this->eligible_ids();
		if ( is_wp_error( $ids ) ) { return 0; }
		foreach ( $ids as $id ) { $this->enqueue( (int) $id ); }
		return count( $ids );
	}

	/** @return array<string, int> */
	public function preview_all(): array {
		$counts  = array_fill_keys( array( 'create', 'update', 'unchanged', 'unsupported', 'invalid' ), 0 );
		$service = new Promokodiki_Admitad_Deeplink_Service();
		foreach ( $this->eligible_ids() as $id ) { ++$counts[ $service->preview_term( (int) $id ) ]; }
		return $counts;
	}

	public function run_batch( int $limit = 20 ): void {
		$queue = $this->queue();
		$batch = array_splice( $queue, 0, max( 1, min( 50, $limit ) ) );
		update_option( self::OPTION, $queue, false );
		foreach ( $batch as $term_id ) { call_user_func( $this->processor, (int) $term_id ); }
		if ( $queue && ! wp_next_scheduled( self::HOOK ) ) { wp_schedule_single_event( time() + 10, self::HOOK ); }
	}

	public function pending_count(): int { return count( $this->queue() ); }

	/** @return array<int, int> */
	private function queue(): array { return array_values( array_filter( array_map( 'absint', (array) get_option( self::OPTION, array() ) ) ) ); }

	/** @return array<int, int> */
	private function eligible_ids(): array {
		$ids = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'admitad_campaign_id', 'compare' => 'EXISTS' ) ) ) );
		return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}
}
