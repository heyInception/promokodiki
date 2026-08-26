( function () {
	'use strict';

	function closeItem( button ) {
		var answer = document.getElementById( button.getAttribute( 'aria-controls' ) );

		button.setAttribute( 'aria-expanded', 'false' );
		button.closest( '.faq-page__item' ).classList.remove( 'is-open' );
		if ( answer ) {
			answer.style.height = answer.scrollHeight + 'px';
			answer.style.opacity = '1';
			window.requestAnimationFrame( function () {
				answer.style.height = '0px';
				answer.style.opacity = '0';
			} );
			answer.addEventListener( 'transitionend', function onClose( event ) {
				if ( event.propertyName !== 'height' ) {
					return;
				}
				answer.hidden = true;
				answer.style.height = '';
				answer.style.opacity = '';
				answer.removeEventListener( 'transitionend', onClose );
			} );
		}
	}

	function openItem( button ) {
		var answer = document.getElementById( button.getAttribute( 'aria-controls' ) );

		button.setAttribute( 'aria-expanded', 'true' );
		button.closest( '.faq-page__item' ).classList.add( 'is-open' );
		if ( answer ) {
			answer.hidden = false;
			answer.style.height = '0px';
			answer.style.opacity = '0';
			window.requestAnimationFrame( function () {
				answer.style.height = answer.scrollHeight + 'px';
				answer.style.opacity = '1';
			} );
			answer.addEventListener( 'transitionend', function onOpen( event ) {
				if ( event.propertyName !== 'height' ) {
					return;
				}
				answer.style.height = '';
				answer.style.opacity = '';
				answer.removeEventListener( 'transitionend', onOpen );
			} );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.faq-page__question' );
		var page;

		if ( ! button ) {
			return;
		}

		page = button.closest( '.faq-page' );
		page.querySelectorAll( '.faq-page__question[aria-expanded="true"]' ).forEach( function ( openButton ) {
			if ( openButton !== button ) {
				closeItem( openButton );
			}
		} );

		if ( 'true' === button.getAttribute( 'aria-expanded' ) ) {
			closeItem( button );
		} else {
			openItem( button );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.faq-page__sidebar-toggle' );
		var link = event.target.closest( '.faq-page__nav a' );
		var sidebar;
		var target;

		if ( toggle ) {
			sidebar = toggle.closest( '.faq-page__sidebar' );
			sidebar.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', sidebar.classList.contains( 'is-open' ) ? 'true' : 'false' );
			return;
		}

		if ( ! link ) {
			return;
		}

		target = document.querySelector( link.getAttribute( 'href' ) );
		if ( ! target ) {
			return;
		}

		event.preventDefault();
		target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		window.history.pushState( null, '', link.getAttribute( 'href' ) );
	} );
}() );
