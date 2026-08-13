<?php
/**
 * Shop taxonomy archive.
 *
 * @package promokodiki
 */

get_header();

$shop = get_queried_object();
if ( ! $shop instanceof WP_Term || 'shops_category' !== $shop->taxonomy ) {
	get_footer();
	return;
}

$profile         = promokodiki_shop_profile( $shop );
$active_shop_ids = promokodiki_shop_active_term_ids();
$has_offers      = in_array( $shop->term_id, $active_shop_ids, true );
$acf_context     = 'shops_category_' . $shop->term_id;
$address         = promokodiki_shop_acf( 'address', $shop );
$phone           = promokodiki_shop_acf( 'phone', $shop );
$email           = promokodiki_shop_acf( 'email', $shop );

$render_logo = static function ( array $shop_profile, string $size = 'medium' ): void {
	if ( $shop_profile['logo_id'] ) {
		echo wp_get_attachment_image( $shop_profile['logo_id'], $size, false, array( 'alt' => $shop_profile['logo_alt'], 'loading' => 'lazy' ) );
	} elseif ( $shop_profile['logo_url'] ) {
		printf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $shop_profile['logo_url'] ), esc_attr( $shop_profile['logo_alt'] ) );
	}
};
?>

<div class="container">
	<div class="main__title">
		<h1><?php echo esc_html( $shop->name ); ?></h1>
		<?php if ( $profile['logo_id'] || $profile['logo_url'] ) : ?>
			<div class="category-image-wrapper"><?php $render_logo( $profile ); ?></div>
		<?php endif; ?>
	</div>
</div>

<section class="promocodes">
	<div class="container">
		<div class="promocodes__row">
			<div class="promocodes__column">
				<?php if ( $has_offers && function_exists( 'promokodiki_filter_render' ) ) : ?>
					<?php promokodiki_filter_render( array( 'context' => 'shop', 'object_id' => $shop->term_id ) ); ?>
				<?php elseif ( $has_offers ) : ?>
					<div class="promocodes__items">
						<?php
						$fallback = new WP_Query(
							array(
								'post_type'      => 'promocode',
								'post_status'    => 'publish',
								'posts_per_page' => 6,
								'tax_query'      => array( array( 'taxonomy' => 'shops_category', 'field' => 'term_id', 'terms' => $shop->term_id ) ),
								'meta_query'     => array(
									'relation' => 'AND',
									array( 'relation' => 'OR', array( 'key' => '_promocode_is_active', 'compare' => 'NOT EXISTS' ), array( 'key' => '_promocode_is_active', 'value' => 'no', 'compare' => '!=' ) ),
									array( 'relation' => 'OR', array( 'key' => '_promocode_expiry_date', 'compare' => 'NOT EXISTS' ), array( 'key' => '_promocode_expiry_date', 'value' => '' ), array( 'key' => '_promocode_expiry_date', 'value' => current_time( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ) ),
								),
							)
						);
						while ( $fallback->have_posts() ) {
							$fallback->the_post();
							get_template_part( 'template-parts/promocode-card' );
						}
						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<div class="promocodes__empty"><p><?php esc_html_e( 'Сейчас у этого магазина нет активных предложений.', 'promokodiki' ); ?></p></div>
				<?php endif; ?>

				<?php if ( trim( wp_strip_all_tags( $profile['full_description'] ) ) ) : ?>
					<div class="promocodes__desc"><?php echo wp_kses_post( $profile['full_description'] ); ?></div>
				<?php endif; ?>

				<?php if ( function_exists( 'have_rows' ) && have_rows( 'sekczii', $acf_context ) ) : ?>
					<?php while ( have_rows( 'sekczii', $acf_context ) ) : the_row(); ?>
						<?php
						$partials = array(
							'pervyj_ekran'  => 'banner',
							'top_promokodov' => 'top',
							'new'            => 'new',
							'promokody'      => 'promocodes',
							'faq'            => 'faq',
							'seo'            => 'seo',
						);
						$layout = get_row_layout();
						if ( isset( $partials[ $layout ] ) ) {
							get_template_part( 'template-parts/partials/' . $partials[ $layout ] );
						}
						?>
					<?php endwhile; ?>
				<?php endif; ?>
			</div>

			<aside class="promocodes__aside">
				<?php if ( $profile['logo_id'] || $profile['logo_url'] || $profile['rating'] || $profile['about'] || $address || $phone || $email || $profile['website'] ) : ?>
					<div class="promocodes__shop">
						<?php if ( $profile['logo_id'] || $profile['logo_url'] || $profile['rating'] ) : ?>
							<div class="promocodes__shop-wrap">
								<?php if ( $profile['logo_id'] || $profile['logo_url'] ) : ?><div class="promocodes__shop-logo"><?php $render_logo( $profile ); ?></div><?php endif; ?>
								<?php if ( $profile['rating'] ) : ?>
									<div class="promocodes__shop-stars" aria-label="<?php echo esc_attr( sprintf( 'Рейтинг: %s из 5', number_format_i18n( $profile['rating'], 1 ) ) ); ?>">
										<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
											<svg width="19" height="19" viewBox="0 0 19 19" aria-hidden="true"><use href="#<?php echo esc_attr( $star <= round( $profile['rating'] ) ? 'star' : 'not-star' ); ?>" /></svg>
										<?php endfor; ?>
										<span class="screen-reader-text"><?php echo esc_html( number_format_i18n( $profile['rating'], 1 ) ); ?></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( $profile['about'] ) : ?>
							<div class="promocodes__shop-title"><?php esc_html_e( 'О магазине', 'promokodiki' ); ?></div>
							<div class="promocodes__shop-text"><?php echo wp_kses_post( wpautop( $profile['about'] ) ); ?></div>
						<?php endif; ?>

						<?php if ( $address || $phone || $email || $profile['website'] ) : ?>
							<div class="promocodes__shop-data">
								<?php if ( $address ) : ?><address class="promocodes__shop-loc"><?php echo esc_html( $address ); ?></address><?php endif; ?>
								<?php if ( $phone ) : ?><div class="promocodes__shop-tel"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></div><?php endif; ?>
								<?php if ( $email ) : ?><div class="promocodes__shop-mail"><a href="mailto:<?php echo esc_attr( antispambot( sanitize_email( $email ) ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></div><?php endif; ?>
								<?php if ( $profile['website'] ) : ?><div class="promocodes__shop-site"><a href="<?php echo esc_url( $profile['website'] ); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php echo esc_html( wp_parse_url( $profile['website'], PHP_URL_HOST ) ?: $profile['website'] ); ?></a></div><?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				$related_ids = array_values( array_diff( $active_shop_ids, array( $shop->term_id ) ) );
				$related     = $related_ids ? get_terms( array( 'taxonomy' => 'shops_category', 'include' => $related_ids, 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) ) : array();
				$related     = is_wp_error( $related ) ? array() : $related;
				?>
				<?php if ( $related ) : ?>
					<div class="promocodes__store">
						<div class="promocodes__store-wrap"><div class="promocodes__store-title"><?php esc_html_e( 'Другие магазины', 'promokodiki' ); ?></div><a href="<?php echo esc_url( home_url( '/shops/' ) ); ?>" class="promocodes__store-link"><?php esc_html_e( 'Все', 'promokodiki' ); ?></a></div>
						<div class="promocodes__store-items">
							<?php foreach ( $related as $related_shop ) : $related_profile = promokodiki_shop_profile( $related_shop ); ?>
								<a href="<?php echo esc_url( get_term_link( $related_shop ) ); ?>" class="promocodes__imgs" aria-label="<?php echo esc_attr( $related_shop->name ); ?>">
									<?php if ( $related_profile['logo_id'] || $related_profile['logo_url'] ) { $render_logo( $related_profile, 'thumbnail' ); } else { echo '<span>' . esc_html( mb_substr( $related_shop->name, 0, 1, 'UTF-8' ) ) . '</span>'; } ?>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</section>

<?php get_footer(); ?>
