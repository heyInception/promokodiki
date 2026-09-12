<?php
/** WP-CLI integration test for same-shop active alternatives. */

require dirname( __DIR__ ) . '/inc/promocode-expiry.php';

$term = wp_insert_term( 'Expiry alternatives ' . wp_generate_uuid4(), 'shops_category' );
if ( is_wp_error( $term ) ) {
	throw new RuntimeException( $term->get_error_message() );
}

$ids = array();
try {
	foreach ( array( '-8 days', '+2 days', '-1 day' ) as $offset ) {
		$id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 'Expiry alternative fixture ' . $offset,
			)
		);
		$ids[] = $id;
		wp_set_object_terms( $id, array( (int) $term['term_id'] ), 'shops_category' );
		update_post_meta( $id, '_promocode_expiry_date', current_datetime()->modify( $offset )->format( 'Y-m-d' ) );
	}

	$actual = promokodiki_promocode_active_shop_alternatives( $ids[0] );
	if ( array( $ids[1] ) !== array_values( $actual ) ) {
		throw new RuntimeException( 'Expected only the active same-shop offer; got ' . wp_json_encode( $actual ) );
	}

	echo "Promocode alternatives integration passed.\n";
} finally {
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	wp_delete_term( (int) $term['term_id'], 'shops_category' );
}
