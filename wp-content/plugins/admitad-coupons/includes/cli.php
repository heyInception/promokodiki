<?php
/** WP-CLI commands for safe data migration and imports. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Promokodiki_Admitad_CLI {
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
}

WP_CLI::add_command( 'admitad', 'Promokodiki_Admitad_CLI' );

