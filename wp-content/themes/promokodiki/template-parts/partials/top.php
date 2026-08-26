<?php
/** Telegram top section. */

$render_top = static function (): void {
	?>
	<section class="top"><div class="container"><div class="top__row">
		<div class="top__banner"><div class="top__banner-title">Обновляем промокоды каждые 3 часа</div><div class="top__banner-img"><img src="<?php echo esc_url( get_template_directory_uri() . '/img/top-banner-tg.png' ); ?>" alt=""></div><div class="top__banner-text">осталось</div><div class="top__banner-countdown"><ul class="list-reset"><li><span id="topHours"></span> часа</li><li><span id="topMinutes"></span> мин</li><li><span id="topSeconds"></span> сек</li></ul></div></div>
		<div class="top__column"><div class="top__title"><h2>Топ промокодов из Telegram</h2></div><?php promokodiki_render_telegram_top(); ?></div>
	</div></div></section>
	<?php
};

$render_top();
