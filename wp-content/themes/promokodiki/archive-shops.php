<?php
/**
 * Shop catalogue archive.
 *
 * @package promokodiki
 */

get_header();

$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$active_term_ids = promokodiki_shop_active_term_ids();
$shops           = array();

if ( $active_term_ids ) {
	$shops = get_terms(
		array(
			'taxonomy'   => 'shops_category',
			'hide_empty' => false,
			'include'    => $active_term_ids,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	$shops = is_wp_error( $shops ) ? array() : array_values( array_filter( $shops, static fn( WP_Term $term ): bool => promokodiki_shop_matches_search( $term, $search ) ) );
}

$groups = array();
foreach ( $shops as $shop ) {
	$letter = mb_strtolower( mb_substr( $shop->name, 0, 1, 'UTF-8' ), 'UTF-8' );
	$key    = preg_match( '/^[0-9]$/u', $letter ) ? '0-9' : $letter;
	$groups[ $key ][] = $shop;
}

$latin    = range( 'a', 'z' );
$cyrillic = array( 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я' );
$order    = array_merge( array( '0-9' ), $latin, $cyrillic );
?>

<section class="alphabetical">
	<div class="container">
		<div class="alphabetical__column">
			<div class="alphabetical__title">
				<h1><?php echo esc_html( is_page() ? get_the_title() : post_type_archive_title( '', false ) ); ?></h1>
			</div>

			<div class="alphabetical__search">
				<form id="shops-search-form" action="<?php echo esc_url( home_url( '/shops/' ) ); ?>" method="get" class="form" role="search">
					<label class="screen-reader-text" for="shops-search-input"><?php esc_html_e( 'Найти магазин', 'promokodiki' ); ?></label>
					<input type="search" id="shops-search-input" name="s" class="input-reset form__input" placeholder="Название магазина" value="<?php echo esc_attr( $search ); ?>" autocomplete="off">
					<button type="submit" id="shops-search-submit" class="btn-reset form__btn" aria-label="<?php esc_attr_e( 'Поиск', 'promokodiki' ); ?>">
						<svg width="21" height="21" viewBox="0 0 21 21" fill="none" aria-hidden="true"><path d="M20 20L15.514 15.506M18 9.5C18 14.1944 14.1944 18 9.5 18C4.80558 18 1 14.1944 1 9.5C1 4.80558 4.80558 1 9.5 1C14.1944 1 18 4.80558 18 9.5Z" stroke="#F682A5" stroke-width="2" stroke-linecap="round"/></svg>
					</button>
				</form>
			</div>

			<?php if ( $shops ) : ?>
				<nav class="alphabetical__index" aria-label="<?php esc_attr_e( 'Алфавитный указатель магазинов', 'promokodiki' ); ?>">
					<div class="alphabetical__index-wrap">
						<?php foreach ( array_merge( array( '0-9' ), $latin ) as $letter ) : ?>
							<a href="#shop-letter-<?php echo esc_attr( $letter ); ?>" class="alphabetical__index-item<?php echo isset( $groups[ $letter ] ) ? '' : ' alphabetical__index-item_not'; ?>"<?php echo isset( $groups[ $letter ] ) ? '' : ' aria-disabled="true"'; ?>><?php echo esc_html( '0-9' === $letter ? $letter : strtoupper( $letter ) ); ?></a>
						<?php endforeach; ?>
					</div>
					<div class="alphabetical__index-wrap">
						<?php foreach ( $cyrillic as $letter ) : ?>
							<a href="#shop-letter-<?php echo esc_attr( $letter ); ?>" class="alphabetical__index-item<?php echo isset( $groups[ $letter ] ) ? '' : ' alphabetical__index-item_not'; ?>"<?php echo isset( $groups[ $letter ] ) ? '' : ' aria-disabled="true"'; ?>><?php echo esc_html( mb_strtoupper( $letter, 'UTF-8' ) ); ?></a>
						<?php endforeach; ?>
					</div>
				</nav>
			<?php endif; ?>

			<div class="alphabetical__lists" id="shops-list-container" aria-live="polite">
				<?php foreach ( $order as $letter ) : ?>
					<?php if ( ! empty( $groups[ $letter ] ) ) : ?>
						<div class="alphabetical__list" data-letter-group="<?php echo esc_attr( $letter ); ?>">
							<div id="shop-letter-<?php echo esc_attr( $letter ); ?>" class="alphabetical__name"><?php echo esc_html( '0-9' === $letter ? $letter : mb_strtoupper( $letter, 'UTF-8' ) ); ?></div>
							<div class="alphabetical__list-wrap">
								<?php foreach ( $groups[ $letter ] as $shop ) : ?>
									<a href="<?php echo esc_url( get_term_link( $shop ) ); ?>" class="alphabetical__list-item" data-shop-name="<?php echo esc_attr( mb_strtolower( $shop->name, 'UTF-8' ) ); ?>"><?php echo esc_html( $shop->name ); ?></a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
				<p class="alphabetical__empty"<?php echo $shops ? ' hidden' : ''; ?>><?php esc_html_e( 'Магазины не найдены.', 'promokodiki' ); ?></p>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
