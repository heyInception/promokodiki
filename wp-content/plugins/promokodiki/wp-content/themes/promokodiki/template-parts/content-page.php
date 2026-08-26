<?php

/**
 * Template part for displaying page content in page.php
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package promokodiki
 */

?>

<?php
if (!function_exists('seo_sections')) {
	function seo_sections($post_id)
	{
		while (have_rows('seo_end', $post_id)) : the_row();
?>
			<section id="post-<?php the_ID(); ?>" class="seo">
				<div class="container">
					<div class="seo__row">
						<div class="seo__column">
							<div class="seo__title">
								<h1><?php the_title(); ?></h1>
							</div>
							<div class="seo__content">
								<div class="content_block hide">
									<?php the_content(); ?>
								</div>
								<button class="content_toggle btn-reset button-blue">Показать всё</button>
							</div>
						</div>
						<div class="seo__img">
							<?php promokodiki_post_thumbnail(); ?>
						</div>
					</div>
				</div>
			</section>
		<?php endwhile; ?>
	<?php } ?>
<?php } ?>
<?php
if (have_rows('seo_end')) {
	seo_sections(null); // Текущий пост
} else {
	seo_sections('option'); // Пост с ID 23
}

?>