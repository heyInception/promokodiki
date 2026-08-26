<?php

/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="main">
	<?php get_template_part('template-parts/partials/single/single'); ?>
	<div class="news">
		<div class="container">
			<div class="news__column">
				<div class="news__title">
					<h2>Что еще почитать</h2>
				</div>
				<div class="swiper news__slider">
					<div class="swiper-wrapper">
						<?php
						$args = array(
							'post_type' => 'post',
							'posts_per_page' => 4, // Количество выводимых статей
							'orderby' => 'date',
							'order' => 'DESC',
							'post__not_in'   => array(get_the_ID())
						);

						$query = new WP_Query($args);

						if ($query->have_posts()) :
							while ($query->have_posts()) : $query->the_post();
								$post_date = get_the_date('d.m.Y');
								$thumbnail_url = get_the_post_thumbnail_url() ?: get_template_directory_uri() . '/img/faq.png';
						?>
								<div class="swiper-slide">
									<div class="news__item">
										<a href="<?php the_permalink(); ?>" class="news__img">
											<img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>">
										</a>
										<div class="news__wrap">
											<a href="<?php the_permalink(); ?>" class="news__head"><?php the_title(); ?></a>
											<div class="news__date"><?php echo $post_date; ?></div>
										</div>
									</div>
								</div>
							<?php
							endwhile;
							wp_reset_postdata();
						else :
							// Если постов нет, выводим заглушки как в оригинальном коде
							for ($i = 0; $i < 5; $i++) {
							?>
								<div class="swiper-slide">
									<div class="news__item">
										<div class="news__img">
											<img src="<?php echo get_template_directory_uri(); ?>/img/faq.png" alt="">
										</div>
										<div class="news__wrap">
											<div class="news__head">Нет статей для отображения</div>
											<div class="news__date">--.--.----</div>
										</div>
									</div>
								</div>
						<?php
							}
						endif;
						?>
					</div>
				</div>
			</div>
		</div>
	</div>

</main><!-- #main -->

<?php
get_sidebar();
get_footer();
