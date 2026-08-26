<?php
/**
 * Template Name: О нас
 *
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php echo promokodiki_render_about_page( promokodiki_get_about_page_data( get_the_ID() ) ); ?>
</main><!-- #main -->

<?php get_footer();
