<?php
/** Safe, read-first coordinator for legacy recovery. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Promokodiki_Admitad_Recovery_Coordinator {
	private Promokodiki_Admitad_Legacy_Migration $migration; private Promokodiki_Admitad_Backup_Gate $backup; private Promokodiki_Admitad_Sync_Coordinator $sync; private Promokodiki_Admitad_Sync_Run_Repository $runs;
	public function __construct( $migration = null, $backup = null, $sync = null, $runs = null ) { $this->migration = $migration ?? new Promokodiki_Admitad_Legacy_Migration(); $this->backup = $backup ?? new Promokodiki_Admitad_Backup_Gate(); $this->sync = $sync ?? new Promokodiki_Admitad_Sync_Coordinator(); $this->runs = $runs ?? new Promokodiki_Admitad_Sync_Run_Repository(); }
	/** Return a read-only, path-free readiness report. */
	public function preflight(): array { $analysis = $this->migration->analyze(); $backup = $this->backup->verify(); $reference = $this->reference_ready(); $blockers = array(); if ( is_wp_error( $backup ) ) { $blockers[] = $backup->get_error_code(); } if ( is_wp_error( $reference ) ) { $blockers[] = 'reference_required'; } return array( 'legacy_keywords' => (int) $analysis['legacy_keywords'], 'legacy_companies' => (int) $analysis['legacy_companies'], 'new_rules' => $this->count_table( 'rule' ), 'new_profiles' => $this->count_table( 'company_profile' ), 'backup_ready' => true === $backup, 'reference_ready' => true === $reference, 'latest_reference_run' => $this->runs->latest_completed( 'reference' ), 'migration' => $this->migration->verify(), 'blockers' => $blockers, 'issues' => array() ); }
	/** Start the existing bounded reference coordinator; this method never imports coupons. */
	public function start_reference_sync() { return $this->sync->start_reference_sync(); }
	/** Require a completed reference run which actually persisted both reference types. */
	public function reference_ready() { if ( ! $this->runs->latest_completed( 'reference' ) || $this->reference_count( 'company_profile' ) <= 0 || $this->reference_count( 'category_map' ) <= 0 ) { return new WP_Error( 'reference_not_ready', 'Справочные данные ещё не готовы.' ); } return true; }
	private function count_table( string $table ): int { global $wpdb; $name = Promokodiki_Admitad_Schema::table( $table ); return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" ); }
	private function reference_count( string $table ): int { global $wpdb; $name = Promokodiki_Admitad_Schema::table( $table ); if ( 'category_map' === $table ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$name} WHERE source_namespace = %s", 'coupon' ) ); } return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$name}" ); }
}
