<?php
/**
 * Template Name: Наша команда
 *
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="site-main">
	<?php
	$post_id = get_the_ID();
	echo promokodiki_render_teams_page(
		promokodiki_get_teams_page_sections( $post_id ),
		promokodiki_get_teams_page_data( $post_id )
	);
	?>
</main><!-- #main -->

<?php
get_footer();
