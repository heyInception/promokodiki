<?php
/** Safe, read-first coordinator for legacy recovery. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Promokodiki_Admitad_Recovery_Coordinator {
	private const MIGRATION_OPTION = 'promokodiki_admitad_recovery_migration';
	private Promokodiki_Admitad_Legacy_Migration $migration; private Promokodiki_Admitad_Backup_Gate $backup; private Promokodiki_Admitad_Sync_Coordinator $sync; private Promokodiki_Admitad_Sync_Run_Repository $runs;
	public function __construct( $migration = null, $backup = null, $sync = null, $runs = null ) { $this->migration = $migration ?? new Promokodiki_Admitad_Legacy_Migration(); $this->backup = $backup ?? new Promokodiki_Admitad_Backup_Gate(); $this->sync = $sync ?? new Promokodiki_Admitad_Sync_Coordinator(); $this->runs = $runs ?? new Promokodiki_Admitad_Sync_Run_Repository(); }
	/** Return a read-only, path-free readiness report. */
	public function preflight(): array { $analysis = $this->migration->analyze(); $backup = $this->backup->verify(); $reference = $this->reference_ready(); $blockers = array(); if ( is_wp_error( $backup ) ) { $blockers[] = $backup->get_error_code(); } if ( is_wp_error( $reference ) ) { $blockers[] = 'reference_required'; } return array( 'legacy_keywords' => (int) $analysis['legacy_keywords'], 'legacy_companies' => (int) $analysis['legacy_companies'], 'new_rules' => $this->count_table( 'rule' ), 'new_profiles' => $this->count_table( 'company_profile' ), 'backup_ready' => true === $backup, 'reference_ready' => true === $reference, 'latest_reference_run' => $this->runs->latest_completed( 'reference' ), 'migration' => $this->migration->verify(), 'blockers' => $blockers, 'issues' => array() ); }
	/** Start the existing bounded reference coordinator; this method never imports coupons. */
	public function start_reference_sync() { return $this->sync->start_reference_sync(); }
	/** Start a durable migration only after the independent recovery gates are verified. */
	public function start_migration() {
		if ( true !== $this->backup->verify() ) { return new WP_Error( 'backup_required', 'Проверенная резервная копия обязательна.' ); }
		if ( true !== $this->reference_ready() ) { return new WP_Error( 'reference_required', 'Справочные данные ещё не готовы.' ); }
		$state = $this->migration_state();
		if ( 'running' === $state['status'] && (int) $state['heartbeat'] + 600 >= time() ) { return new WP_Error( 'migration_locked', 'Миграция уже выполняется.' ); }
		$analysis = $this->migration->analyze(); $owner = wp_generate_uuid4();
		$state = array( 'status' => 'running', 'owner' => $owner, 'cursor' => 0, 'total' => (int) $analysis['total'], 'processed' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'started_at' => time(), 'heartbeat' => time(), 'source_counts' => $analysis );
		update_option( self::MIGRATION_OPTION, $state, false ); return $state;
	}
	/** Execute exactly one bounded batch for its durable owner. */
	public function migrate_next_batch( string $owner ) {
		$state = $this->migration_state();
		if ( 'running' !== $state['status'] ) { return new WP_Error( 'migration_not_running', 'Миграция не запущена.' ); }
		if ( ! hash_equals( (string) $state['owner'], $owner ) && (int) $state['heartbeat'] + 600 >= time() ) { return new WP_Error( 'migration_locked', 'Миграция принадлежит другому сеансу.' ); }
		$batch = $this->migration->migrate_batch( (int) $state['cursor'], 200 );
		foreach ( array( 'processed', 'created', 'skipped' ) as $key ) { $state[ $key ] += (int) $batch[ $key ]; }
		$state['cursor'] = (int) $batch['next_offset']; $state['heartbeat'] = time(); $state['owner'] = $owner;
		if ( $batch['complete'] ) { $verify = $this->migration->verify(); $state['verification'] = $verify; $state['status'] = $verify['complete'] && $this->same_source_counts( $state['source_counts'], $this->migration->analyze() ) ? 'completed' : 'failed'; if ( 'failed' === $state['status'] ) { ++$state['failed']; } }
		update_option( self::MIGRATION_OPTION, $state, false ); return $state;
	}
	/** Read path-free durable progress for AJAX polling and resume. */
	public function migration_progress(): array { return $this->migration_state(); }
	/** Require a completed reference run which actually persisted both reference types. */
	public function reference_ready() { if ( ! $this->runs->latest_completed( 'reference' ) || $this->reference_count( 'company_profile' ) <= 0 || $this->reference_count( 'category_map' ) <= 0 ) { return new WP_Error( 'reference_not_ready', 'Справочные данные ещё не готовы.' ); } return true; }
	private function count_table( string $table ): int { global $wpdb; $name = Promokodiki_Admitad_Schema::table( $table ); return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" ); }
	private function reference_count( string $table ): int { global $wpdb; $name = Promokodiki_Admitad_Schema::table( $table ); if ( 'category_map' === $table ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$name} WHERE source_namespace = %s", 'coupon' ) ); } return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" ); }
	private function migration_state(): array { return array_merge( array( 'status' => 'idle', 'owner' => '', 'cursor' => 0, 'total' => 0, 'processed' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'started_at' => 0, 'heartbeat' => 0, 'source_counts' => array() ), (array) get_option( self::MIGRATION_OPTION, array() ) ); }
	private function same_source_counts( array $before, array $after ): bool { foreach ( array( 'legacy_keywords', 'legacy_companies', 'legacy_category_names' ) as $key ) { if ( (int) ( $before[ $key ] ?? -1 ) !== (int) ( $after[ $key ] ?? -2 ) ) { return false; } } return true; }
}
