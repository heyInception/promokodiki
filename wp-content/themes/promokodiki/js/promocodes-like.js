( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.promocodes__like', function ( event ) {
		event.preventDefault();
		const button = $( this );
		const postId = button.data( 'post-id' );
		const reaction = button.data( 'action' );
		if ( ! postId || ! reaction || button.hasClass( 'loading' ) ) {
			return;
		}

		button.addClass( 'loading' );
		$.post( promocodes_ajax.ajaxurl, {
			action: 'promokodiki_promo_vote',
			post_id: postId,
			reaction: reaction,
			nonce: promocodes_ajax.nonce
		} ).done( function ( response ) {
			if ( ! response.success ) {
				return;
			}
			$( '.promocodes__like_yes[data-post-id="' + postId + '"] span' ).text( response.data.likes );
			$( '.promocodes__like_no[data-post-id="' + postId + '"] span' ).text( response.data.dislikes );
			$( '.promocodes__like[data-post-id="' + postId + '"]' ).removeClass( 'is-active' );
			button.addClass( 'is-active' );
		} ).always( function () {
			button.removeClass( 'loading' );
		} );
	} );
}( jQuery ) );
