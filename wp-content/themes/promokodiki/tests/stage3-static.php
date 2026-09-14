<?php
/** Stage 3 theme source contracts. */
$root = dirname( __DIR__ );
$card = file_get_contents( $root . '/template-parts/promocode-card.php' );
$functions = file_get_contents( $root . '/functions.php' );
$discounts = file_get_contents( $root . '/page-discounts.php' );
$failures = array();
if ( ! str_contains( $card, 'wp_get_attachment_image(' ) ) { $failures[] = 'Cards must use responsive WordPress attachment markup.'; }
if ( ! str_contains( $functions, 'promokodiki_page_has_promocode_cards' ) ) { $failures[] = 'Card asset predicate is missing.'; }
if ( ! str_contains( $functions, "wp_enqueue_script('promokodiki-top-promocodes'" ) || ! str_contains( $functions, 'is_front_page()' ) ) { $failures[] = 'Top promocode script must be front-page scoped.'; }
if ( ! str_contains( $discounts, '<h1' ) || ! str_contains( $discounts, 'the_title()' ) ) { $failures[] = 'Discounts page must render its page title as H1.'; }
if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "PASS stage 3 theme source contracts\n";
