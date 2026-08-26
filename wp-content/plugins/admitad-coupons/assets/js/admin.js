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
		enhanceTooltips( table );
		enhanceCompanyAutocomplete( table );
		const focusTarget = ( selector && table.querySelector( selector ) ) || table.querySelector( '[data-admitad-focus]' ) || table;
		if ( focusTarget === table && ! table.getAttribute( 'tabindex' ) ) {
			table.setAttribute( 'tabindex', '-1' );
		}
		if ( typeof focusTarget.focus === 'function' ) {
			focusTarget.focus( { preventScroll: true } );
		}
	}

	function metadataFor( element ) {
		return {
			operation: element.dataset.admitadOperation || 'render_fragment',
			page: element.dataset.admitadPage || '',
			fragment: element.dataset.admitadFragment || '',
		};
	}

	function applyMetadata( payload, metadata ) {
		for ( const [ key, value ] of Object.entries( metadata ) ) {
			if ( value ) {
				payload.set( key, value );
			}
		}
		return payload;
	}

	function payloadFromForm( form, submitter, metadata ) {
		const payload = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => {
			if ( typeof value === 'string' && ! [ 'action', '_ajax_nonce', 'operation', 'page', 'fragment' ].includes( key ) ) {
				payload.append( key, value );
			}
		} );
		if ( submitter?.name && ! [ 'action', '_ajax_nonce', 'operation', 'page', 'fragment' ].includes( submitter.name ) ) {
			payload.append( submitter.name, submitter.value );
		}
		return applyMetadata( payload, metadata );
	}

	function actionFor( element ) {
		return element.dataset.admitadAction || element.querySelector?.( '[name="action"]' )?.value || '';
	}

	function tableFor( element ) {
		if ( element?.dataset?.admitadTarget ) {
			return document.querySelector( element.dataset.admitadTarget );
		}
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

	function queueSnapshotContinuation( target, action, data ) {
		const progress = data?.progress;
		if ( action !== 'promokodiki_admitad_admin' || ! progress || typeof progress !== 'object' ) {
			return;
		}
		const operation = {
			previewing: 'preview_step',
			applying: 'snapshot_apply_step',
			rolling_back: 'snapshot_rollback_step',
			running: progress.owner ? 'recovery_migration_step' : '',
		}[ progress.status ];
		const snapshotId = progress.snapshot_id || progress.id;
		if ( ! operation || ( operation !== 'recovery_migration_step' && ( typeof snapshotId !== 'string' || ! snapshotId ) ) ) {
			return;
		}
		window.setTimeout( () => {
			const payload = new URLSearchParams( {
				operation,
				page: operation === 'recovery_migration_step' ? 'admitad-diagnostics' : 'admitad-history',
			} );
			if ( operation === 'recovery_migration_step' ) {
				payload.set( 'owner', progress.owner );
			} else {
				payload.set( 'snapshot_id', snapshotId );
			}
			send( target, action, payload, null, 'replace' );
		}, 0 );
	}

	async function send( target, action, payload, submitter, historyMode ) {
		const table = tableFor( target );
		const owner = table || target;
		const previous = activeRequests.get( owner );
		const controller = new AbortController();
		const selector = focusSelector( document.activeElement );
		const originalDisabled = submitter && previous?.submitter === submitter ? previous.originalDisabled : Boolean( submitter?.disabled );
		const generation = ++requestGeneration;
		let status = 'error';

		if ( previous ) {
			if ( previous.submitter && previous.submitter !== submitter ) {
				previous.submitter.disabled = previous.originalDisabled;
			}
			previous.controller.abort();
		}
		activeRequests.set( owner, { controller, generation, submitter, originalDisabled } );
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
			queueSnapshotContinuation( target, action, data );
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
				submitter.disabled = originalDisabled;
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
		send( form, action, payloadFromForm( form, submitter, metadataFor( form ) ), submitter, 'push' );
	} );

	document.addEventListener( 'click', ( event ) => {
		const copyButton = event.target.closest?.( '[data-admitad-copy-source]' );
		if ( copyButton && copyButton.hasAttribute?.( 'data-admitad-copy-source' ) ) {
			const source = document.querySelector( '[data-admitad-source-description]' );
			const textarea = document.querySelector( '[name="_admitad_shop_manual_description"]' );
			if ( source && textarea ) {
				textarea.value = source.innerHTML;
				if ( window.tinyMCE?.get( textarea.id ) ) window.tinyMCE.get( textarea.id ).setContent( source.innerHTML );
				textarea.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			return;
		}
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
		send( link, action, applyMetadata( payload, metadataFor( link ) ), null, 'push' );
	} );

	window.addEventListener( 'popstate', () => {
		const table = document.querySelector( '[data-admitad-table][data-admitad-action]' );
		if ( ! table ) {
			return;
		}

		const payload = new URLSearchParams( window.location.search );
		payload.delete( 'action' );
		payload.delete( '_ajax_nonce' );
		send( table, table.dataset.admitadAction, applyMetadata( payload, metadataFor( table ) ), null, 'replace' );
	} );

	function setTooltipOpen( trigger, open ) {
		const tooltip = trigger._admitadTooltip;
		if ( ! tooltip ) {
			return;
		}
		tooltip.classList.toggle( 'promokodiki-admitad-tooltip--open', open );
		trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}

	function enhanceTooltips( root = document ) {
		const triggers = root.querySelectorAll ? Array.from( root.querySelectorAll( '[data-admitad-tooltip]' ) ) : [];
		if ( root.matches?.( '[data-admitad-tooltip]' ) ) {
			triggers.unshift( root );
		}
		triggers.forEach( ( trigger ) => {
			if ( trigger.getAttribute( 'data-admitad-tooltip-enhanced' ) ) {
				return;
			}
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
		trigger._admitadTooltip = tooltip;
		trigger.setAttribute( 'data-admitad-tooltip-enhanced', 'true' );
		if ( ! /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/i.test( trigger.tagName ) && ! trigger.getAttribute( 'tabindex' ) ) {
			trigger.setAttribute( 'tabindex', '0' );
		}
		trigger.setAttribute( 'aria-describedby', tooltip.id );
		trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.addEventListener( 'focus', () => setTooltipOpen( trigger, true ) );
		trigger.addEventListener( 'blur', () => setTooltipOpen( trigger, false ) );
		trigger.addEventListener( 'mouseenter', () => setTooltipOpen( trigger, true ) );
		trigger.addEventListener( 'mouseleave', () => setTooltipOpen( trigger, false ) );
		trigger.addEventListener( 'pointerdown', () => {
			trigger._admitadPointerActivation = {
				wasOpen: trigger.getAttribute( 'aria-expanded' ) === 'true',
			};
		} );
		trigger.addEventListener( 'click', () => {
			const pointerActivation = trigger._admitadPointerActivation;
			if ( pointerActivation ) {
				trigger._admitadPointerActivation = false;
				if ( ! pointerActivation.wasOpen && trigger.getAttribute( 'aria-expanded' ) !== 'true' ) {
					setTooltipOpen( trigger, true );
				}
				return;
			}
			setTooltipOpen( trigger, trigger.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
	} );
	}

	function enhanceCompanyAutocomplete( root = document ) {
		const inputs = root.querySelectorAll ? Array.from( root.querySelectorAll( '[data-admitad-company-search]' ) ) : [];
		inputs.forEach( ( input ) => {
			if ( input.getAttribute( 'data-admitad-company-search-enhanced' ) ) {
				return;
			}
			const form = input.closest( 'form' );
			const hidden = form?.querySelector( '[name="campaign_id"]' );
			const list = document.querySelector( `#${ CSS.escape( input.getAttribute( 'aria-controls' ) || '' ) }` );
			if ( ! hidden || ! list ) {
				return;
			}
			let controller = null;
			let timer = null;
			let choices = [];
			let activeIndex = -1;
			let generation = 0;
			input.setAttribute( 'role', 'combobox' );
			input.setAttribute( 'aria-expanded', 'false' );
			input.setAttribute( 'data-admitad-company-search-enhanced', 'true' );

			const close = () => {
				list.hidden = true;
				input.setAttribute( 'aria-expanded', 'false' );
				input.removeAttribute( 'aria-activedescendant' );
				activeIndex = -1;
			};
			const select = ( choice ) => {
				input.value = choice.text;
				hidden.value = String( choice.id );
				close();
			};
			const render = () => {
				list.replaceChildren();
				choices.forEach( ( choice, index ) => {
					const option = document.createElement( 'button' );
					option.type = 'button';
					option.id = `${ input.id }-option-${ index }`;
					option.setAttribute( 'role', 'option' );
					option.setAttribute( 'aria-selected', index === activeIndex ? 'true' : 'false' );
					option.textContent = choice.text;
					option.addEventListener( 'mousedown', ( event ) => event.preventDefault() );
					option.addEventListener( 'click', () => select( choice ) );
					list.append( option );
				} );
				list.setAttribute( 'role', 'listbox' );
				list.hidden = choices.length === 0;
				input.setAttribute( 'aria-expanded', choices.length ? 'true' : 'false' );
			};
			const search = async () => {
				const query = input.value.trim();
				const currentGeneration = ++generation;
				if ( controller ) controller.abort();
				if ( ! query ) {
					choices = [];
					render();
					return;
				}
				controller = new AbortController();
				try {
					const payload = new URLSearchParams( { operation: 'company_search', page: 'admitad-companies', s: query } );
					const data = await request( 'promokodiki_admitad_admin', payload, controller.signal );
					if ( currentGeneration !== generation || query !== input.value.trim() ) return;
					choices = Array.isArray( data.items ) ? data.items : [];
					render();
				} catch ( error ) {
					if ( error.name !== 'AbortError' && currentGeneration === generation ) close();
				}
			};
			input.addEventListener( 'input', () => {
				hidden.value = '';
				++generation;
				if ( timer ) clearTimeout( timer );
				timer = setTimeout( search, 300 );
			} );
			input.addEventListener( 'keydown', ( event ) => {
				if ( event.key === 'Escape' ) {
					event.preventDefault();
					++generation;
					if ( controller ) controller.abort();
					close();
					return;
				}
				if ( ! choices.length ) return;
				if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
					event.preventDefault();
					activeIndex = event.key === 'ArrowDown' ? ( activeIndex + 1 ) % choices.length : ( activeIndex - 1 + choices.length ) % choices.length;
					input.setAttribute( 'aria-activedescendant', `${ input.id }-option-${ activeIndex }` );
					render();
				} else if ( event.key === 'Enter' && activeIndex >= 0 ) {
					event.preventDefault();
					select( choices[ activeIndex ] );
				}
			} );
		} );
	}

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			document.querySelectorAll( '[data-admitad-tooltip-enhanced]' ).forEach( ( trigger ) => setTooltipOpen( trigger, false ) );
		}
	} );
	document.addEventListener( 'pointerdown', ( event ) => {
		document.querySelectorAll( '[data-admitad-tooltip-enhanced]' ).forEach( ( trigger ) => {
			if ( ! trigger.contains( event.target ) && ! trigger._admitadTooltip?.contains( event.target ) ) {
				setTooltipOpen( trigger, false );
			}
		} );
	} );

	enhanceTooltips();
	enhanceCompanyAutocomplete();
	let ruleSearchTimer = null;
	document.addEventListener( 'input', ( event ) => {
		const input = event.target.closest?.( '[data-admitad-rule-search]' );
		if ( ! input ) return;
		const form = input.closest( 'form[data-admitad-ajax]' );
		if ( ! form ) return;
		const paged = form.querySelector( '[name="paged"]' );
		if ( paged ) paged.value = '1';
		if ( ruleSearchTimer ) clearTimeout( ruleSearchTimer );
		ruleSearchTimer = setTimeout( () => form.requestSubmit(), 300 );
	} );
}() );
