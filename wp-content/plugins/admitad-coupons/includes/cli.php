<?php
/** WP-CLI commands for safe data migration and imports. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Promokodiki_Admitad_CLI {
	/**
	 * Register a fresh database backup for recovery operations.
	 *
	 * ## OPTIONS
	 *
	 * --path=<absolute-path>
	 * : Existing non-empty database backup file.
	 */
	public function backup_register( array $args, array $assoc_args ): void {
		unset( $args );
		try {
			$state = ( new Promokodiki_Admitad_Backup_Gate() )->register( (string) ( $assoc_args['path'] ?? '' ) );
		} catch ( Throwable $error ) {
			WP_CLI::error( 'The backup could not be registered.' );
		}
		WP_CLI::success( sprintf( 'Recovery backup registered (%d bytes, %s).', $state['size'], $state['sha256'] ) );
	}

	/**
	 * Analyze or execute the non-destructive mapping migration.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Analyze without copying data. This is the default.
	 *
	 * [--execute]
	 * : Copy legacy mappings into the new repositories.
	 *
	 * [--yes]
	 * : Confirm execution.
	 *
	 * [--backup=<path>]
	 * : Existing non-empty database backup required with --execute.
	 *
	 * @param array<int,string>    $args       Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 */
	public function automation_migrate( array $args, array $assoc_args ): void {
		unset( $args );
		$migration = new Promokodiki_Admitad_Legacy_Migration();
		$analysis  = $migration->analyze();
		if ( empty( $assoc_args['execute'] ) ) {
			WP_CLI::log( wp_json_encode( $analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			WP_CLI::success( 'Mapping migration dry-run complete; legacy data was not changed.' );
			return;
		}
		if ( empty( $assoc_args['yes'] ) ) {
			WP_CLI::error( '--yes is required.' );
		}
		$backup = (string) ( $assoc_args['backup'] ?? '' );
		if ( ! is_file( $backup ) || 0 === filesize( $backup ) ) {
			WP_CLI::error( 'A non-empty existing --backup file is required.' );
		}
		$offset = 0;
		do {
			$batch  = $migration->migrate_batch( $offset, 200 );
			$offset = $batch['next_offset'];
		} while ( ! $batch['complete'] );
		$report = $migration->verify();
		update_option(
			'promokodiki_admitad_legacy_migration_report',
			array(
				'backup'       => wp_normalize_path( $backup ),
				'completed_at' => gmdate( 'Y-m-d H:i:s' ),
				'analysis'     => $analysis,
				'verification' => $report,
			),
			false
		);
		WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		WP_CLI::success( 'Mapping migration complete; legacy tables were preserved.' );
	}

	/**
	 * Analyze or execute the one-CPT migration.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Analyze without modifying data. This is the default.
	 *
	 * [--execute]
	 * : Execute the destructive migration.
	 *
	 * [--yes]
	 * : Confirm permanent deletion of duplicates.
	 *
	 * [--backup=<path>]
	 * : Existing database backup required with --execute.
	 */
	public function migrate( $args, $assoc_args ) {
		$execute = isset( $assoc_args['execute'] );
		if ( ! $execute ) {
			WP_CLI::log( wp_json_encode( admitad_migration_analyze(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			WP_CLI::success( 'Dry-run complete; no data changed.' );
			return;
		}
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::error( '--yes is required because duplicate posts are deleted permanently.' );
		}
		$report = admitad_migration_execute( $assoc_args['backup'] ?? '' );
		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}
		WP_CLI::log( wp_json_encode( $report['verification'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		if ( $report['errors'] ) {
			WP_CLI::error( 'Migration completed with errors. Inspect admitad_last_migration_report.' );
		}
		WP_CLI::success( 'Migration complete.' );
	}

	/** Run the unified streaming import. */
	public function import() {
		$coordinator = new Promokodiki_Admitad_Sync_Coordinator(
			null,
			null,
			null,
			null,
			static fn(): bool => true
		);
		$run_id      = $coordinator->start_coupon_sync();
		if ( is_wp_error( $run_id ) ) {
			WP_CLI::error( $run_id->get_error_message() );
		}
		$offset = 0;
		do {
			$result = $coordinator->run_coupon_batch( $run_id, $offset );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			$offset = $result['next_offset'];
		} while ( ! $result['complete'] );

		WP_CLI::log( wp_json_encode( $result['counters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		WP_CLI::success( 'Import complete.' );
	}

	/**
	 * Preview or apply deterministic coupon classification.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Store and print an affected-only preview without changing taxonomy.
	 *
	 * [--apply=<snapshot-id>]
	 * : Apply a previously stored preview.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function classify( array $args, array $assoc_args ): void {
		unset( $args );
		$service = new Promokodiki_Admitad_Reclassification_Service();
		if ( ! empty( $assoc_args['apply'] ) ) {
			$count = $service->apply_preview( sanitize_text_field( (string) $assoc_args['apply'] ) );
			WP_CLI::success( sprintf( 'Applied classification to %d coupon(s).', $count ) );
			return;
		}

		$post_ids = get_posts(
			array(
				'post_type'                     => 'promocode',
				'post_status'                   => array( 'publish', 'future', 'draft', 'private' ),
				'posts_per_page'                => -1,
				'fields'                        => 'ids',
				'no_found_rows'                 => true,
				'promokodiki_include_inactive' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A dry-run intentionally scans only imported coupons.
				'meta_key'                      => 'admitad_coupon_id',
			)
		);
		$preview  = $service->preview( array_map( 'intval', $post_ids ) );
		WP_CLI::log( wp_json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		WP_CLI::success( 'Classification dry-run complete; taxonomy was not changed.' );
	}

	/**
	 * Roll back a previously applied classification preview.
	 *
	 * ## OPTIONS
	 *
	 * <snapshot-id>
	 * : Stored preview UUID.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function rollback( array $args ): void {
		$snapshot_id = sanitize_text_field( (string) ( $args[0] ?? '' ) );
		if ( '' === $snapshot_id ) {
			WP_CLI::error( 'A snapshot ID is required.' );
		}
		$count = ( new Promokodiki_Admitad_Reclassification_Service() )->rollback( $snapshot_id );
		WP_CLI::success( sprintf( 'Rolled back classification for %d coupon(s).', $count ) );
	}
}

WP_CLI::add_command( 'admitad', 'Promokodiki_Admitad_CLI' );

