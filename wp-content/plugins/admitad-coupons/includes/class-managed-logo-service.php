<?php
/**
 * Managed Admitad campaign-logo lifecycle.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports, deduplicates, and safely cleans plugin-owned campaign logos.
 */
final class Promokodiki_Admitad_Managed_Logo_Service {
	private const MAX_BYTES = 2097152;

	/** @var callable */
	private $downloader;

	/** @var callable|null */
	private $importer;

	/**
	 * @param callable|null $downloader Optional URL downloader.
	 * @param callable|null $importer   Optional verified-file importer.
	 */
	public function __construct( ?callable $downloader = null, ?callable $importer = null ) {
		$this->downloader = $downloader ?? array( $this, 'bounded_download' );
		$this->importer   = $importer;
	}

	/**
	 * Describe current work without downloading files.
	 *
	 * @return array{linked:int,download:int,reuse:int,unlinked:int,unsupported:int}
	 */
	public function preview(): array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Snapshot table is authoritative for preview.
		$rows = $wpdb->get_results( "SELECT campaign_id, image_url FROM {$table} WHERE image_url <> ''", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated table name.
		$out  = array( 'linked' => 0, 'download' => 0, 'reuse' => 0, 'unlinked' => 0, 'unsupported' => 0 );
		foreach ( $rows as $row ) {
			$term_id = $this->term_id( (int) $row['campaign_id'] );
			if ( ! $term_id ) {
				++$out['unlinked'];
				continue;
			}
			++$out['linked'];
			$source_url = (string) get_term_meta( $term_id, '_admitad_shop_logo_source_url', true );
			if ( $source_url === (string) $row['image_url'] && (int) get_term_meta( $term_id, '_admitad_shop_logo_id', true ) > 0 ) {
				++$out['reuse'];
			} else {
				++$out['download'];
			}
		}
		return $out;
	}

	/**
	 * Download or reuse one campaign logo.
	 *
	 * @param int $campaign_id Admitad campaign ID.
	 * @return array{state:string,attachment_id:int}
	 */
	public function process_campaign( int $campaign_id ): array {
		$profile = ( new Promokodiki_Admitad_Reference_Repository() )->campaign( $campaign_id );
		$term_id = $this->term_id( $campaign_id );
		$url     = esc_url_raw( (string) ( $profile['image_url'] ?? '' ) );
		if ( ! $term_id || '' === $url ) {
			return array( 'state' => 'unlinked', 'attachment_id' => 0 );
		}
		$current_id  = (int) get_term_meta( $term_id, '_admitad_shop_logo_id', true );
		$current_url = (string) get_term_meta( $term_id, '_admitad_shop_logo_source_url', true );
		if ( $current_id > 0 && $current_url === $url && get_post( $current_id ) instanceof WP_Post ) {
			return array( 'state' => 'unchanged', 'attachment_id' => $current_id );
		}

		$temp_file = call_user_func( $this->downloader, $url );
		if ( is_wp_error( $temp_file ) ) {
			return array( 'state' => 'failed', 'attachment_id' => $current_id );
		}
		$temp_file = (string) $temp_file;
		try {
			$validated = $this->validate( $temp_file, $url );
			if ( is_wp_error( $validated ) ) {
				return array( 'state' => 'unsupported', 'attachment_id' => $current_id );
			}
			$hash          = hash_file( 'sha256', $temp_file );
			$attachment_id = $this->find_hash( $hash );
			$state         = 'reused';
			if ( ! $attachment_id ) {
				$attachment_id = $this->import( $temp_file, $validated['filename'], $validated['type'] );
				if ( is_wp_error( $attachment_id ) || (int) $attachment_id <= 0 ) {
					return array( 'state' => 'failed', 'attachment_id' => $current_id );
				}
				$attachment_id = (int) $attachment_id;
				$state         = 'downloaded';
				update_post_meta( $attachment_id, '_admitad_managed_logo', 'yes' );
				update_post_meta( $attachment_id, '_admitad_logo_hash', $hash );
				update_post_meta( $attachment_id, '_admitad_logo_source_url', $url );
			}

			$this->attach_campaign( $attachment_id, $campaign_id );
			update_term_meta( $term_id, '_admitad_shop_logo_id', $attachment_id );
			update_term_meta( $term_id, '_admitad_shop_logo_source_url', $url );
			if ( $current_id > 0 && $current_id !== $attachment_id ) {
				$this->detach_campaign( $current_id, $campaign_id );
			}
			return array( 'state' => $state, 'attachment_id' => $attachment_id );
		} finally {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
		}
	}

	/**
	 * Return owned attachments with no current shop reference.
	 *
	 * @return array{attachment_ids:array<int,int>,bytes:int}
	 */
	public function cleanup_preview(): array {
		$orphans = array();
		$bytes   = 0;
		$page    = 1;
		do {
			$batch = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'fields'         => 'ids',
					'posts_per_page' => 500,
					'paged'          => $page,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'meta_key'       => '_admitad_managed_logo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ownership lookup is paginated.
					'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Ownership lookup is paginated.
				)
			);
			foreach ( $batch as $id ) {
				if ( $this->is_referenced( (int) $id ) ) {
					continue;
				}
				$orphans[] = (int) $id;
				$file      = get_attached_file( (int) $id );
				$bytes    += is_string( $file ) && file_exists( $file ) ? (int) filesize( $file ) : 0;
				if ( 500 === count( $orphans ) ) {
					break 2;
				}
			}
			++$page;
		} while ( 500 === count( $batch ) );
		sort( $orphans, SORT_NUMERIC );
		return array( 'attachment_ids' => $orphans, 'bytes' => $bytes );
	}

	/**
	 * Stream a remote file and stop after the configured byte ceiling.
	 *
	 * @return string|WP_Error
	 */
	private function bounded_download( string $url ) {
		$temp_file = wp_tempnam( $url );
		if ( ! $temp_file ) {
			return new WP_Error( 'logo_temp_file', 'Could not create a temporary logo file.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 30,
				'redirection'         => 3,
				'stream'              => true,
				'filename'            => $temp_file,
				'limit_response_size' => self::MAX_BYTES + 1,
			)
		);
		$status   = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$size     = is_file( $temp_file ) ? (int) filesize( $temp_file ) : 0;
		if ( is_wp_error( $response ) || $status < 200 || $status >= 300 || $size <= 0 || $size > self::MAX_BYTES ) {
			wp_delete_file( $temp_file );
			return is_wp_error( $response ) ? $response : new WP_Error( 'logo_download', 'Logo download failed or exceeded the size limit.' );
		}

		return $temp_file;
	}

	/**
	 * Delete only still-unreferenced, plugin-owned attachments.
	 *
	 * @param array<int, int> $attachment_ids Previewed attachment IDs.
	 * @return array{deleted:int,skipped:int}
	 */
	public function cleanup( array $attachment_ids ): array {
		$out = array( 'deleted' => 0, 'skipped' => 0 );
		foreach ( array_values( array_unique( array_map( 'absint', $attachment_ids ) ) ) as $id ) {
			if ( $id <= 0 || 'yes' !== get_post_meta( $id, '_admitad_managed_logo', true ) || $this->is_referenced( $id ) ) {
				++$out['skipped'];
				continue;
			}
			if ( wp_delete_attachment( $id, true ) ) {
				++$out['deleted'];
			} else {
				++$out['skipped'];
			}
		}
		return $out;
	}

	/** @return array{filename:string,type:string}|WP_Error */
	private function validate( string $file, string $url ) {
		if ( ! is_file( $file ) || filesize( $file ) <= 0 || filesize( $file ) > self::MAX_BYTES ) {
			return new WP_Error( 'logo_size', 'Logo file size is invalid.' );
		}
		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = sanitize_file_name( wp_basename( $path ) );
		if ( '' === $filename ) {
			$filename = 'admitad-logo';
		}
		$check = wp_check_filetype_and_ext( $file, $filename, array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' ) );
		if ( empty( $check['type'] ) || empty( $check['ext'] ) || ! in_array( $check['type'], array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
			return new WP_Error( 'logo_type', 'Logo file type is unsupported.' );
		}
		return array( 'filename' => preg_replace( '/\.[^.]+$/', '', $filename ) . '.' . $check['ext'], 'type' => $check['type'] );
	}

	/** @return int|WP_Error */
	private function import( string $file, string $filename, string $type ) {
		if ( is_callable( $this->importer ) ) {
			return call_user_func( $this->importer, $file, $filename, $type );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$copy = wp_tempnam( $filename );
		if ( ! copy( $file, $copy ) ) {
			return new WP_Error( 'logo_copy', 'Could not prepare the logo upload.' );
		}
		$result = media_handle_sideload( array( 'name' => $filename, 'type' => $type, 'tmp_name' => $copy, 'error' => 0, 'size' => filesize( $copy ) ), 0, 'Admitad campaign logo' );
		if ( is_wp_error( $result ) && file_exists( $copy ) ) {
			wp_delete_file( $copy );
		}
		return $result;
	}

	private function term_id( int $campaign_id ): int {
		$ids = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'fields' => 'ids', 'number' => 2, 'meta_query' => array( array( 'key' => 'admitad_campaign_id', 'value' => (string) $campaign_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Stable campaign ID is the canonical link.
		return ! is_wp_error( $ids ) && 1 === count( $ids ) ? (int) $ids[0] : 0;
	}

	private function find_hash( string $hash ): int {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => '_admitad_managed_logo', 'value' => 'yes' ), array( 'key' => '_admitad_logo_hash', 'value' => $hash ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Owned hash lookup enables deduplication.
		return $ids ? (int) $ids[0] : 0;
	}

	private function attach_campaign( int $attachment_id, int $campaign_id ): void {
		$ids   = array_map( 'intval', (array) get_post_meta( $attachment_id, '_admitad_campaign_ids', true ) );
		$ids[] = $campaign_id;
		$ids   = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids, SORT_NUMERIC );
		update_post_meta( $attachment_id, '_admitad_campaign_ids', $ids );
	}

	private function detach_campaign( int $attachment_id, int $campaign_id ): void {
		$ids = array_values( array_diff( array_map( 'intval', (array) get_post_meta( $attachment_id, '_admitad_campaign_ids', true ) ), array( $campaign_id ) ) );
		update_post_meta( $attachment_id, '_admitad_campaign_ids', $ids );
	}

	private function is_referenced( int $attachment_id ): bool {
		$terms = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'fields' => 'ids', 'number' => 1, 'meta_query' => array( array( 'key' => '_admitad_shop_logo_id', 'value' => (string) $attachment_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cleanup safety rechecks current references.
		return ! is_wp_error( $terms ) && ! empty( $terms );
	}
}
