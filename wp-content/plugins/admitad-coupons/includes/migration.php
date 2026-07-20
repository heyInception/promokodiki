<?php
/** One-time migration from duplicated shops/promocode records to one canonical record. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function admitad_migration_register_legacy_types() {
	if ( ! post_type_exists( 'shops' ) ) {
		register_post_type( 'shops', array( 'public' => false ) );
	}
	if ( ! taxonomy_exists( 'shop_coupons' ) ) {
		register_taxonomy( 'shop_coupons', array( 'promocode', 'shops' ), array( 'public' => false ) );
	}
}

function admitad_migration_groups() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT p.ID, p.post_type, p.post_status, pm.meta_value AS external_id
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'admitad_coupon_id'
		WHERE p.post_type IN ('promocode', 'shops')
		ORDER BY CAST(p.ID AS UNSIGNED) ASC"
	);
	$groups = array();
	foreach ( $rows as $row ) {
		$key = (string) $row->external_id;
		if ( '' !== $key ) {
			$groups[ $key ][] = $row;
		}
	}
	return $groups;
}

function admitad_migration_select_canonical( array $rows ) {
	usort(
		$rows,
		static function ( $a, $b ) {
			$rank = static function ( $row ) {
				if ( 'promocode' === $row->post_type && 'publish' === $row->post_status ) {
					return 0;
				}
				return 'promocode' === $row->post_type ? 1 : 2;
			};
			return $rank( $a ) === $rank( $b ) ? (int) $a->ID <=> (int) $b->ID : $rank( $a ) <=> $rank( $b );
		}
	);
	return $rows[0];
}

function admitad_migration_analyze() {
	admitad_migration_register_legacy_types();
	$groups      = admitad_migration_groups();
	$source_rows = 0;
	$shop_rows   = 0;
	$duplicates  = 0;
	foreach ( $groups as $rows ) {
		$source_rows += count( $rows );
		$duplicates  += max( 0, count( $rows ) - 1 );
		foreach ( $rows as $row ) {
			$shop_rows += 'shops' === $row->post_type ? 1 : 0;
		}
	}
	return array(
		'unique_external_ids' => count( $groups ),
		'source_rows'         => $source_rows,
		'duplicate_rows'      => $duplicates,
		'shop_rows'           => $shop_rows,
		'expected_final_rows' => count( $groups ),
	);
}

function admitad_migration_copy_term_meta( $source_term_id, $target_term_id ) {
	foreach ( get_term_meta( $source_term_id ) as $key => $values ) {
		if ( ! metadata_exists( 'term', $target_term_id, $key ) && isset( $values[0] ) ) {
			update_term_meta( $target_term_id, $key, maybe_unserialize( $values[0] ) );
		}
	}
}

function admitad_migration_shop_term_from_legacy( WP_Term $legacy ) {
	$campaign_id = (string) get_term_meta( $legacy->term_id, 'admitad_campaign_id', true );
	$target_id   = 0;
	if ( $campaign_id ) {
		$ids = get_terms(
			array(
				'taxonomy'   => 'shops_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_query' => array( array( 'key' => 'admitad_campaign_id', 'value' => $campaign_id ) ),
			)
		);
		if ( ! is_wp_error( $ids ) && $ids ) {
			$target_id = (int) $ids[0];
		}
	}
	if ( ! $target_id ) {
		$target = get_term_by( 'name', $legacy->name, 'shops_category' );
		if ( $target ) {
			$target_id = (int) $target->term_id;
		} else {
			$created = wp_insert_term( $legacy->name, 'shops_category', array( 'description' => $legacy->description ) );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$target_id = (int) $created['term_id'];
		}
	}
	admitad_migration_copy_term_meta( $legacy->term_id, $target_id );
	return $target_id;
}

function admitad_migration_merge_meta( $source_id, $target_id ) {
	$all_meta = get_post_meta( $source_id );
	foreach ( $all_meta as $key => $values ) {
		if ( str_starts_with( $key, '_shops_' ) ) {
			$key = '_promocode_' . substr( $key, 7 );
		}
		if ( ! metadata_exists( 'post', $target_id, $key ) && isset( $values[0] ) && '' !== $values[0] ) {
			update_post_meta( $target_id, $key, maybe_unserialize( $values[0] ) );
		}
	}

	foreach ( array( 'used_count', 'likes', 'dislikes' ) as $suffix ) {
		$target_value = (int) get_post_meta( $target_id, '_promocode_' . $suffix, true );
		$source_value = max(
			(int) get_post_meta( $source_id, '_promocode_' . $suffix, true ),
			(int) get_post_meta( $source_id, '_shops_' . $suffix, true )
		);
		update_post_meta( $target_id, '_promocode_' . $suffix, max( $target_value, $source_value ) );
	}

	foreach ( array( 'liked_ips', 'disliked_ips' ) as $suffix ) {
		$values = array_merge(
			(array) get_post_meta( $target_id, '_promocode_' . $suffix, true ),
			(array) get_post_meta( $source_id, '_promocode_' . $suffix, true ),
			(array) get_post_meta( $source_id, '_shops_' . $suffix, true )
		);
		update_post_meta( $target_id, '_promocode_' . $suffix, array_values( array_unique( array_filter( $values ) ) ) );
	}
}

function admitad_migration_merge_terms( $source_id, $target_id ) {
	foreach ( array( 'promocode_category', 'promocode_tag', 'shops_category' ) as $taxonomy ) {
		$ids = wp_get_object_terms( $source_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $ids ) && $ids ) {
			wp_set_post_terms( $target_id, array_map( 'intval', $ids ), $taxonomy, true );
		}
	}
	$legacy_terms = wp_get_object_terms( $source_id, 'shop_coupons' );
	if ( ! is_wp_error( $legacy_terms ) ) {
		foreach ( $legacy_terms as $legacy ) {
			$target_term_id = admitad_migration_shop_term_from_legacy( $legacy );
			if ( $target_term_id ) {
				wp_set_post_terms( $target_id, array( $target_term_id ), 'shops_category', true );
			}
		}
	}
}

function admitad_migration_assign_campaign_shop( $post_id ) {
	$campaign = array(
		'id'   => get_post_meta( $post_id, 'campaign_id', true ),
		'name' => get_post_meta( $post_id, 'campaign_name', true ),
	);
	$term_id = admitad_find_or_create_shop( $campaign, get_post_meta( $post_id, 'image_url', true ) );
	if ( $term_id ) {
		wp_set_post_terms( $post_id, array( $term_id ), 'shops_category', true );
	}
}

function admitad_migration_ensure_shops_page() {
	$page = get_page_by_path( 'shops' );
	if ( ! $page ) {
		$page_id = wp_insert_post(
			array( 'post_title' => 'Магазины', 'post_name' => 'shops', 'post_type' => 'page', 'post_status' => 'publish' ),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}
	} else {
		$page_id = $page->ID;
	}
	update_post_meta( $page_id, '_wp_page_template', 'page-shops.php' );
	return (int) $page_id;
}

function admitad_migration_execute( $backup_path ) {
	if ( ! is_string( $backup_path ) || ! is_file( $backup_path ) || filesize( $backup_path ) < 1024 ) {
		return new WP_Error( 'backup_required', 'A non-empty backup file is required before migration.' );
	}

	admitad_migration_register_legacy_types();
	$groups = admitad_migration_groups();
	$report = array(
		'started_at' => current_time( 'mysql' ),
		'backup'     => wp_normalize_path( $backup_path ),
		'mappings'   => array(),
		'deleted'    => array(),
		'errors'     => array(),
	);

	wp_defer_term_counting( true );
	wp_defer_comment_counting( true );
	try {
		foreach ( $groups as $external_id => $rows ) {
			$canonical    = admitad_migration_select_canonical( $rows );
			$canonical_id = (int) $canonical->ID;
			if ( 'promocode' !== $canonical->post_type ) {
				$result = wp_update_post( array( 'ID' => $canonical_id, 'post_type' => 'promocode' ), true );
				if ( is_wp_error( $result ) ) {
					$report['errors'][] = array( 'external_id' => $external_id, 'message' => $result->get_error_message() );
					continue;
				}
			}

			admitad_migration_merge_meta( $canonical_id, $canonical_id );
			admitad_migration_merge_terms( $canonical_id, $canonical_id );
			foreach ( $rows as $row ) {
				$source_id = (int) $row->ID;
				if ( $source_id === $canonical_id ) {
					continue;
				}
				admitad_migration_merge_meta( $source_id, $canonical_id );
				admitad_migration_merge_terms( $source_id, $canonical_id );
				global $wpdb;
				$wpdb->update( $wpdb->comments, array( 'comment_post_ID' => $canonical_id ), array( 'comment_post_ID' => $source_id ), array( '%d' ), array( '%d' ) );
				if ( wp_delete_post( $source_id, true ) ) {
					$report['deleted'][] = $source_id;
				}
			}
			admitad_migration_assign_campaign_shop( $canonical_id );
			$report['mappings'][ $external_id ] = $canonical_id;
		}

		$legacy_terms = get_terms( array( 'taxonomy' => 'shop_coupons', 'hide_empty' => false ) );
		if ( ! is_wp_error( $legacy_terms ) ) {
			foreach ( $legacy_terms as $term ) {
				wp_delete_term( $term->term_id, 'shop_coupons' );
			}
		}
		$page = admitad_migration_ensure_shops_page();
		if ( is_wp_error( $page ) ) {
			$report['errors'][] = array( 'message' => $page->get_error_message() );
		}
	} finally {
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
	}

	$verification             = admitad_migration_analyze();
	$verification['shops']    = (int) wp_count_posts( 'shops' )->publish + (int) wp_count_posts( 'shops' )->future;
	$verification['errors']   = count( $report['errors'] );
	$report['verification']   = $verification;
	$report['completed_at']   = current_time( 'mysql' );
	update_option( 'admitad_last_migration_report', $report, false );
	flush_rewrite_rules();
	return $report;
}

