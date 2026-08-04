<?php
/**
 * GET-functional Discounts sort links.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$discount_sorts = array(
	'popular'   => __( 'Топ', 'promokodiki-ajax-filter' ),
	'newest'    => __( 'Новинки', 'promokodiki-ajax-filter' ),
	'discussed' => __( 'Обсуждаемое', 'promokodiki-ajax-filter' ),
);
?>
<form class="promokodiki-filter__sort-form" method="get" action="<?php echo esc_url( get_permalink() ); ?>" data-filter-form>
	<input type="hidden" name="paf_sort" value="<?php echo esc_attr( $state['sort'] ); ?>">
	<nav class="promocodes__sort" aria-label="<?php esc_attr_e( 'Сортировать:', 'promokodiki-ajax-filter' ); ?>">
		<span class="promocodes__sort-label"><?php esc_html_e( 'Сортировать:', 'promokodiki-ajax-filter' ); ?></span>
		<div class="promocodes__sort-links">
			<?php foreach ( $discount_sorts as $sort_key => $sort_label ) : ?>
				<a
					class="tabs__nav-btn"
					href="<?php echo esc_url( add_query_arg( 'paf_sort', $sort_key, get_permalink() ) ); ?>"
					data-filter-sort="<?php echo esc_attr( $sort_key ); ?>"
					<?php echo $state['sort'] === $sort_key ? 'aria-current="true"' : ''; ?>
				>
					<?php echo esc_html( $sort_label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
</form>
