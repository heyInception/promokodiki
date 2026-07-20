<?php
/** Streaming, idempotent coupon importer. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function admitad_cron_hooks() {
	return array( 'update_admitad_coupons_event' );
}

function admitad_schedule_events() {
	if ( ! wp_next_scheduled( 'update_admitad_coupons_event' ) ) {
		wp_schedule_event( time() + 300, 'twicedaily', 'update_admitad_coupons_event' );
	}
}

add_action( 'update_admitad_coupons_event', 'update_admitad_coupons_data' );

function admitad_acquire_import_lock() {
	$now      = time();
	$existing = (int) get_option( 'admitad_import_lock', 0 );
	if ( $existing && $existing > $now - 1800 ) {
		return false;
	}
	if ( $existing ) {
		delete_option( 'admitad_import_lock' );
	}
	return add_option( 'admitad_import_lock', $now, '', false );
}

function admitad_release_import_lock() {
	delete_option( 'admitad_import_lock' );
}

function admitad_existing_coupon_map() {
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT pm.meta_value AS external_id, MIN(p.ID) AS post_id
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'admitad_coupon_id'
		WHERE p.post_type = 'promocode' AND p.post_status NOT IN ('trash', 'auto-draft')
		GROUP BY pm.meta_value"
	);
	$map = array();
	foreach ( $rows as $row ) {
		$map[ (string) $row->external_id ] = (int) $row->post_id;
	}
	return $map;
}

function admitad_find_or_create_shop( array $campaign, $image_url = '' ) {
	$campaign_id = isset( $campaign['id'] ) ? (string) $campaign['id'] : '';
	$name        = sanitize_text_field( $campaign['name'] ?? '' );
	if ( '' === $name ) {
		return 0;
	}

	$term_id = 0;
	if ( '' !== $campaign_id ) {
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
			$term_id = (int) $ids[0];
		}
	}

	if ( ! $term_id ) {
		$term = get_term_by( 'name', $name, 'shops_category' );
		if ( $term ) {
			$term_id = (int) $term->term_id;
		} else {
			$created = wp_insert_term( $name, 'shops_category', array( 'slug' => sanitize_title( $name ) ) );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$term_id = (int) $created['term_id'];
		}
	}

	if ( '' !== $campaign_id ) {
		update_term_meta( $term_id, 'admitad_campaign_id', $campaign_id );
	}
	if ( ! empty( $campaign['site_url'] ) ) {
		update_term_meta( $term_id, 'shop_website', esc_url_raw( $campaign['site_url'] ) );
	}
	if ( $image_url && ! get_term_meta( $term_id, 'image_url', true ) ) {
		update_term_meta( $term_id, 'image_url', esc_url_raw( $image_url ) );
	}
	return $term_id;
}

function admitad_upsert_coupon( array $coupon, array &$existing_map ) {
	$external_id = isset( $coupon['id'] ) ? (string) $coupon['id'] : '';
	if ( '' === $external_id ) {
		return new WP_Error( 'missing_external_id', 'Coupon has no Admitad ID.' );
	}

	$start      = ! empty( $coupon['date_start'] ) ? strtotime( $coupon['date_start'] ) : false;
	$post_date  = $start ? wp_date( 'Y-m-d H:i:s', $start ) : current_time( 'mysql' );
	$post_data  = array(
		'post_title'   => sanitize_text_field( $coupon['name'] ?? '' ),
		'post_content' => wp_kses_post( $coupon['description'] ?? '' ),
		'post_excerpt' => sanitize_text_field( $coupon['short_name'] ?? '' ),
		'post_status'  => $start && $start > current_time( 'timestamp' ) ? 'future' : 'publish',
		'post_type'    => 'promocode',
		'post_date'    => $post_date,
	);

	if ( isset( $existing_map[ $external_id ] ) ) {
		$post_data['ID'] = $existing_map[ $external_id ];
		$post_id         = wp_update_post( wp_slash( $post_data ), true );
	} else {
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( ! is_wp_error( $post_id ) ) {
			$existing_map[ $external_id ] = (int) $post_id;
		}
	}
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$campaign = is_array( $coupon['campaign'] ?? null ) ? $coupon['campaign'] : array();
	$meta     = array(
		'admitad_coupon_id'       => $external_id,
		'_promocode_code'         => sanitize_text_field( $coupon['promocode'] ?? '' ),
		'_promocode_link'         => esc_url_raw( $coupon['goto_link'] ?? '' ),
		'_promocode_expiry_date'  => sanitize_text_field( $coupon['date_end'] ?? '' ),
		'_promocode_is_active'    => 'yes',
		'_promocode_is_verified'  => 'yes',
		'campaign_id'             => sanitize_text_field( $campaign['id'] ?? '' ),
		'campaign_name'           => sanitize_text_field( $campaign['name'] ?? '' ),
		'discount'                => sanitize_text_field( $coupon['discount'] ?? '' ),
		'species'                 => sanitize_text_field( is_array( $coupon['species'] ?? null ) ? wp_json_encode( $coupon['species'] ) : ( $coupon['species'] ?? '' ) ),
		'frameset_link'           => esc_url_raw( $coupon['frameset_link'] ?? '' ),
		'goto_link'               => esc_url_raw( $coupon['goto_link'] ?? '' ),
		'date_start'              => sanitize_text_field( $coupon['date_start'] ?? '' ),
		'date_end'                => sanitize_text_field( $coupon['date_end'] ?? '' ),
		'image_url'               => esc_url_raw( $coupon['image'] ?? '' ),
		'_admitad_original_categories' => wp_json_encode( $coupon['categories'] ?? array() ),
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	$shop_id = admitad_find_or_create_shop( $campaign, $meta['image_url'] );
	if ( $shop_id ) {
		wp_set_post_terms( $post_id, array( $shop_id ), 'shops_category', false );
	}

	if ( class_exists( 'Admitad_Category_Mapper' ) ) {
		$mapper      = new Admitad_Category_Mapper();
		$category_id = $mapper->get_site_subcategory_by_text( $post_data['post_title'], $post_data['post_content'], $meta['campaign_name'], $post_id );
		if ( $category_id > 0 ) {
			wp_set_post_terms( $post_id, array( (int) $category_id ), 'promocode_category', false );
			update_post_meta( $post_id, '_assigned_site_category_id', (int) $category_id );
		}
	}

	return (int) $post_id;
}

function update_admitad_coupons_data() {
	if ( ! admitad_acquire_import_lock() ) {
		return new WP_Error( 'import_locked', 'Another Admitad import is already running.' );
	}

	$stats = array( 'processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => array(), 'pages' => 0 );
	try {
		wp_raise_memory_limit( 'admin' );
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		$existing = admitad_existing_coupon_map();
		$limit    = 200;
		$offset   = 0;
		do {
			$page = get_admitad_coupons_from_api( $limit, $offset );
			if ( is_wp_error( $page ) ) {
				$stats['errors'][] = $page->get_error_message();
				break;
			}
			$items = is_array( $page['results'] ?? null ) ? $page['results'] : array();
			foreach ( $items as $coupon ) {
				$was_existing = isset( $existing[ (string) ( $coupon['id'] ?? '' ) ] );
				$result       = admitad_upsert_coupon( $coupon, $existing );
				if ( is_wp_error( $result ) ) {
					$stats['errors'][] = $result->get_error_message();
					continue;
				}
				++$stats['processed'];
				++$stats[ $was_existing ? 'updated' : 'created' ];
			}
			++$stats['pages'];
			$offset += $limit;
		} while ( count( $items ) === $limit );
		$stats['completed_at'] = current_time( 'mysql' );
		update_option( 'admitad_last_sync_report', $stats, false );
		update_option( 'admitad_last_sync', $stats['completed_at'], false );
	} finally {
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		admitad_release_import_lock();
	}
	return $stats;
}

