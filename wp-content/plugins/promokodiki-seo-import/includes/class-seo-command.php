<?php
/** WP-CLI SEO import with backup and rollback. */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_SEO_Command {
	private const PAGE_ROWS = array( 'главная', 'новогодние промокоды', 'приложения' );

	/** Preview the exact-name import without writes. */
	public function dry_run(): void { $this->report( false, array() ); }

	/** Apply matched rows after a full database backup. */
	public function apply( array $args, array $assoc_args ): void {
		$backup_file = isset( $assoc_args['backup-file'] ) ? (string) $assoc_args['backup-file'] : $this->create_database_backup();
		if ( ! is_file( $backup_file ) || 0 === filesize( $backup_file ) ) {
			WP_CLI::error( 'A non-empty full database backup is required.' );
		}
		$this->report( true, array( 'database_backup' => wp_normalize_path( $backup_file ) ) );
		$this->refresh_yoast_and_cache();
	}

	/** Restore one generated JSON field backup. */
	public function rollback( array $args, array $assoc_args ): void {
		$file = (string) ( $assoc_args['file'] ?? '' );
		$data = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( ! is_array( $data ) || empty( $data['objects'] ) ) { WP_CLI::error( 'Valid SEO backup file is required.' ); }
		foreach ( $data['objects'] as $item ) {
			if ( 'post' === $item['type'] ) {
				wp_update_post( array( 'ID' => (int) $item['id'], 'post_title' => $item['name'] ) );
				update_post_meta( (int) $item['id'], '_yoast_wpseo_title', $item['title'] );
				update_post_meta( (int) $item['id'], '_yoast_wpseo_metadesc', $item['description'] );
			} else {
				wp_update_term( (int) $item['id'], 'promocode_category', array( 'name' => $item['name'] ) );
				$this->write_term_seo( (int) $item['id'], (string) $item['title'], (string) $item['description'] );
			}
		}
		if ( isset( $data['yoast_titles'] ) ) { update_option( 'wpseo_titles', $data['yoast_titles'], false ); }
		$this->refresh_yoast_and_cache();
		WP_CLI::success( 'SEO values restored.' );
	}

	private function report( bool $apply, array $context ): void {
		$rows = Promokodiki_SEO_Dataset::load( PROMOKODIKI_SEO_IMPORT_DIR . 'data/seo-pages.csv' );
		$report = array( 'matched' => 0, 'missing' => array(), 'stale' => array(), 'changes' => array() );
		$operations = array();
		foreach ( $rows as $row ) {
			if ( Promokodiki_SEO_Dataset::is_stale_seasonal( implode( ' ', $row ), (int) gmdate( 'Y' ) ) ) { $report['stale'][] = $row['name']; continue; }
			$object = $this->find_object( $row['name'] );
			if ( ! $object ) { $report['missing'][] = $row['name']; continue; }
			++$report['matched'];
			$report['changes'][] = array( 'type' => $object['type'], 'id' => $object['id'], 'from' => $object['name'], 'to' => $row['h1'] );
			$operations[] = array( 'object' => $object, 'row' => $row );
		}
		$telegram = get_term_by( 'slug', 'promokody-iz-telegram', 'promocode_category' );
		if ( $telegram instanceof WP_Term ) {
			$seo = $this->read_term_seo( $telegram->term_id );
			$object = array( 'type' => 'term', 'id' => $telegram->term_id, 'name' => $telegram->name, 'title' => $seo['title'], 'description' => $seo['description'] );
			$operations[] = array( 'object' => $object, 'row' => array( 'h1' => $telegram->name, 'title' => 'Промокоды из Telegram | Promokodiki', 'description' => 'Свежие промокоды и скидки из публичных Telegram-каналов, проверенные и собранные на Promokodiki.' ) );
			$report['telegram_template'] = 'explicit';
		}
		if ( $apply ) {
			$backup = $this->write_field_backup( array_column( $operations, 'object' ), $context );
			foreach ( $operations as $operation ) { $this->write_object( $operation['object'], $operation['row'] ); }
			$this->set_yoast_templates();
			$report['field_backup'] = $backup;
		}
		WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		if ( $apply ) { WP_CLI::success( 'SEO import completed.' ); }
	}

	private function find_object( string $name ): ?array {
		$normalized = Promokodiki_SEO_Dataset::normalize_name( $name );
		if ( in_array( $normalized, self::PAGE_ROWS, true ) ) {
			$post = 'главная' === $normalized ? get_post( (int) get_option( 'page_on_front' ) ) : get_page_by_title( $name, OBJECT, 'page' );
			return $post ? array( 'type' => 'post', 'id' => $post->ID, 'name' => $post->post_title, 'title' => get_post_meta( $post->ID, '_yoast_wpseo_title', true ), 'description' => get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ) : null;
		}
		foreach ( get_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false ) ) as $term ) {
			if ( $normalized === Promokodiki_SEO_Dataset::normalize_name( $term->name ) ) {
				$seo = $this->read_term_seo( $term->term_id );
				return array( 'type' => 'term', 'id' => $term->term_id, 'name' => $term->name, 'title' => $seo['title'], 'description' => $seo['description'] );
			}
		}
		return null;
	}

	private function write_object( array $object, array $row ): void {
		if ( 'post' === $object['type'] ) {
			wp_update_post( array( 'ID' => $object['id'], 'post_title' => $row['h1'] ) );
			update_post_meta( $object['id'], '_yoast_wpseo_title', $row['title'] );
			update_post_meta( $object['id'], '_yoast_wpseo_metadesc', $row['description'] );
		} else {
			wp_update_term( $object['id'], 'promocode_category', array( 'name' => $row['h1'] ) );
			$this->write_term_seo( (int) $object['id'], (string) $row['title'], (string) $row['description'] );
		}
	}

	/** Read the per-term fields from Yoast's canonical taxonomy option. */
	private function read_term_seo( int $term_id ): array {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			return array(
				'title'       => (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, 'promocode_category', 'title' ),
				'description' => (string) WPSEO_Taxonomy_Meta::get_term_meta( $term_id, 'promocode_category', 'desc' ),
			);
		}
		$meta = get_option( 'wpseo_taxonomy_meta', array() );
		$term = $meta['promocode_category'][ $term_id ] ?? array();
		return array(
			'title'       => (string) ( $term['wpseo_title'] ?? '' ),
			'description' => (string) ( $term['wpseo_desc'] ?? '' ),
		);
	}

	/** Preserve other Yoast fields while updating an individual category. */
	private function write_term_seo( int $term_id, string $title, string $description ): void {
		if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$term_meta = WPSEO_Taxonomy_Meta::get_term_meta( $term_id, 'promocode_category' );
			$term_meta = is_array( $term_meta ) ? $term_meta : array();
			$term_meta['wpseo_title'] = $title;
			$term_meta['wpseo_desc']  = $description;
			WPSEO_Taxonomy_Meta::set_values( $term_id, 'promocode_category', $term_meta );
			return;
		}
		$meta = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! isset( $meta['promocode_category'] ) || ! is_array( $meta['promocode_category'] ) ) {
			$meta['promocode_category'] = array();
		}
		$term = isset( $meta['promocode_category'][ $term_id ] ) && is_array( $meta['promocode_category'][ $term_id ] )
			? $meta['promocode_category'][ $term_id ]
			: array();
		$term['wpseo_title'] = $title;
		$term['wpseo_desc']  = $description;
		$meta['promocode_category'][ $term_id ] = $term;
		update_option( 'wpseo_taxonomy_meta', $meta, false );
	}

	private function create_database_backup(): string {
		$uploads = wp_upload_dir();
		$dir = trailingslashit( $uploads['basedir'] ) . 'promokodiki-seo-backups';
		wp_mkdir_p( $dir );
		$file = $dir . '/database-' . gmdate( 'Ymd-His' ) . '.sql';
		WP_CLI::runcommand( 'db export ' . escapeshellarg( $file ), array( 'exit_error' => false ) );
		return $file;
	}

	private function write_field_backup( array $objects, array $context ): string {
		$uploads = wp_upload_dir(); $dir = trailingslashit( $uploads['basedir'] ) . 'promokodiki-seo-backups'; wp_mkdir_p( $dir );
		$file = $dir . '/seo-fields-' . gmdate( 'Ymd-His' ) . '.json';
		$written = file_put_contents( $file, wp_json_encode( array_merge( $context, array( 'created_at' => gmdate( DATE_ATOM ), 'yoast_titles' => get_option( 'wpseo_titles', array() ), 'objects' => $objects ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		if ( false === $written || 0 === $written ) { WP_CLI::error( 'Unable to write the SEO field backup.' ); }
		$files = glob( $dir . '/seo-fields-*.json' ) ?: array(); rsort( $files ); foreach ( array_slice( $files, 5 ) as $old ) { unlink( $old ); }
		return $file;
	}

	private function set_yoast_templates(): void {
		$option = get_option( 'wpseo_titles', array() );
		$option['title-tax-promocode_category'] = 'Промокоды %%term_title%% — %%currentmonth%% %%currentyear%% | Promokodiki';
		$option['metadesc-tax-promocode_category'] = 'Актуальные промокоды, купоны и скидки в категории «%%term_title%%». Выбирайте действующие предложения интернет-магазинов на Promokodiki.';
		$option['title-tax-shops_category'] = 'Промокоды %%term_title%% — %%currentmonth%% %%currentyear%% | Promokodiki';
		$option['metadesc-tax-shops_category'] = 'Актуальные промокоды, купоны и скидки магазина %%term_title%%. Проверяйте условия и сроки предложений на Promokodiki.';
		$option['title-promocode'] = '%%title%% | Promokodiki'; $option['metadesc-promocode'] = '%%excerpt%%';
		update_option( 'wpseo_titles', $option, false );
	}

	private function refresh_yoast_and_cache(): void {
		WP_CLI::runcommand( 'yoast index --reindex', array( 'exit_error' => false ) );
		if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
	}
}
