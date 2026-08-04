/**
 * File navigation.js.
 *
 * Handles toggling the navigation menu for small screens and enables TAB key
 * navigation support for dropdown menus.
 */
( function() {
	const siteNavigation = document.getElementById( 'site-navigation' );

	if ( ! siteNavigation ) {
		return;
	}

	const button = siteNavigation.querySelector( '.menu-toggle' );
	const menu = siteNavigation.querySelector( '.nav-menu' ) || siteNavigation.getElementsByTagName( 'ul' )[ 0 ];

	if ( 'undefined' === typeof menu ) {
		if ( button ) {
			button.style.display = 'none';
		}
		return;
	}

	const parentItems = Array.from( menu.querySelectorAll( '.menu-item-has-children, .page_item_has_children' ) );

	function parentLink( item ) {
		return Array.from( item.children ).find( ( child ) => child.tagName.toLowerCase() === 'a' );
	}

	function setExpanded( item, expanded ) {
		const link = parentLink( item );
		item.classList.toggle( 'focus', expanded );
		if ( link ) {
			link.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		}
	}

	function closeDropdowns( except ) {
		parentItems.forEach( ( item ) => {
			if ( item !== except ) {
				setExpanded( item, false );
			}
		} );
	}

	parentItems.forEach( ( item ) => {
		const link = parentLink( item );
		if ( ! link ) {
			return;
		}

		link.setAttribute( 'aria-haspopup', 'true' );
		link.setAttribute( 'aria-expanded', 'false' );

		item.addEventListener( 'mouseenter', function() {
			closeDropdowns( item );
			setExpanded( item, true );
		} );

		item.addEventListener( 'mouseleave', function() {
			if ( ! item.contains( document.activeElement ) ) {
				setExpanded( item, false );
			}
		} );

		item.addEventListener( 'focusin', function() {
			closeDropdowns( item );
			setExpanded( item, true );
		} );

		item.addEventListener( 'focusout', function( event ) {
			if ( ! item.contains( event.relatedTarget ) ) {
				setExpanded( item, false );
			}
		} );

		link.addEventListener( 'touchstart', function( event ) {
			if ( ! item.classList.contains( 'focus' ) ) {
				event.preventDefault();
				closeDropdowns( item );
				setExpanded( item, true );
			}
		}, { passive: false } );

		link.addEventListener( 'keydown', function( event ) {
			if ( event.key !== 'ArrowDown' ) {
				return;
			}
			event.preventDefault();
			closeDropdowns( item );
			setExpanded( item, true );
			const submenuLink = Array.from( item.getElementsByTagName( 'a' ) ).find( ( candidate ) => candidate !== link );
			if ( submenuLink ) {
				submenuLink.focus();
			}
		} );

		item.addEventListener( 'keydown', function( event ) {
			if ( event.key !== 'Escape' ) {
				return;
			}
			event.preventDefault();
			setExpanded( item, false );
			link.focus();
		} );
	} );

	if ( button ) {
		button.addEventListener( 'click', function() {
			const expanded = siteNavigation.classList.toggle( 'toggled' );
			button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	document.addEventListener( 'click', function( event ) {
		if ( siteNavigation.contains( event.target ) ) {
			return;
		}
		siteNavigation.classList.remove( 'toggled' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', 'false' );
		}
		closeDropdowns();
	} );
}() );
