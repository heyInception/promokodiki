'use strict';

const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

class ClassList {
	constructor() { this.values = new Set(); }
	add( value ) { this.values.add( value ); }
	remove( value ) { this.values.delete( value ); }
	toggle( value, force ) {
		const enabled = undefined === force ? ! this.values.has( value ) : Boolean( force );
		if ( enabled ) { this.add( value ); } else { this.remove( value ); }
		return enabled;
	}
	contains( value ) { return this.values.has( value ); }
}

function element() {
	return {
		attributes: {}, children: [], classList: new ClassList(), dataset: {}, hidden: false,
		listeners: {}, textContent: '', selectorMap: {}, selectorAllMap: {},
		style: { values: {}, setProperty( name, value ) { this.values[ name ] = value; } },
		addEventListener( name, callback ) { ( this.listeners[ name ] ||= [] ).push( callback ); },
		contains( candidate ) { return this === candidate || this.children.some( ( child ) => child.contains( candidate ) ); },
		dispatch( name, properties = {} ) {
			const event = Object.assign( { target: this, preventDefault() {}, stopPropagation() {} }, properties );
			( this.listeners[ name ] || [] ).forEach( ( callback ) => callback( event ) );
		},
		getAttribute( name ) { return this.attributes[ name ]; },
		setAttribute( name, value ) { this.attributes[ name ] = String( value ); },
		querySelector( selector ) { return this.selectorMap[ selector ] || null; },
		querySelectorAll( selector ) { return this.selectorAllMap[ selector ] || []; },
		getElementsByTagName() { return []; },
	};
}

const navigation = element();
const body = element();
const menuButton = element();
const menu = element();
const submenuButton = element();
const submenu = element();
const promocodeItem = element();
const favoriteButton = element();
const favoriteHelp = element();
const menuLink = element();
const nativeParent = element();
const nativeParentLink = element();
const pageMain = element();
const header = element();

menuLink.tagName = 'a';
nativeParentLink.tagName = 'a';
nativeParent.children = [ nativeParentLink ];
header.getBoundingClientRect = () => ( { bottom: 96 } );
submenu.dataset.mobileDefaultExpanded = 'true';
favoriteButton.dataset.iosHelp = 'iOS help';
favoriteButton.dataset.androidHelp = 'Android help';

navigation.children = [ menuButton, menu, submenuButton, submenu, promocodeItem, favoriteButton, favoriteHelp, menuLink ];
navigation.selectorMap = {
	'.menu-toggle': menuButton,
	'.nav-menu': menu,
	'[data-promocode-submenu-toggle]': submenuButton,
	'[data-promocode-submenu]': submenu,
	'.menu-item--promocodes': promocodeItem,
	'[data-mobile-favorite]': favoriteButton,
	'[data-mobile-favorite-help]': favoriteHelp,
};
navigation.selectorAllMap = { 'a': [ menuLink ] };
menu.selectorAllMap = { '.menu-item-has-children:not(.menu-item--promocodes), .page_item_has_children:not(.menu-item--promocodes)': [ nativeParent ] };

const documentListeners = {};
const document = {
	body, readyState: 'loading', activeElement: null,
	addEventListener( name, callback ) { ( documentListeners[ name ] ||= [] ).push( callback ); },
	getElementById( id ) { return 'site-navigation' === id ? navigation : null; },
	querySelector( selector ) { return '.site-wrap' === selector ? header : null; },
	querySelectorAll( selector ) { return 'main, .breadcrumbs, footer' === selector ? [ pageMain ] : []; },
};
const window = { matchMedia() { return { matches: true, addEventListener() {} }; } };
const context = {
	document,
	navigator: { maxTouchPoints: 5, platform: 'iPhone', userAgent: 'Mozilla/5.0 (iPhone)' },
	window,
};
context.globalThis = context;

const script = fs.readFileSync( path.join( __dirname, '../js/navigation.js' ), 'utf8' );
vm.runInNewContext( script, context, { filename: 'navigation.js' } );
assert.ok( window.PromokodikiNavigation, 'navigation controller is exposed for initialization' );
window.PromokodikiNavigation.init( navigation, document );

assert.equal( submenuButton.getAttribute( 'aria-expanded' ), 'true', 'configured submenu starts expanded on mobile' );
assert.equal( submenu.hidden, false, 'expanded submenu is visible' );
assert.equal( promocodeItem.classList.contains( 'mobile-categories-default-open' ), false, 'JavaScript takes ownership of the server default state' );

menuButton.dispatch( 'click' );
assert.equal( navigation.classList.contains( 'toggled' ), true, 'menu button opens the panel' );
assert.equal( body.classList.contains( 'mobile-navigation-open' ), true, 'open mobile panel locks page scrolling' );
assert.equal( pageMain.inert, true, 'open mobile panel makes background content inert' );
assert.equal( navigation.style.values[ '--mobile-nav-top' ], '96px', 'panel starts below the measured header' );

submenuButton.dispatch( 'click' );
assert.equal( submenuButton.getAttribute( 'aria-expanded' ), 'false', 'submenu button collapses categories independently' );
assert.equal( submenu.hidden, true, 'collapsed submenu is hidden' );

let firstTouchPrevented = false;
nativeParentLink.dispatch( 'touchstart', { preventDefault() { firstTouchPrevented = true; } } );
assert.equal( firstTouchPrevented, true, 'first touch opens a native WordPress child menu' );
assert.equal( nativeParentLink.getAttribute( 'aria-expanded' ), 'true', 'native child menu exposes its state' );
let secondTouchPrevented = false;
nativeParentLink.dispatch( 'touchstart', { preventDefault() { secondTouchPrevented = true; } } );
assert.equal( secondTouchPrevented, false, 'second touch follows the native parent link' );

( documentListeners.keydown || [] ).forEach( ( callback ) => callback( { key: 'Escape', preventDefault() {} } ) );
assert.equal( navigation.classList.contains( 'toggled' ), false, 'Escape closes the mobile panel' );
assert.equal( body.classList.contains( 'mobile-navigation-open' ), false, 'Escape restores page scrolling' );
assert.equal( pageMain.inert, false, 'Escape restores background keyboard navigation' );

menuButton.dispatch( 'click' );
menuLink.dispatch( 'click' );
assert.equal( navigation.classList.contains( 'toggled' ), false, 'following a link closes the mobile panel' );

favoriteButton.dispatch( 'click' );
assert.equal( favoriteHelp.hidden, false, 'favorite instructions become visible' );
assert.equal( favoriteHelp.textContent, 'iOS help', 'iOS receives localized platform-specific instructions' );

console.log( 'Navigation interaction contract passed.' );
