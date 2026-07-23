( function () {
	'use strict';
	function modal() { return document.getElementById( 'promocodeModal' ); }
	function close() {
		const element = modal();
		if ( ! element ) { return; }
		element.classList.remove( 'show' );
		element.style.display = 'none';
		document.body.style.overflow = '';
	}
	window.openPromoModal = function ( postId ) {
		const card = document.querySelector( '.promocodes__item[data-post-id="' + postId + '"]' );
		const element = modal();
		if ( ! card || ! element ) { return; }
		const code = card.querySelector( 'input[name="_promocode_code"]' )?.value || '';
		document.getElementById( 'modalPromoTitle' ).textContent = card.querySelector( '.promocodes__title' )?.textContent || '';
		document.getElementById( 'modalPromoCode' ).value = code;
		document.getElementById( 'modalPromoLink' ).textContent = code ? 'Перейти с промокодом' : 'Перейти в магазин';
		document.getElementById( 'modalPromoUsed' ).textContent = card.querySelector( '.promocodes__used' )?.textContent.replace( /\D/g, '' ) || '0';
		element.dataset.postId = postId;
		element.style.display = 'flex';
		element.classList.add( 'show' );
		document.body.style.overflow = 'hidden';
		if ( code && navigator.clipboard ) { navigator.clipboard.writeText( code ).catch( function () {} ); }
	};
	document.addEventListener( 'DOMContentLoaded', function () {
		const element = modal();
		if ( ! element ) { return; }
		element.querySelector( '.modal-promocode__close' ).addEventListener( 'click', close );
		element.querySelector( '.modal-promocode__overlay' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', function ( event ) { if ( 'Escape' === event.key ) { close(); } } );
		document.getElementById( 'copyPromoBtn' ).addEventListener( 'click', function () {
			const input = document.getElementById( 'modalPromoCode' ); input.select(); document.execCommand( 'copy' );
		} );
		document.getElementById( 'modalPromoLink' ).addEventListener( 'click', function ( event ) {
			event.preventDefault();
			const body = new URLSearchParams( { action: 'promokodiki_promo_use', post_id: element.dataset.postId || '', nonce: window.promokodikiAjaxNonce } );
			fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) { if ( response.success && response.data.store_url ) { window.open( response.data.store_url, '_blank', 'noopener' ); } } );
		} );
	} );
}() );
