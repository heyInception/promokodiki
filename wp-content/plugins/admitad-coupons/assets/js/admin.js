( function () {
	'use strict';

	const config = window.PromokodikiAdmitadAdmin;
	const tableRequests = new WeakMap();

	if ( ! config ) {
		return;
	}

	async function request( action, payload, signal ) {
		const body = new URLSearchParams( { action, _ajax_nonce: config.nonce, ...payload } );
		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body,
			signal,
		} );
		const json = await response.json();
		if ( ! response.ok || ! json.success ) {
			throw new Error( json?.data?.message || config.i18n.error || 'Не удалось выполнить запрос.' );
		}
		return json.data;
	}

	function dispatch( target, name, detail ) {
		target.dispatchEvent( new CustomEvent( name, { bubbles: true, detail } ) );
	}

	function noticeContainer() {
		let container = document.querySelector( '[data-admitad-notices]' );
		if ( container ) {
			return container;
		}

		container = document.createElement( 'div' );
		container.className = 'promokodiki-admitad-notices';
		container.setAttribute( 'data-admitad-notices', '' );
		container.setAttribute( 'role', 'status' );
		container.setAttribute( 'aria-live', 'polite' );
		container.setAttribute( 'aria-atomic', 'true' );
		( document.querySelector( '.promokodiki-admitad-admin' ) || document.body ).prepend( container );
		return container;
	}

	function showNotice( message, type ) {
		const container = noticeContainer();
		container.className = `promokodiki-admitad-notices notice notice-${ type }`;
		container.replaceChildren();
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = message;
		container.append( paragraph );
	}

	function focusSelector( element ) {
		if ( ! element || ! element.closest( '[data-admitad-table]' ) ) {
			return '';
		}

		if ( element.id ) {
			return `#${ CSS.escape( element.id ) }`;
		}
		if ( element.name ) {
			return `[name="${ CSS.escape( element.name ) }"]`;
		}
		return '';
	}

	function replaceTable( table, html, selector ) {
		if ( typeof html !== 'string' ) {
			return;
		}

		table.innerHTML = html;
		const focusTarget = ( selector && table.querySelector( selector ) ) || table.querySelector( '[data-admitad-focus]' ) || table;
		if ( typeof focusTarget.focus === 'function' ) {
			focusTarget.focus( { preventScroll: true } );
		}
	}

	function payloadFromForm( form ) {
		const payload = {};
		new FormData( form ).forEach( ( value, key ) => {
			if ( typeof value === 'string' && key !== 'action' && key !== '_ajax_nonce' ) {
				payload[ key ] = value;
			}
		} );
		return payload;
	}

	function actionFor( element ) {
		return element.dataset.admitadAction || element.querySelector?.( '[name="action"]' )?.value || '';
	}

	function tableFor( element ) {
		return element.closest?.( '[data-admitad-table]' ) || document.querySelector( '[data-admitad-table]' );
	}

	async function send( target, action, payload, submitter, historyMode ) {
		const table = tableFor( target );
		const previous = table && tableRequests.get( table );
		const controller = new AbortController();
		const selector = focusSelector( document.activeElement );
		const wasDisabled = submitter ? submitter.disabled : false;

		if ( previous ) {
			previous.abort();
		}
		if ( table ) {
			tableRequests.set( table, controller );
			table.classList.add( 'promokodiki-admitad-is-loading' );
			table.setAttribute( 'aria-busy', 'true' );
		}
		if ( submitter ) {
			submitter.disabled = true;
		}

		dispatch( target, 'admitad:before', { action, payload } );
		try {
			const data = await request( action, payload, controller.signal );
			if ( table ) {
				replaceTable( table, data.html, selector );
			}
			if ( data.message ) {
				showNotice( data.message, 'success' );
			}
			if ( historyMode === 'push' && data.url ) {
				history.pushState( {}, '', data.url );
			}
			dispatch( target, 'admitad:success', { action, payload, data } );
		} catch ( error ) {
			if ( error.name !== 'AbortError' ) {
				showNotice( error.message, 'error' );
				dispatch( target, 'admitad:error', { action, payload, error } );
			}
		} finally {
			if ( table && tableRequests.get( table ) === controller ) {
				tableRequests.delete( table );
				table.classList.remove( 'promokodiki-admitad-is-loading' );
				table.removeAttribute( 'aria-busy' );
			}
			if ( submitter ) {
				submitter.disabled = wasDisabled;
			}
			dispatch( target, 'admitad:complete', { action, payload } );
		}
	}

	document.addEventListener( 'submit', ( event ) => {
		const form = event.target.closest?.( 'form[data-admitad-ajax]' );
		if ( ! form ) {
			return;
		}

		event.preventDefault();
		const action = actionFor( form );
		if ( ! action ) {
			showNotice( 'Не удалось определить действие формы.', 'error' );
			return;
		}
		send( form, action, payloadFromForm( form ), event.submitter || form.querySelector( '[type="submit"]' ), 'push' );
	} );

	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest?.( 'a[data-admitad-ajax]' );
		if ( ! link || event.defaultPrevented ) {
			return;
		}

		event.preventDefault();
		const action = actionFor( link );
		if ( ! action ) {
			showNotice( 'Не удалось определить действие ссылки.', 'error' );
			return;
		}
		const payload = Object.fromEntries( new URL( link.href, window.location.href ).searchParams.entries() );
		delete payload.action;
		delete payload._ajax_nonce;
		send( link, action, payload, null, 'push' );
	} );

	window.addEventListener( 'popstate', () => {
		const table = document.querySelector( '[data-admitad-table][data-admitad-action]' );
		if ( ! table ) {
			return;
		}

		const payload = Object.fromEntries( new URLSearchParams( window.location.search ).entries() );
		delete payload.action;
		delete payload._ajax_nonce;
		send( table, table.dataset.admitadAction, payload, null, 'replace' );
	} );

	document.querySelectorAll( '[data-admitad-tooltip]' ).forEach( ( trigger ) => {
		const text = trigger.getAttribute( 'data-admitad-tooltip' );
		if ( ! text ) {
			return;
		}
		const tooltip = document.createElement( 'span' );
		tooltip.className = 'promokodiki-admitad-tooltip';
		tooltip.id = `promokodiki-admitad-tooltip-${ Math.random().toString( 36 ).slice( 2 ) }`;
		tooltip.setAttribute( 'role', 'tooltip' );
		tooltip.textContent = text;
		trigger.after( tooltip );
		trigger.setAttribute( 'aria-describedby', tooltip.id );
	} );
}() );
