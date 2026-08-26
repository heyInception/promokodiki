<?php
/**
 * Verified backup prerequisite for destructive recovery operations.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ensures that recovery work has a fresh, unchanged database backup. */
final class Promokodiki_Admitad_Backup_Gate {
	private const OPTION_NAME = 'promokodiki_admitad_recovery_backup';

	/** @var string */
	private string $option_name;

	public function __construct( string $option_name = self::OPTION_NAME ) {
		$this->option_name = sanitize_key( $option_name );
		if ( '' === $this->option_name ) {
			throw new InvalidArgumentException( 'A backup state option name is required.' );
		}
	}

	/**
	 * Register one normalized, existing, non-empty backup.
	 *
	 * @return array{created_at:int,size:int,sha256:string}
	 */
	public function register( string $path ): array {
		$path = $this->normalized_existing_path( $path );
		$size = filesize( $path );
		if ( false === $size || $size <= 0 ) {
			throw new InvalidArgumentException( 'The backup file must be non-empty.' );
		}
		$sha256 = hash_file( 'sha256', $path );
		if ( false === $sha256 ) {
			throw new RuntimeException( 'The backup file could not be verified.' );
		}
		$state = array(
			'path'       => $path,
			'created_at' => time(),
			'size'       => (int) $size,
			'sha256'     => $sha256,
		);
		update_option( $this->option_name, $state, false );
		return array_intersect_key( $state, array_flip( array( 'created_at', 'size', 'sha256' ) ) );
	}

	/** Return true only while the registered backup is fresh and byte-identical. */
	public function verify() {
		$state = get_option( $this->option_name, array() );
		if ( ! is_array( $state ) || empty( $state['path'] ) || empty( $state['created_at'] ) || ! isset( $state['size'], $state['sha256'] ) ) {
			return new WP_Error( 'backup_missing', 'Резервная копия не зарегистрирована.' );
		}
		$path = (string) $state['path'];
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'backup_missing', 'Резервная копия недоступна.' );
		}
		$size = filesize( $path );
		if ( false === $size || 0 === $size ) {
			return new WP_Error( 'backup_empty', 'Резервная копия пуста.' );
		}
		$hash = hash_file( 'sha256', $path );
		if ( (int) $size !== (int) $state['size'] || false === $hash || ! hash_equals( (string) $state['sha256'], $hash ) ) {
			return new WP_Error( 'backup_changed', 'Резервная копия изменилась.' );
		}
		if ( time() - (int) $state['created_at'] > DAY_IN_SECONDS ) {
			return new WP_Error( 'backup_expired', 'Срок действия резервной копии истёк.' );
		}
		return true;
	}

	/** @throws InvalidArgumentException When the supplied path cannot be registered. */
	private function normalized_existing_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path || ! path_is_absolute( $path ) ) {
			throw new InvalidArgumentException( 'An absolute backup path is required.' );
		}
		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) ) {
			throw new InvalidArgumentException( 'The backup file is unavailable.' );
		}
		return wp_normalize_path( $real );
	}
}
