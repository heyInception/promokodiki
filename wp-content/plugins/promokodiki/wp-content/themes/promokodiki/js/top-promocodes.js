( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const container = document.getElementById( 'popular-promocodes-container' );
		const config = window.PromokodikiInteractions || {};
		if ( ! container || ! config.ajaxUrl || ! config.nonce ) {
			return;
		}

		let nextUpdate = Number( container.dataset.nextUpdate || 0 );
		const serverAtLoad = Number( container.dataset.serverTime || 0 );
		const clientAtLoad = Math.floor( Date.now() / 1000 );
		const offset = serverAtLoad ? serverAtLoad - clientAtLoad : 0;
		let refreshing = false;

		function renderTimer( seconds ) {
			const hours = document.getElementById( 'topHours' );
			const minutes = document.getElementById( 'topMinutes' );
			const remainingSeconds = document.getElementById( 'topSeconds' );
			if ( hours ) { hours.textContent = String( Math.floor( seconds / 3600 ) ).padStart( 2, '0' ); }
			if ( minutes ) { minutes.textContent = String( Math.floor( ( seconds % 3600 ) / 60 ) ).padStart( 2, '0' ); }
			if ( remainingSeconds ) { remainingSeconds.textContent = String( seconds % 60 ).padStart( 2, '0' ); }
		}

		function refresh() {
			if ( refreshing ) { return; }
			refreshing = true;
			container.classList.add( 'updating' );
			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams( { action: 'promokodiki_top_snapshot', nonce: config.nonce } ),
			} )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					if ( response && response.success && response.data ) {
						container.innerHTML = response.data.html;
						nextUpdate = Number( response.data.next_update || nextUpdate );
					}
				} )
				.catch( function () {} )
				.finally( function () {
					refreshing = false;
					container.classList.remove( 'updating' );
				} );
		}

		function tick() {
			const serverNow = Math.floor( Date.now() / 1000 ) + offset;
			const remaining = Math.max( 0, nextUpdate - serverNow );
			renderTimer( remaining );
			if ( 0 === remaining ) { refresh(); }
		}

		tick();
		setInterval( tick, 1000 );
	} );
}() );
