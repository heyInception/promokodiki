<?php
/**
 * Discounts feed with plugin rendering and a server-side fallback.
 *
 * @package promokodiki
 */
?>
<section class="promocodes">
	<div class="container">
		<div class="promocodes__row">
			<div class="promocodes__column">
				<?php if ( function_exists( 'promokodiki_filter_render' ) ) : ?>
					<?php promokodiki_filter_render( array( 'context' => 'discounts' ) ); ?>
				<?php else : ?>
					<?php
					$allowed_sort = array( 'popular', 'newest', 'discussed' );
					$sort = isset( $_GET['paf_sort'] ) ? sanitize_key( wp_unslash( $_GET['paf_sort'] ) ) : 'popular'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public sorting.
					$sort = in_array( $sort, $allowed_sort, true ) ? $sort : 'popular';
					$query = promokodiki_discounts_fallback_query( $sort );
					$sort_labels = array(
						'popular'   => 'Топ',
						'newest'    => 'Новинки',
						'discussed' => 'Обсуждаемое',
					);
					?>
					<nav class="promocodes__sort" aria-label="<?php esc_attr_e( 'Сортировать:', 'promokodiki' ); ?>">
						<span class="promocodes__sort-label"><?php esc_html_e( 'Сортировать:', 'promokodiki' ); ?></span>
						<?php foreach ( $sort_labels as $sort_key => $sort_label ) : ?>
							<a
								href="<?php echo esc_url( add_query_arg( 'paf_sort', $sort_key, get_permalink() ) ); ?>"
								<?php echo $sort === $sort_key ? 'aria-current="true"' : ''; ?>
							>
								<?php echo esc_html( $sort_label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
					<div class="promocodes__items">
						<?php if ( $query->have_posts() ) : ?>
							<?php while ( $query->have_posts() ) : ?>
								<?php $query->the_post(); ?>
								<?php get_template_part( 'template-parts/promocode-card' ); ?>
							<?php endwhile; ?>
						<?php else : ?>
							<p class="no-promocodes"><?php esc_html_e( 'Промокоды не найдены.', 'promokodiki' ); ?></p>
						<?php endif; ?>
					</div>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
