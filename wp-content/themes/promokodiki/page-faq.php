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
	<?php if (have_rows('sekczii')): ?>
		<?php while (have_rows('sekczii')) : the_row(); ?>
			<?php if (get_row_layout() == 'faq') : ?>
				<?php get_template_part('template-parts/partials/faq'); ?>
			<?php elseif (get_row_layout() == 'seo') : ?>
				<?php get_template_part('template-parts/partials/seo'); ?>
			<?php endif; ?>
		<?php endwhile; ?>
	<?php else: ?>
		<?php // No layouts found 
		?>
	<?php endif; ?>
</main><!-- #main -->

<?php
get_footer();
