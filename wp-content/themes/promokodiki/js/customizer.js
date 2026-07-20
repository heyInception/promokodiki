/* global wp, jQuery */
/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */


(function ($) {
	$(document).ready(function () {
		$('.content_toggle').click(function () {
			$('.content_block').toggleClass('hide');
			if ($('.content_block').hasClass('hide')) {
				$('.content_toggle').html('Показать всё');
				$('.content_toggle').removeClass('content_toggle-hide');
			} else {
				$('.content_toggle').html('Скрыть');
				$('.content_toggle').addClass('content_toggle-hide');
			}
			return false;
		});
	});
}(jQuery));
