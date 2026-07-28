( function () {
	'use strict';

	const config = window.PromokodikiAdmitadAdmin;
	const activeRequests = new WeakMap();
	let requestGeneration = 0;

	if ( ! config ) {
		return;
	}

	class AdminRequestError extends Error {
		constructor( message, retryable = true ) {
			super( message );
			this.retryable = retryable;
		}
	}

	function isExpiredSession( json, text ) {
		return json?.data?.code === 'invalid_nonce' || String( text ).trim() === '-1';
	}

	function errorMessage( response, json, text ) {
		if ( isExpiredSession( json, text ) ) {
			return 'Сессия истекла. Обновите страницу и повторите действие.';
		}
		if ( typeof json?.data?.message === 'string' && json.data.message ) {
			return json.data.message;
		}
		return 'Не удалось выполнить запрос. Попробуйте ещё раз.';
	}

	async function request( action, payload, signal ) {
		const body = new URLSearchParams();
		body.set( 'action', action );
		body.set( '_ajax_nonce', config.nonce );
		payload.forEach( ( value, key ) => body.append( key, value ) );

		let response;
		let text;
		try {
			response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body,
				signal,
			} );
			text = await response.text();
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				throw error;
			}
			throw new AdminRequestError( 'Не удалось связаться с сервером. Проверьте подключение и повторите попытку.' );
		}

		let json = null;
		try {
			json = JSON.parse( text );
		} catch ( error ) {
			throw new AdminRequestError( errorMessage( response, json, text ), ! isExpiredSession( json, text ) );
		}

		if ( ! response.ok || ! json.success ) {
			throw new AdminRequestError( errorMessage( response, json, text ), ! isExpiredSession( json, text ) );
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

	function showNotice( message, type, retry ) {
		const container = noticeContainer();
		container.className = `promokodiki-admitad-notices notice notice-${ type }`;
		container.replaceChildren();
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = message;
		container.append( paragraph );
		if ( retry ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'button';
			button.textContent = config.i18n.retry;
			button.addEventListener( 'click', retry );
			container.append( button );
		}
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
		if ( focusTarget === table && ! table.getAttribute( 'tabindex' ) ) {
			table.setAttribute( 'tabindex', '-1' );
		}
		if ( typeof focusTarget.focus === 'function' ) {
			focusTarget.focus( { preventScroll: true } );
		}
	}

	function payloadFromForm( form, submitter ) {
		const payload = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => {
			if ( typeof value === 'string' && key !== 'action' && key !== '_ajax_nonce' ) {
				payload.append( key, value );
			}
		} );
		if ( submitter?.name && submitter.name !== 'action' && submitter.name !== '_ajax_nonce' ) {
			payload.append( submitter.name, submitter.value );
		}
		return payload;
	}

	function actionFor( element ) {
		return element.dataset.admitadAction || element.querySelector?.( '[name="action"]' )?.value || '';
	}

	function tableFor( element ) {
		return element.closest?.( '[data-admitad-table]' ) || null;
	}

	function canonicalUrl( value ) {
		if ( typeof value !== 'string' || ! value ) {
			return null;
		}

		let url;
		try {
			url = new URL( value, window.location.href );
		} catch ( error ) {
			return null;
		}
		if (
			url.origin !== window.location.origin ||
			! url.pathname.endsWith( '/edit.php' ) ||
			url.searchParams.get( 'post_type' ) !== 'promocode' ||
			! ( url.searchParams.get( 'page' ) || '' ).startsWith( 'admitad-' )
		) {
			return null;
		}

		return `${ url.pathname }${ url.search }${ url.hash }`;
	}

	async function send( target, action, payload, submitter, historyMode ) {
		const table = tableFor( target );
		const owner = table || target;
		const previous = activeRequests.get( owner );
		const controller = new AbortController();
		const selector = focusSelector( document.activeElement );
		const wasDisabled = submitter ? submitter.disabled : false;
		const generation = ++requestGeneration;
		let status = 'error';

		if ( previous ) {
			previous.controller.abort();
		}
		activeRequests.set( owner, { controller, generation } );
		if ( table ) {
			table.classList.add( 'promokodiki-admitad-is-loading' );
			table.setAttribute( 'aria-busy', 'true' );
			table.setAttribute( 'data-admitad-loading-label', config.i18n.loading );
		}
		if ( submitter ) {
			submitter.disabled = true;
		}

		dispatch( target, 'admitad:before', { action, payload } );
		try {
			const data = await request( action, payload, controller.signal );
			if ( activeRequests.get( owner )?.generation !== generation ) {
				status = 'aborted';
				return;
			}
			if ( table ) {
				replaceTable( table, data.html, selector );
			}
			if ( data.message ) {
				showNotice( data.message, 'success' );
			}
			const url = historyMode === 'push' ? canonicalUrl( data.url ) : null;
			if ( url ) {
				history.pushState( {}, '', url );
			}
			status = 'success';
			dispatch( target, 'admitad:success', { action, payload, data } );
		} catch ( error ) {
			if ( error.name === 'AbortError' || activeRequests.get( owner )?.generation !== generation ) {
				status = 'aborted';
			} else {
				const message = error instanceof AdminRequestError ? error.message : 'Не удалось выполнить запрос. Попробуйте ещё раз.';
				showNotice(
					message,
					'error',
					error instanceof AdminRequestError && error.retryable ? () => send( target, action, payload, submitter, historyMode ) : null
				);
				dispatch( target, 'admitad:error', { action, payload, error, message } );
			}
		} finally {
			const isCurrent = activeRequests.get( owner )?.generation === generation;
			if ( isCurrent ) {
				activeRequests.delete( owner );
			}
			if ( table && isCurrent ) {
				table.classList.remove( 'promokodiki-admitad-is-loading' );
				table.removeAttribute( 'aria-busy' );
				table.removeAttribute( 'data-admitad-loading-label' );
			}
			if ( submitter && isCurrent ) {
				submitter.disabled = wasDisabled;
			}
			dispatch( target, 'admitad:complete', { action, payload, status } );
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
		const submitter = event.submitter || form.querySelector( '[type="submit"]' );
		send( form, action, payloadFromForm( form, submitter ), submitter, 'push' );
	} );

	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest?.( 'a[data-admitad-ajax]' );
		if (
			! link ||
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		const action = actionFor( link );
		if ( ! action ) {
			showNotice( 'Не удалось определить действие ссылки.', 'error' );
			return;
		}
		const payload = new URLSearchParams( new URL( link.href, window.location.href ).searchParams );
		payload.delete( 'action' );
		payload.delete( '_ajax_nonce' );
		send( link, action, payload, null, 'push' );
	} );

	window.addEventListener( 'popstate', () => {
		const table = document.querySelector( '[data-admitad-table][data-admitad-action]' );
		if ( ! table ) {
			return;
		}

		const payload = new URLSearchParams( window.location.search );
		payload.delete( 'action' );
		payload.delete( '_ajax_nonce' );
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
		if ( ! /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/i.test( trigger.tagName ) && ! trigger.getAttribute( 'tabindex' ) ) {
			trigger.setAttribute( 'tabindex', '0' );
		}
		trigger.setAttribute( 'aria-describedby', tooltip.id );
	} );
}() );
