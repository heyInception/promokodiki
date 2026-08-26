<?php
/** Audit and assign exact Admitad campaign links for shop terms. @package Promokodiki_Admitad */
defined( 'ABSPATH' ) || exit;

final class Promokodiki_Admitad_Shop_Link_Audit {
	public function audit( array $args = array() ): array {
		$per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
		$paged    = max( 1, absint( $args['paged'] ?? 1 ) );
		$search   = sanitize_text_field( (string) ( $args['s'] ?? '' ) );
		$terms    = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'search' => $search ) );
		$terms    = is_wp_error( $terms ) ? array() : $terms;
		$counts   = array();
		foreach ( $terms as $term ) {
			$value = trim( (string) get_term_meta( $term->term_id, 'admitad_campaign_id', true ) );
			if ( ctype_digit( $value ) && (int) $value > 0 ) { $counts[ $value ] = ( $counts[ $value ] ?? 0 ) + 1; }
		}
		$items = array(); $repo = new Promokodiki_Admitad_Reference_Repository();
		foreach ( $terms as $term ) {
			$value = trim( (string) get_term_meta( $term->term_id, 'admitad_campaign_id', true ) );
			if ( '' === $value ) { $reason = 'missing'; }
			elseif ( ! ctype_digit( $value ) || (int) $value <= 0 ) { $reason = 'invalid'; }
			elseif ( ( $counts[ $value ] ?? 0 ) > 1 ) { $reason = 'duplicate'; }
			elseif ( null === $repo->campaign( (int) $value ) ) { $reason = 'unknown'; }
			else { continue; }
			$items[] = array( 'term_id' => (int) $term->term_id, 'name' => $term->name, 'campaign_id' => $value, 'reason' => $reason, 'edit_url' => get_edit_term_link( $term, 'shops_category' ) );
		}
		$total = count( $items );
		return array( 'items' => array_slice( $items, ( $paged - 1 ) * $per_page, $per_page ), 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	public function assign( int $term_id, int $campaign_id, int $user_id ) {
		$term = get_term( $term_id, 'shops_category' );
		if ( ! $term instanceof WP_Term || $campaign_id <= 0 ) { return new WP_Error( 'invalid_shop_link', 'Invalid shop or campaign.' ); }
		$campaign = ( new Promokodiki_Admitad_Reference_Repository() )->campaign( $campaign_id );
		if ( ! $campaign ) { return new WP_Error( 'unknown_campaign', 'Campaign is absent from the local Admitad reference.' ); }
		$existing = get_terms( array( 'taxonomy' => 'shops_category', 'hide_empty' => false, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'admitad_campaign_id', 'value' => (string) $campaign_id ) ) ) );
		$existing = is_wp_error( $existing ) ? array() : array_values( array_diff( array_map( 'intval', $existing ), array( $term_id ) ) );
		if ( $existing ) { return new WP_Error( 'duplicate_campaign', 'Campaign is already linked to another shop.' ); }
		update_term_meta( $term_id, 'admitad_campaign_id', (string) $campaign_id );
		$result = ( new Promokodiki_Admitad_Shop_Profile_Sync() )->sync_campaign( array( 'external_id' => $campaign_id, 'description' => $campaign['description'], 'raw_description' => $campaign['raw_description'], 'rating' => null === $campaign['rating'] ? null : (float) $campaign['rating'], 'image_url' => $campaign['image_url'], 'site_url' => $campaign['site_url'] ) );
		if ( 1 !== $result['updated'] ) { delete_term_meta( $term_id, 'admitad_campaign_id' ); return new WP_Error( 'shop_enrichment_failed', 'Shop enrichment failed.' ); }
		update_term_meta( $term_id, '_admitad_shop_manual_audit', array( 'updated_at' => time(), 'user_id' => $user_id, 'source' => 'manual' ) );
		update_term_meta( $term_id, '_admitad_shop_background_queued', time() );
		( new Promokodiki_Admitad_Deeplink_Queue() )->enqueue( $term_id );
		return true;
	}
}
