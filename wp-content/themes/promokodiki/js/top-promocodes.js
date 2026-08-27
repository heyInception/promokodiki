( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const container = document.getElementById( 'telegram-promocodes-container' );
		if ( ! container ) { return; }
		const nextUpdate = Number( container.dataset.nextUpdate || 0 );
		const serverAtLoad = Number( container.dataset.serverTime || 0 );
		const clientAtLoad = Math.floor( Date.now() / 1000 );
		const offset = serverAtLoad ? serverAtLoad - clientAtLoad : 0;

		function tick() {
			const seconds = Math.max( 0, nextUpdate - Math.floor( Date.now() / 1000 ) - offset );
			const hours = document.getElementById( 'topHours' );
			const minutes = document.getElementById( 'topMinutes' );
			const remaining = document.getElementById( 'topSeconds' );
			if ( hours ) { hours.textContent = String( Math.floor( seconds / 3600 ) ).padStart( 2, '0' ); }
			if ( minutes ) { minutes.textContent = String( Math.floor( ( seconds % 3600 ) / 60 ) ).padStart( 2, '0' ); }
			if ( remaining ) { remaining.textContent = String( seconds % 60 ).padStart( 2, '0' ); }
		}

		tick();
		setInterval( tick, 1000 );
	} );
}() );
