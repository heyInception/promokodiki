document.addEventListener( 'click', function ( event ) {
	const target = event.target instanceof Element ? event.target.closest( '.footer__button_up' ) : null;

	if ( target ) {
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	}
} );
