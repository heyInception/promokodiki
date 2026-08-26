/**
 * Primary navigation behavior for desktop dropdowns and the mobile panel.
 */
( function( window, document ) {
	'use strict';

	const initialized = new WeakSet();

	function init( navigation, ownerDocument ) {
		const siteNavigation = navigation || ownerDocument.getElementById( 'site-navigation' );
		if ( ! siteNavigation || initialized.has( siteNavigation ) ) {
			return;
		}
		initialized.add( siteNavigation );

		const button = siteNavigation.querySelector( '.menu-toggle' );
		const menu = siteNavigation.querySelector( '.nav-menu' ) || siteNavigation.getElementsByTagName( 'ul' )[ 0 ];
		if ( ! menu ) {
			if ( button ) {
				button.hidden = true;
			}
			return;
		}

		const mobileQuery = window.matchMedia( '(max-width: 1229px)' );
		const submenuButton = siteNavigation.querySelector( '[data-promocode-submenu-toggle]' );
		const submenu = siteNavigation.querySelector( '[data-promocode-submenu]' );
		const promocodeItem = siteNavigation.querySelector( '.menu-item--promocodes' );
		const favoriteButton = siteNavigation.querySelector( '[data-mobile-favorite]' );
		const favoriteHelp = siteNavigation.querySelector( '[data-mobile-favorite-help]' );
		const parentItems = Array.from( menu.querySelectorAll( '.menu-item-has-children:not(.menu-item--promocodes), .page_item_has_children:not(.menu-item--promocodes)' ) );
		let touchedParent = null;

		function isMobile() {
			return mobileQuery.matches;
		}

		function parentLink( item ) {
			return Array.from( item.children ).find( ( child ) => child.tagName && child.tagName.toLowerCase() === 'a' );
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

		function setPromocodeExpanded( expanded ) {
			if ( ! submenuButton || ! submenu ) {
				return;
			}
			submenuButton.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			siteNavigation.classList.toggle( 'promocode-submenu-open', expanded );
			submenu.hidden = isMobile() ? ! expanded : false;
		}

		function resetPromocodeExpanded() {
			if ( ! submenu ) {
				return;
			}
			setPromocodeExpanded( isMobile() && 'true' === submenu.dataset.mobileDefaultExpanded );
			if ( promocodeItem ) {
				promocodeItem.classList.remove( 'mobile-categories-default-open' );
			}
		}

		function setBackgroundInert( inert ) {
			if ( 'function' !== typeof ownerDocument.querySelectorAll ) {
				return;
			}
			Array.from( ownerDocument.querySelectorAll( 'main, .breadcrumbs, footer' ) ).forEach( ( element ) => {
				element.inert = inert;
			} );
		}

		function updateMobilePanelOffset() {
			if ( ! isMobile() || 'function' !== typeof ownerDocument.querySelector ) {
				return;
			}
			const header = ownerDocument.querySelector( '.site-wrap' ) || ownerDocument.querySelector( '.header' );
			if ( header && 'function' === typeof header.getBoundingClientRect && siteNavigation.style ) {
				siteNavigation.style.setProperty( '--mobile-nav-top', Math.max( 0, header.getBoundingClientRect().bottom ) + 'px' );
			}
		}

		function closeNavigation( returnFocus ) {
			siteNavigation.classList.remove( 'toggled' );
			ownerDocument.body.classList.remove( 'mobile-navigation-open' );
			setBackgroundInert( false );
			if ( button ) {
				button.setAttribute( 'aria-expanded', 'false' );
				if ( returnFocus && 'function' === typeof button.focus ) {
					button.focus();
				}
			}
			closeDropdowns();
		}

		parentItems.forEach( ( item ) => {
			const link = parentLink( item );
			if ( ! link ) {
				return;
			}
			link.setAttribute( 'aria-haspopup', 'true' );
			link.setAttribute( 'aria-expanded', 'false' );

			item.addEventListener( 'mouseenter', function() {
				if ( ! isMobile() ) {
					closeDropdowns( item );
					setExpanded( item, true );
				}
			} );
			item.addEventListener( 'mouseleave', function() {
				if ( ! isMobile() && ! item.contains( ownerDocument.activeElement ) ) {
					setExpanded( item, false );
				}
			} );
			item.addEventListener( 'focusin', function() {
				if ( ! isMobile() ) {
					closeDropdowns( item );
					setExpanded( item, true );
				}
			} );
			item.addEventListener( 'focusout', function( event ) {
				if ( ! isMobile() && ! item.contains( event.relatedTarget ) ) {
					setExpanded( item, false );
				}
			} );
			item.addEventListener( 'keydown', function( event ) {
				if ( 'ArrowDown' === event.key ) {
					event.preventDefault();
					closeDropdowns( item );
					setExpanded( item, true );
					return;
				}
				if ( 'Escape' === event.key ) {
					event.preventDefault();
					setExpanded( item, false );
					link.focus();
				}
			} );
			link.addEventListener( 'touchstart', function( event ) {
				if ( ! isMobile() || touchedParent === item ) {
					touchedParent = null;
					return;
				}
				event.preventDefault();
				closeDropdowns( item );
				setExpanded( item, true );
				touchedParent = item;
			} );
		} );

		if ( button ) {
			button.addEventListener( 'click', function() {
				const expanded = siteNavigation.classList.toggle( 'toggled' );
				button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				ownerDocument.body.classList.toggle( 'mobile-navigation-open', expanded && isMobile() );
				setBackgroundInert( expanded && isMobile() );
				if ( expanded ) {
					updateMobilePanelOffset();
					resetPromocodeExpanded();
				}
			} );
		}

		if ( submenuButton && submenu ) {
			submenuButton.addEventListener( 'click', function( event ) {
				event.preventDefault();
				event.stopPropagation();
				setPromocodeExpanded( 'true' !== submenuButton.getAttribute( 'aria-expanded' ) );
			} );
			resetPromocodeExpanded();
		}

		Array.from( siteNavigation.querySelectorAll( 'a' ) ).forEach( ( link ) => {
			link.addEventListener( 'click', function() {
				if ( isMobile() ) {
					closeNavigation( false );
				}
			} );
		} );

		if ( favoriteButton && favoriteHelp ) {
			favoriteButton.addEventListener( 'click', function() {
				const isIOS = /iPad|iPhone|iPod/.test( navigator.userAgent ) || ( 'MacIntel' === navigator.platform && navigator.maxTouchPoints > 1 );
				favoriteHelp.textContent = isIOS ? favoriteButton.dataset.iosHelp : favoriteButton.dataset.androidHelp;
				favoriteHelp.hidden = false;
			} );
		}

		ownerDocument.addEventListener( 'click', function( event ) {
			if ( ! siteNavigation.contains( event.target ) ) {
				closeNavigation( false );
			}
		} );
		ownerDocument.addEventListener( 'keydown', function( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}
			if ( siteNavigation.classList.contains( 'toggled' ) ) {
				event.preventDefault();
				closeNavigation( true );
			} else {
				setPromocodeExpanded( false );
				closeDropdowns();
			}
		} );

		if ( 'function' === typeof mobileQuery.addEventListener ) {
			mobileQuery.addEventListener( 'change', function() {
				closeNavigation( false );
				updateMobilePanelOffset();
				resetPromocodeExpanded();
			} );
		}
	}

	window.PromokodikiNavigation = { init };
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			init( document.getElementById( 'site-navigation' ), document );
		} );
	} else {
		init( document.getElementById( 'site-navigation' ), document );
	}
}( window, document ) );
