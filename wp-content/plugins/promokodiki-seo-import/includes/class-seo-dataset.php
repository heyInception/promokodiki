<?php
/** SEO CSV parsing and normalization. */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_SEO_Dataset {
	public static function normalize_name( string $name ): string {
		$name = preg_replace( '/\s+/u', ' ', trim( $name ) );
		return mb_strtolower( (string) $name, 'UTF-8' );
	}

	public static function expand_placeholders( string $value ): string {
		return str_replace(
			array( '[текущий месяц/год]', '[Название сайта]' ),
			array( '%%currentmonth%% %%currentyear%%', 'Promokodiki' ),
			$value
		);
	}

	public static function is_stale_seasonal( string $value, int $current_year ): bool {
		if ( str_contains( $value, '%%currentyear%%' ) ) {
			return false;
		}
		preg_match_all( '/\b(20\d{2})\b/', $value, $matches );
		return ! empty( $matches[1] ) && max( array_map( 'intval', $matches[1] ) ) <= $current_year;
	}

	/** @return array<int, array{name:string,title:string,h1:string,description:string,row:int}> */
	public static function load( string $path ): array {
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'SEO CSV cannot be opened.' );
		}
		$header = fgetcsv( $handle );
		$rows = array();
		$line = 1;
		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			++$line;
			if ( count( $data ) < 4 || '' === trim( (string) $data[0] ) ) { continue; }
			$rows[] = array(
				'name' => sanitize_text_field( $data[0] ),
				'title' => self::expand_placeholders( sanitize_text_field( $data[1] ?? '' ) ),
				'h1' => sanitize_text_field( $data[2] ?? '' ),
				'description' => self::expand_placeholders( sanitize_text_field( $data[3] ?? '' ) ),
				'row' => $line,
			);
		}
		fclose( $handle );
		return $rows;
	}
}
