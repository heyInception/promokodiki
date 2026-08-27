<?php
/** Telegram promocode top slider. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function promokodiki_telegram_next_update( ?int $now = null ): int {
	$now    = $now ?? current_time( 'timestamp' );
	$window = 3 * HOUR_IN_SECONDS;
	return ( intdiv( $now, $window ) + 1 ) * $window;
}

function promokodiki_render_telegram_top(): void {
	$count = class_exists( 'Promokodiki_Telegram_Config' ) ? Promokodiki_Telegram_Config::card_count() : 4;
	$ids   = class_exists( 'Promokodiki_Telegram_Query' ) ? Promokodiki_Telegram_Query::top_ids( $count ) : array();
	$now   = current_time( 'timestamp' );
	?>
	<div class="top__slider swiper" id="telegram-promocodes-container" data-next-update="<?php echo esc_attr( (string) promokodiki_telegram_next_update( $now ) ); ?>" data-server-time="<?php echo esc_attr( (string) $now ); ?>">
		<div class="top__items swiper-wrapper">
			<?php if ( ! $ids ) : ?>
				<div class="top__empty">Промокоды из Telegram скоро появятся.</div>
			<?php else : ?>
				<?php foreach ( $ids as $post_id ) : promokodiki_render_telegram_card( (int) $post_id ); endforeach; ?>
			<?php endif; ?>
		</div>
		<?php if ( count( $ids ) > 1 ) : ?><button class="top__prev" type="button" aria-label="Предыдущие промокоды"></button><button class="top__next" type="button" aria-label="Следующие промокоды"></button><?php endif; ?>
	</div>
	<?php
}

function promokodiki_render_telegram_card( int $post_id ): void {
	$code          = (string) get_post_meta( $post_id, '_promocode_code', true );
	$link          = (string) get_post_meta( $post_id, '_promocode_link', true );
	$expires_at    = (int) get_post_meta( $post_id, '_telegram_expires_at', true );
	$used          = max( 0, (int) get_post_meta( $post_id, '_promocode_used_count', true ) );
	$likes         = max( 0, (int) get_post_meta( $post_id, '_promocode_likes', true ) );
	$dislikes      = max( 0, (int) get_post_meta( $post_id, '_promocode_dislikes', true ) );
	$description   = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	$visitor_id    = isset( $_COOKIE['promokodiki_visitor'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['promokodiki_visitor'] ) ) : '';
	$user_reaction = class_exists( 'Promokodiki_Filter_Promo_Interactions' ) ? Promokodiki_Filter_Promo_Interactions::reaction_for( $post_id, $visitor_id ) : '';
	$is_expired    = $expires_at > 0 && current_time( 'timestamp' ) > $expires_at;
	$is_popular    = $used > 10;
	$is_new        = ( current_time( 'timestamp' ) - (int) get_post_time( 'U', true, $post_id ) ) < ( 7 * DAY_IN_SECONDS );
	$expiry_label  = $expires_at > 0 ? wp_date( 'd.m.Y', $expires_at ) : 'Бессрочно';
	?>
	<article class="swiper-slide top__slide top__item" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-store-url="<?php echo esc_url( $link ); ?>" data-code="<?php echo esc_attr( $code ); ?>" data-expiry="<?php echo esc_attr( $expiry_label ); ?>" data-expired="<?php echo $is_expired ? 'true' : 'false'; ?>" data-description="<?php echo esc_attr( $description ); ?>">
		<?php if ( $is_expired ) : ?>
			<div class="promocodes__badge promocodes__badge_new">Истекло</div>
		<?php elseif ( $is_popular ) : ?>
			<div class="promocodes__badge promocodes__badge_popular">Популярный</div>
		<?php elseif ( $is_new ) : ?>
			<div class="promocodes__badge promocodes__badge_new">Новый</div>
		<?php endif; ?>

		<div class="top__img">
			<?php echo get_the_post_thumbnail( $post_id, 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ) ) ?: '<img src="' . esc_url( get_template_directory_uri() . '/img/top-1.png' ) . '" alt="">'; ?>
		</div>

		<div class="top__wrap">
			<div class="top__last"><?php echo $is_expired ? 'Истек' : esc_html( human_time_diff( (int) get_post_time( 'U', true, $post_id ), current_time( 'timestamp' ) ) . ' назад' ); ?></div>
			<div class="top__max"><?php echo $expires_at > 0 ? 'до ' . esc_html( $expiry_label ) : 'Бессрочно'; ?></div>
		</div>

		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="top__head" title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>

		<div class="top__wrapper">
			<div class="top__wrap">
				<div class="top__quantity"><?php echo esc_html( (string) $used ); ?> Применено</div>
				<div class="top__likes">
					<div class="top__up promocodes__like promocodes__like_yes<?php echo 'like' === $user_reaction ? ' is-active' : ''; ?>" data-action="like" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"><span><?php echo esc_html( (string) $likes ); ?></span></div>
					<div class="top__down promocodes__like promocodes__like_no<?php echo 'dislike' === $user_reaction ? ' is-active' : ''; ?>" data-action="dislike" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"><span><?php echo esc_html( (string) $dislikes ); ?></span></div>
				</div>
			</div>

			<div class="top__author"><span class="top__nick">Telegram</span></div>

			<?php if ( $is_expired ) : ?>
				<button class="top__button btn-reset" type="button" disabled>Промокод истёк</button>
			<?php elseif ( '' === $code ) : ?>
				<a class="top__button btn-reset ui-button ui-button--orange" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow noopener">Перейти в магазин</a>
			<?php else : ?>
				<button class="top__button btn-reset promocodes__view ui-button ui-button--orange" type="button" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">Показать промокод</button>
			<?php endif; ?>
		</div>
	</article>
	<?php
}
