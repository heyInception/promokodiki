<?php
/**
 * Filter GET form.
 *
 * @var array  $context Context data.
 * @var array  $state Current state.
 * @var array  $settings Plugin settings.
 * @var string $form_action Canonical action URL.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sort_labels = array(
	'newest'   => __( 'Сначала новые', 'promokodiki-ajax-filter' ),
	'popular'  => __( 'Сначала популярные', 'promokodiki-ajax-filter' ),
	'expiring' => __( 'Скоро истекают', 'promokodiki-ajax-filter' ),
	'oldest'   => __( 'Сначала старые', 'promokodiki-ajax-filter' ),
);
?>
<form class="promocodes__filters" method="get" action="<?php echo esc_url( $form_action ); ?>" data-filter-form>
	<div class="promocodes__filters-wrap">
		<?php if ( in_array( $context['type'], array( 'home', 'category' ), true ) ) : ?>
			<label class="screen-reader-text" for="paf-category-<?php echo esc_attr( (string) $context['object_id'] ); ?>">
				<?php echo esc_html( $settings['category_label'] ); ?>
			</label>
			<select id="paf-category-<?php echo esc_attr( (string) $context['object_id'] ); ?>" name="paf_category" data-filter-control>
				<?php if ( 'home' === $context['type'] ) : ?>
					<option value="0"><?php echo esc_html( $settings['category_label'] ); ?></option>
				<?php endif; ?>
				<?php foreach ( $context['category_options'] as $option ) : ?>
					<option value="<?php echo esc_attr( (string) $option['id'] ); ?>" <?php echo selected( $state['category_id'] ?: $context['object_id'], $option['id'], false ); ?>>
						<?php echo esc_html( $option['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<?php if ( in_array( $context['type'], array( 'home', 'shop' ), true ) ) : ?>
			<label class="screen-reader-text" for="paf-brand-<?php echo esc_attr( (string) $context['object_id'] ); ?>">
				<?php echo esc_html( $settings['brand_label'] ); ?>
			</label>
			<select id="paf-brand-<?php echo esc_attr( (string) $context['object_id'] ); ?>" name="paf_brand" data-filter-control>
				<option value="0"><?php echo esc_html( $settings['brand_label'] ); ?></option>
				<?php foreach ( $context['brand_options'] as $option ) : ?>
					<option value="<?php echo esc_attr( (string) $option['id'] ); ?>" <?php echo selected( $state['brand_id'], $option['id'], false ); ?>>
						<?php echo esc_html( $option['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<?php if ( 'home' === $context['type'] ) : ?>
			<label class="promocodes__popular">
				<input class="screen-reader-text" type="checkbox" name="paf_popular" value="1" data-filter-popular <?php checked( $state['popular'] ); ?>>
				<span><?php echo esc_html( $settings['popular_label'] ); ?></span>
			</label>
		<?php endif; ?>
	</div>

	<div class="promocodes__sort">
		<label class="screen-reader-text" for="paf-sort-<?php echo esc_attr( (string) $context['object_id'] ); ?>">
			<?php echo esc_html( $settings['sort_label'] ); ?>
		</label>
		<select id="paf-sort-<?php echo esc_attr( (string) $context['object_id'] ); ?>" name="paf_sort" data-filter-control>
			<option value=""><?php echo esc_html( $settings['sort_label'] ); ?></option>
			<?php foreach ( $settings['enabled_sorts'] as $sort ) : ?>
				<option value="<?php echo esc_attr( $sort ); ?>" <?php echo selected( $state['sort'], $sort, false ); ?>>
					<?php echo esc_html( $sort_labels[ $sort ] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<noscript>
		<button type="submit"><?php echo esc_html( $settings['apply_label'] ); ?></button>
	</noscript>
</form>
