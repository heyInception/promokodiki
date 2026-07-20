<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="site-main">

	<?php if (have_posts()) : ?>
		<section class="popular">
			<div class="container">
				<div class="popular__column">
					<div class="popular__title">
						<h1><?php
							/* translators: %s: search query. */
							printf(esc_html__('Результаты поиска по запросу: %s', 'promokodiki'), '<span>' . get_search_query() . '</span>');
							?></h1>
					</div>
				</div>
			</div>
		</section>
		<?php echo do_shortcode("[wd_asp elements='results' ratio='100%' id=1]"); ?>
		<section class="promocodes">
			<div class="container">
				<div class="promocodes__row">
					<div class="promocodes__column">
						<div class="promocodes__filters">
							<div class="promocodes__filters-wrap">
								<?php //echo do_shortcode('[fe_widget id="99" horizontal="no" show_count="no"]'); 
								?>
							</div>

							<div class="promocodes__sort">
								
							</div>
						</div>

						<div class="promocodes__items">
							<?php
							/* Start the Loop */
							while (have_posts()) :
								the_post();

								/**
								 * Для Ajax Search PRO используем тот же шаблон
								 * Если нужно выводить специфические поля от плагина,
								 * используйте функции ниже (раскомментируйте при необходимости)
								 */
								
								// Стандартный вывод через шаблон темы
								get_template_part('template-parts/promocode-card');
								
								// ---- ИЛИ (альтернативный вариант) ----
								// Если вам нужно выводить поля напрямую из результатов ASP:
								/*
								?>
								<div class="promocode-item">
									<h3><a href="<?php the_asp_result_field('link'); ?>"><?php the_asp_result_field('title'); ?></a></h3>
									<?php if (get_asp_result_field('image')) : ?>
										<img src="<?php the_asp_result_field('image'); ?>" alt="<?php the_asp_result_field('title'); ?>">
									<?php endif; ?>
									<div><?php the_asp_result_field('content'); ?></div>
									<?php if (get_asp_result_field('date')) : ?>
										<time><?php the_asp_result_field('date'); ?></time>
									<?php endif; ?>
								</div>
								<?php
								*/
								?>

							<?php endwhile; ?>

							<?php
							// Пагинация (если нужна)
							the_posts_pagination(array(
								'mid_size'  => 2,
								'prev_text' => __('« Назад', 'promokodiki'),
								'next_text' => __('Вперед »', 'promokodiki'),
							));
							?>

						</div>
					</div>

				</div>
			</div>
		</section>
	<?php else : ?>

		<section class="promocodes">
			<div class="container">
				<div class="promocodes__row">
					<div class="promocodes__column">
						<div class="promocodes__items">
							<?php get_template_part('template-parts/content', 'none'); ?>
						</div>
					</div>
				</div>
			</div>
		</section>

	<?php endif; ?>
</main><!-- #main -->

<?php
get_footer();