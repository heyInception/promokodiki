<?php
/** Contract tests for shared promocode expiry rules. */

define( 'PROMOKODIKI_EXPIRY_TEST', true );
require dirname( __DIR__ ) . '/inc/promocode-expiry.php';

function expiry_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . PHP_EOL . 'Expected: ' . var_export( $expected, true ) . PHP_EOL . 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

$today = '2026-09-12';

expiry_assert_same( 'active', promokodiki_promocode_expiry_state( '2026-09-12', $today ), 'A promocode remains active through its expiry date.' );
expiry_assert_same( 'grace', promokodiki_promocode_expiry_state( '2026-09-11', $today ), 'A recently expired promocode enters the listing grace period.' );
expiry_assert_same( 'grace', promokodiki_promocode_expiry_state( '2026-09-05', $today ), 'The seventh day after expiry remains in the listing grace period.' );
expiry_assert_same( 'hidden', promokodiki_promocode_expiry_state( '2026-09-04', $today ), 'The eighth day after expiry is hidden from general listings.' );
expiry_assert_same( 'undated', promokodiki_promocode_expiry_state( '', $today ), 'An offer without an expiry date is undated.' );
expiry_assert_same( true, promokodiki_promocode_is_listing_visible( '2026-09-05', $today ), 'Grace-period offers remain in general listings.' );
expiry_assert_same( false, promokodiki_promocode_is_listing_visible( '2026-09-04', $today ), 'Old expired offers leave general listings.' );
expiry_assert_same( false, promokodiki_promocode_is_recommendable( '2026-09-11', $today ), 'Expired offers never appear in recommendation slots.' );
expiry_assert_same( true, promokodiki_promocode_is_recommendable( '', $today ), 'Undated offers remain eligible for recommendations.' );
expiry_assert_same( 'Срок не указан', promokodiki_promocode_expiry_label( '' ), 'Missing dates are not described as perpetual.' );
expiry_assert_same( '30.09.2026', promokodiki_promocode_expiry_label( '2026-09-30' ), 'Every surface receives one date label format.' );
expiry_assert_same( '30.09.2026', promokodiki_promocode_expiry_label( '2026-09-30 23:59:59' ), 'A stored time cannot shift the displayed calendar date.' );
expiry_assert_same( '2026-09-05', promokodiki_promocode_listing_expiry_meta_query( $today )[2]['value'], 'General listings include the full seven-day grace period.' );
expiry_assert_same( '2026-09-12', promokodiki_promocode_recommendation_expiry_meta_query( $today )[2]['value'], 'Recommendations exclude offers immediately after expiry.' );
expiry_assert_same( true, promokodiki_promocode_should_noindex( 'hidden', false ), 'An old expired offer without alternatives is noindexed.' );
expiry_assert_same( false, promokodiki_promocode_should_noindex( 'hidden', true ), 'An old expired offer with an active alternative can remain indexed.' );
expiry_assert_same( false, promokodiki_promocode_should_noindex( 'grace', false ), 'The seven-day grace period remains indexable.' );

echo "Promocode expiry contract passed.\n";
