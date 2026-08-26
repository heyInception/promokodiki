<?php

/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 * Template name: FAQ
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php
	$post_id = get_the_ID();
	echo promokodiki_render_faq_page(
		promokodiki_get_faq_page_sections( $post_id ),
		promokodiki_get_faq_page_data( $post_id )
	);
	?>
</main><!-- #main -->

<?php
get_footer();
