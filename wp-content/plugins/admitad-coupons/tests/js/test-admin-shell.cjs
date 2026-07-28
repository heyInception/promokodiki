const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const test = require( 'node:test' );
const vm = require( 'node:vm' );

const source = fs.readFileSync(
	path.resolve( __dirname, '../../assets/js/admin.js' ),
	'utf8'
);

function cssForTest() {
	return fs.readFileSync( path.resolve( __dirname, '../../assets/css/admin.css' ), 'utf8' );
}

class MockElement {
	constructor( tagName, options = {} ) {
		this.tagName = tagName;
		this.dataset = options.dataset || {};
		this.parent = options.parent || null;
		this.attributes = new Map();
		this.children = [];
		this.listeners = new Map();
		this.queries = new Map();
		this.classNames = new Set();
		this.classList = {
			add: ( name ) => this.classNames.add( name ),
			remove: ( name ) => this.classNames.delete( name ),
			contains: ( name ) => this.classNames.has( name ),
			toggle: ( name, force ) => {
				if ( force ) this.classNames.add( name );
				else this.classNames.delete( name );
			},
		};
		this.disabled = false;
		this.name = options.name || '';
		this.value = options.value || '';
		this.href = options.href || '';
		this.id = options.id || '';
		this.formEntries = options.formEntries || [];
		this.textContent = '';
		this.focused = false;
		this.innerHTML = '';
	}

	closest( selector ) {
		if ( selector === 'form[data-admitad-ajax]' && this.tagName === 'form' && this.dataset.admitadAjax !== undefined ) {
			return this;
		}
		if ( selector === 'a[data-admitad-ajax]' && this.tagName === 'a' && this.dataset.admitadAjax !== undefined ) {
			return this;
		}
		if ( selector === '[data-admitad-table]' ) {
			return this.tagName === 'table' && this.dataset.admitadTable !== undefined ? this : this.parent?.closest( selector ) || null;
		}
		return null;
	}

	querySelector( selector ) {
		return this.queries.get( selector ) || null;
	}

	setAttribute( name, value ) {
		this.attributes.set( name, String( value ) );
	}

	getAttribute( name ) {
		return this.attributes.get( name ) || null;
	}

	removeAttribute( name ) {
		this.attributes.delete( name );
	}

	append( child ) {
		this.children.push( child );
		child.parent = this;
	}

	prepend( child ) {
		this.children.unshift( child );
		child.parent = this;
	}

	replaceChildren( ...children ) {
		this.children = children;
		children.forEach( ( child ) => {
			child.parent = this;
		} );
	}

	after( child ) {
		this.afterChild = child;
	}

	addEventListener( name, callback ) {
		this.listeners.set( name, callback );
	}

	dispatchEvent( event ) {
		this.dispatched = this.dispatched || [];
		this.dispatched.push( event );
		return true;
	}

	focus() {
		this.focused = true;
	}
}

function createRuntime( fetchImplementation, tooltipTriggers = [] ) {
	const documentListeners = new Map();
	const windowListeners = new Map();
	const document = {
		body: new MockElement( 'body' ),
		activeElement: null,
		queries: new Map(),
		addEventListener: ( name, callback ) => documentListeners.set( name, callback ),
		querySelector: ( selector ) => document.queries.get( selector ) || null,
		querySelectorAll: ( selector ) => {
			if ( selector === '[data-admitad-tooltip]' ) return tooltipTriggers;
			if ( selector === '[data-admitad-tooltip-enhanced]' ) {
				return tooltipTriggers.filter( ( trigger ) => trigger.getAttribute( 'data-admitad-tooltip-enhanced' ) );
			}
			return [];
		},
		createElement: ( tagName ) => new MockElement( tagName ),
	};
	const historyCalls = [];
	const window = {
		PromokodikiAdmitadAdmin: {
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'nonce',
			i18n: { loading: 'Loading', retry: 'Retry' },
		},
		location: {
			href: 'https://example.test/wp-admin/edit.php?post_type=promocode&page=admitad-history',
			search: '?post_type=promocode&page=admitad-history',
			origin: 'https://example.test',
		},
		history: { pushState: ( ...args ) => historyCalls.push( args ) },
		addEventListener: ( name, callback ) => windowListeners.set( name, callback ),
	};
	class MockFormData {
		constructor( form ) {
			this.entries = form.formEntries;
		}

		forEach( callback ) {
			this.entries.forEach( ( entry ) => callback( entry[ 1 ], entry[ 0 ] ) );
		}
	}
	class MockCustomEvent {
		constructor( type, options ) {
			this.type = type;
			this.detail = options.detail;
		}
	}
	const context = {
		AbortController,
		CSS: { escape: ( value ) => value },
		CustomEvent: MockCustomEvent,
		FormData: MockFormData,
		URL,
		URLSearchParams,
		console,
		document,
		fetch: fetchImplementation,
		history: window.history,
		window,
	};
	vm.runInNewContext( source, context, { filename: 'admin.js' } );
	return { document, documentListeners, historyCalls, window, windowListeners };
}

function response( data, options = {} ) {
	return {
		ok: options.ok ?? true,
		status: options.status ?? 200,
		json: async () => data,
		text: async () => JSON.stringify( data ),
	};
}

async function flush() {
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	await new Promise( ( resolve ) => setImmediate( resolve ) );
}

function submit( runtime, form, submitter ) {
	const event = {
		target: form,
		submitter,
		preventDefault() { this.prevented = true; },
	};
	runtime.documentListeners.get( 'submit' )( event );
	return event;
}

test( 'serializes repeated form fields and the clicked submitter', async () => {
	let body;
	const runtime = createRuntime( async ( _url, options ) => {
		body = options.body;
		return response( { success: true, data: {} } );
	} );
	const form = new MockElement( 'form', {
		dataset: {
			admitadAjax: '',
			admitadAction: 'promokodiki_admitad_admin',
			admitadOperation: 'render_fragment',
			admitadPage: 'admitad-settings',
			admitadFragment: 'foundation',
		},
		formEntries: [ [ 'term_id', '1' ], [ 'term_id', '2' ] ],
	} );
	const submitter = new MockElement( 'button', { name: 'intent', value: 'save' } );
	form.queries.set( '[type="submit"]', submitter );

	submit( runtime, form, submitter );
	await flush();

	assert.deepEqual( [ ...body.getAll( 'term_id' ) ], [ '1', '2' ] );
	assert.equal( body.get( 'operation' ), 'render_fragment' );
	assert.equal( body.get( 'intent' ), 'save' );
	assert.equal( body.get( 'page' ), 'admitad-settings' );
	assert.equal( body.get( 'fragment' ), 'foundation' );

	for ( const reservedName of [ 'action', '_ajax_nonce' ] ) {
		const reservedSubmitter = new MockElement( 'button', { name: reservedName, value: 'override' } );
		submit( runtime, form, reservedSubmitter );
		await flush();
		assert.deepEqual( body.getAll( 'action' ), [ 'promokodiki_admitad_admin' ] );
		assert.deepEqual( body.getAll( '_ajax_nonce' ), [ 'nonce' ] );
	}
} );

test( 'only the current table request may replace content or push a canonical URL', async () => {
	const pending = [];
	const runtime = createRuntime( () => new Promise( ( resolve ) => pending.push( resolve ) ) );
	const table = new MockElement( 'table', { dataset: { admitadTable: '' } } );
	const form = new MockElement( 'form', {
		dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
		parent: table,
	} );
	const submitter = new MockElement( 'button' );
	form.queries.set( '[type="submit"]', submitter );

	submit( runtime, form, submitter );
	submit( runtime, form, submitter );
	assert.equal( table.getAttribute( 'data-admitad-loading-label' ), 'Loading' );
	pending[ 1 ]( response( { success: true, data: { html: 'new', url: '/wp-admin/edit.php?post_type=promocode&page=admitad-history' } } ) );
	await flush();
	pending[ 0 ]( response( { success: true, data: { html: 'old', url: '/wp-admin/edit.php?post_type=promocode&page=admitad-history&paged=2' } } ) );
	await flush();

	assert.equal( table.innerHTML, 'new' );
	assert.equal( runtime.historyCalls.length, 1 );
	assert.equal( submitter.disabled, false );
} );

test( 'a different superseding submitter is restored immediately and the latest restores on completion', async () => {
	const pending = [];
	const runtime = createRuntime( () => new Promise( ( resolve ) => pending.push( resolve ) ) );
	const table = new MockElement( 'table', { dataset: { admitadTable: '' } } );
	const form = new MockElement( 'form', { dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' }, parent: table } );
	const first = new MockElement( 'button', { name: 'first', value: '1' } );
	const second = new MockElement( 'button', { name: 'second', value: '2' } );

	submit( runtime, form, first );
	assert.equal( first.disabled, true );
	submit( runtime, form, second );
	assert.equal( first.disabled, false );
	assert.equal( second.disabled, true );
	pending[ 1 ]( response( { success: true, data: {} } ) );
	await flush();
	assert.equal( second.disabled, false );
} );

test( 'enhances initial tooltips for touch, Escape, and dynamic fragment replacement', () => {
	const trigger = new MockElement( 'span' );
	trigger.setAttribute( 'data-admitad-tooltip', 'Touch help' );
	const runtime = createRuntime( async () => response( { success: true, data: {} } ), [ trigger ] );
	assert.match( trigger.getAttribute( 'aria-describedby' ), /^promokodiki-admitad-tooltip-/ );
	assert.equal( trigger.getAttribute( 'aria-expanded' ), 'false' );
	trigger.listeners.get( 'focus' )();
	assert.equal( trigger.getAttribute( 'aria-expanded' ), 'true' );
	assert.equal( trigger.afterChild.classList.contains( 'promokodiki-admitad-tooltip--open' ), true );
	trigger.listeners.get( 'pointerdown' )();
	trigger.listeners.get( 'click' )();
	assert.equal( trigger.getAttribute( 'aria-expanded' ), 'true' );
	runtime.documentListeners.get( 'keydown' )( { key: 'Escape' } );
	assert.equal( trigger.getAttribute( 'aria-expanded' ), 'false' );
	trigger.listeners.get( 'pointerdown' )();
	trigger.listeners.get( 'click' )();
	assert.equal( trigger.getAttribute( 'aria-expanded' ), 'true' );

	// Fragment replacement re-invokes the same idempotent enhancer.
	assert.match( source, /function enhanceTooltips\(/ );
	assert.match( source, /enhanceTooltips\( table \)/ );
	assert.match( source, /data-admitad-tooltip-enhanced/ );
	assert.match( cssForTest(), /aria-expanded="true"/ );
	assert.doesNotMatch( cssForTest(), /\[data-admitad-tooltip\]:hover/ );
} );

test( 'presenter status classes have matching semantic CSS rules', () => {
	const css = fs.readFileSync( path.resolve( __dirname, '../../assets/css/admin.css' ), 'utf8' );
	for ( const state of [ 'neutral', 'info', 'success', 'warning', 'error' ] ) {
		assert.match( css, new RegExp( `promokodiki-admitad-status--${ state }` ) );
	}
} );

test( 'does not choose an unrelated page table and makes the replacement target focusable', async () => {
	const runtime = createRuntime( async () => response( { success: true, data: { html: 'fragment' } } ) );
	const unrelated = new MockElement( 'table', { dataset: { admitadTable: '' } } );
	runtime.document.queries.set( '[data-admitad-table]', unrelated );
	const outsideForm = new MockElement( 'form', {
		dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
	} );
	const submitter = new MockElement( 'button' );
	outsideForm.queries.set( '[type="submit"]', submitter );

	submit( runtime, outsideForm, submitter );
	await flush();
	assert.equal( unrelated.innerHTML, '' );
	assert.equal( unrelated.classList.contains( 'promokodiki-admitad-is-loading' ), false );

	const table = new MockElement( 'table', { dataset: { admitadTable: '' } } );
	const insideForm = new MockElement( 'form', {
		dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
		parent: table,
	} );
	insideForm.queries.set( '[type="submit"]', submitter );
	submit( runtime, insideForm, submitter );
	await flush();
	assert.equal( table.getAttribute( 'tabindex' ), '-1' );
	assert.equal( table.focused, true );
} );

test( 'accepts only primary unmodified links and trusted canonical admin URLs', async () => {
	let requests = 0;
	const runtime = createRuntime( async () => {
		requests += 1;
		return response( { success: true, data: { url: requests === 1 ? 'https://attacker.test/' : ':' } } );
	} );
	const link = new MockElement( 'a', {
		dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
		href: 'https://example.test/wp-admin/edit.php?post_type=promocode&page=admitad-history',
	} );
	const modified = {
		target: link,
		button: 1,
		ctrlKey: false,
		metaKey: false,
		shiftKey: false,
		altKey: false,
		preventDefault() { this.prevented = true; },
	};
	runtime.documentListeners.get( 'click' )( modified );
	await flush();
	assert.equal( modified.prevented, undefined );
	assert.equal( requests, 0 );

	const primary = { ...modified, button: 0, preventDefault() { this.prevented = true; } };
	runtime.documentListeners.get( 'click' )( primary );
	await flush();
	assert.equal( primary.prevented, true );
	assert.equal( runtime.historyCalls.length, 0 );
	runtime.documentListeners.get( 'click' )( primary );
	await flush();
	assert.equal( runtime.historyCalls.length, 0 );
} );

test( 'normalizes failed responses into a Russian retryable notice and completes with error status', async () => {
	let attempts = 0;
	const runtime = createRuntime( async () => {
		attempts += 1;
		if ( attempts === 2 ) {
			return response( { success: true, data: {} } );
		}
		return {
			ok: false,
			status: 403,
			json: async () => { throw new SyntaxError( 'raw parser failure' ); },
			text: async () => 'not-json',
		};
	} );
	const notices = new MockElement( 'div' );
	runtime.document.queries.set( '[data-admitad-notices]', notices );
	const form = new MockElement( 'form', {
		dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
	} );
	const submitter = new MockElement( 'button' );
	form.queries.set( '[type="submit"]', submitter );

	submit( runtime, form, submitter );
	await flush();

	assert.match( notices.children[ 0 ].textContent, /Сессия|Не удалось/ );
	assert.equal( notices.children[ 1 ].textContent, 'Retry' );
	const complete = form.dispatched.find( ( event ) => event.type === 'admitad:complete' );
	assert.equal( complete.detail.status, 'error' );
	await notices.children[ 1 ].listeners.get( 'click' )();
	await flush();
	assert.equal( attempts, 2 );
} );

test( 'does not offer a stale-nonce retry for invalid_nonce or WordPress -1 responses', async () => {
	for ( const failedResponse of [
		response( { success: false, data: { code: 'invalid_nonce', message: 'Сессия истекла.' } }, { ok: false, status: 403 } ),
		{ ok: false, status: 403, text: async () => '-1' },
	] ) {
		const runtime = createRuntime( async () => failedResponse );
		const notices = new MockElement( 'div' );
		runtime.document.queries.set( '[data-admitad-notices]', notices );
		const form = new MockElement( 'form', {
			dataset: { admitadAjax: '', admitadAction: 'promokodiki_admitad_admin' },
		} );
		const submitter = new MockElement( 'button' );
		form.queries.set( '[type="submit"]', submitter );

		submit( runtime, form, submitter );
		await flush();

		assert.match( notices.children[ 0 ].textContent, /Сессия/ );
		assert.equal( notices.children.length, 1 );
	}
} );
