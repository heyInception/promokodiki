<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package promokodiki
 */

get_header();
?>

<main id="primary" class="main">
    <div class="news" itemscope itemtype="https://schema.org/ItemList">
        <div class="container">
            <div class="news__column">
                <div class="news__title">
                    <h1 itemprop="name"><?php single_post_title(); ?></h1>
                </div>
                <div class="swiper news__slider">
                    <div class="swiper-wrapper">
                        <?php
                        $args = array(
                            'post_type' => 'post',
                            'posts_per_page' => 5,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        );

                        $query = new WP_Query($args);
                        $position = 1;

                        if ($query->have_posts()) :
                            while ($query->have_posts()) : $query->the_post();
                                $post_date = get_the_date('d.m.Y');
                                $thumbnail_url = get_the_post_thumbnail_url() ?: get_template_directory_uri() . '/img/faq.png';
                        ?>
                                <div class="swiper-slide" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <meta itemprop="position" content="<?php echo $position++; ?>" />
                                    <div class="news__item" itemscope itemtype="https://schema.org/Article">
                                        <a href="<?php the_permalink(); ?>" class="news__img" itemprop="url">
                                            <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php the_title_attribute(); ?>" itemprop="image">
                                        </a>
                                        <div class="news__wrap">
                                            <a href="<?php the_permalink(); ?>" class="news__head" itemprop="headline"><?php the_title(); ?></a>
                                            <div class="news__date" itemprop="datePublished" content="<?php echo get_the_date('c'); ?>"><?php echo $post_date; ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                            // Если постов нет, выводим заглушки
                            for ($i = 0; $i < 5; $i++) {
                            ?>
                                <div class="swiper-slide">
                                    <div class="news__item">
                                        <div class="news__img">
                                            <img src="<?php echo get_template_directory_uri(); ?>/img/faq.png" alt="Блог - promokodiki.com" style="height: 300px;object-fit: contain;">
                                        </div>
                                        <div class="news__wrap">
                                            <div class="news__head">Нет статей для отображения</div>
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
    <?php if (have_rows('sekczii')): ?>
		<?php while (have_rows('sekczii')) : the_row(); ?>
			<?php if (get_row_layout() == 'seo') : ?>
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
