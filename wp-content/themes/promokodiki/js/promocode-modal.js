( function () {
	'use strict';

	const config = window.PromokodikiInteractions || window.PromokodikiFilterConfig || {};
	let activeCard = null;
	let returnFocus = null;

	function modal() {
		return document.getElementById( 'promocodeModal' );
	}

	function cardFor( target ) {
		return target && target.closest ? target.closest( '.promocodes__item, .top__item' ) : null;
	}

	function cardByPostId( postId ) {
		return document.querySelector( '.promocodes__item[data-post-id="' + postId + '"], .top__item[data-post-id="' + postId + '"]' );
	}

	function close() {
		const element = modal();
		if ( ! element || ! element.classList.contains( 'show' ) ) {
			return;
		}
		element.classList.remove( 'show' );
		element.style.display = 'none';
		element.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
		if ( returnFocus && 'function' === typeof returnFocus.focus ) {
			returnFocus.focus();
		}
		returnFocus = null;
	}

	function copyText( value ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( value );
		}

		const input = document.getElementById( 'modalPromoCode' );
		if ( input ) {
			input.select();
			return document.execCommand( 'copy' )
				? Promise.resolve()
				: Promise.reject( new Error( 'Clipboard command failed' ) );
		}

		return Promise.reject( new Error( 'Clipboard unavailable' ) );
	}

	function updateUsage( postId, count ) {
		if ( 'function' !== typeof document.querySelectorAll ) {
			return;
		}
		document.querySelectorAll( '[data-post-id="' + postId + '"]' ).forEach( function ( card ) {
			const used = card.querySelector( '.promocodes__used' );
			const topUsed = card.querySelector( '.top__quantity' );
			if ( used ) {
				used.textContent = count + ' Применено';
			}
			if ( topUsed ) {
				topUsed.textContent = count + ' Применено';
			}
		} );
		const modalUsed = document.getElementById( 'modalPromoUsed' );
		if ( modalUsed && activeCard && String( activeCard.dataset.postId ) === String( postId ) ) {
			modalUsed.textContent = String( count );
		}
	}

	function trackUsage( card ) {
		if ( ! card || ! config.ajaxUrl || ! config.nonce || 'function' !== typeof fetch ) {
			return Promise.resolve( null );
		}

		const body = new URLSearchParams( {
			action: 'promokodiki_promo_use',
			post_id: card.dataset.postId || '',
			nonce: config.nonce,
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body,
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( response ) {
				if ( response && response.success && response.data && undefined !== response.data.new_count ) {
					updateUsage( card.dataset.postId, response.data.new_count );
				}
				return response;
			} )
			.catch( function () { return null; } );
	}

	function syncReaction( postId, data ) {
		if ( 'function' !== typeof document.querySelectorAll ) {
			return;
		}
		document.querySelectorAll( '[data-post-id="' + postId + '"]' ).forEach( function ( card ) {
			const like = card.querySelector( '.promocodes__like_yes' );
			const dislike = card.querySelector( '.promocodes__like_no' );
			const likeCount = card.querySelector( '.promocodes__like_yes span' ) || like;
			const dislikeCount = card.querySelector( '.promocodes__like_no span' ) || dislike;
			if ( likeCount ) {
				likeCount.textContent = String( data.likes );
			}
			if ( dislikeCount ) {
				dislikeCount.textContent = String( data.dislikes );
			}
			if ( like ) {
				like.classList.remove( 'is-active' );
				like.setAttribute( 'aria-pressed', 'false' );
				if ( 'like' === data.reaction ) {
					like.classList.add( 'is-active' );
					like.setAttribute( 'aria-pressed', 'true' );
				}
			}
			if ( dislike ) {
				dislike.classList.remove( 'is-active' );
				dislike.setAttribute( 'aria-pressed', 'false' );
				if ( 'dislike' === data.reaction ) {
					dislike.classList.add( 'is-active' );
					dislike.setAttribute( 'aria-pressed', 'true' );
				}
			}
		} );
	}

	function submitVote( button, card ) {
		if ( ! button || ! card || button.classList.contains( 'loading' ) || ! config.ajaxUrl || ! config.nonce ) {
			return;
		}
		const reaction = button.dataset.action || '';
		if ( 'like' !== reaction && 'dislike' !== reaction ) {
			return;
		}

		button.classList.add( 'loading' );
		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				action: 'promokodiki_promo_vote',
				post_id: card.dataset.postId || button.dataset.postId || '',
				reaction: reaction,
				nonce: config.nonce,
			} ),
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( response ) {
				if ( response && response.success && response.data ) {
					syncReaction( card.dataset.postId || button.dataset.postId, response.data );
				}
			} )
			.catch( function () {} )
			.finally( function () { button.classList.remove( 'loading' ); } );
	}

	function openStore( card ) {
		if ( ! card || 'true' === card.dataset.expired ) {
			return;
		}
		const storeUrl = card.dataset.storeUrl || '';
		if ( ! storeUrl ) {
			return;
		}

		window.open( storeUrl, '_blank', 'noopener' );
		trackUsage( card );
	}

	function populateModal( card ) {
		const element = modal();
		if ( ! card || ! element || 'true' === card.dataset.expired || ! card.dataset.code ) {
			return;
		}

		activeCard = card;
		const title = card.querySelector( '.promocodes__title, .top__head' );
		const used = card.querySelector( '.promocodes__used, .top__quantity' );
		const logo = card.querySelector( '.promocodes__imgs img, .top__img img' );
		const usedCount = used ? used.textContent.replace( /\D/g, '' ) : '0';
		const logoElement = document.getElementById( 'modalPromoLogo' );
		const linkElement = document.getElementById( 'modalPromoLink' );
		const unavailable = document.getElementById( 'modalPromoLinkUnavailable' );
		const status = document.getElementById( 'modalPromoCopyStatus' );

		document.getElementById( 'modalPromoTitle' ).textContent = title ? title.textContent.trim() : '';
		document.getElementById( 'modalPromoDesc' ).textContent = card.dataset.description || '';
		document.getElementById( 'modalPromoCode' ).value = card.dataset.code;
		linkElement.textContent = 'Перейти с промокодом';
		linkElement.href = card.dataset.storeUrl || '#';
		linkElement.hidden = ! card.dataset.storeUrl;
		unavailable.hidden = Boolean( card.dataset.storeUrl );
		if ( status ) { status.textContent = ''; }
		document.getElementById( 'modalPromoUsed' ).textContent = usedCount || '0';
		document.getElementById( 'modalPromoExpiry' ).textContent = card.dataset.expiry || 'Срок не указан';
		if ( logoElement ) {
			logoElement.src = logo ? logo.src : '';
			logoElement.alt = logo ? logo.alt : '';
		}

		element.dataset.postId = card.dataset.postId || '';
		element.style.display = 'flex';
		element.classList.add( 'show' );
		element.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
		const dialog = element.querySelector( '.modal-promocode__content' );
		if ( dialog && 'function' === typeof dialog.focus ) { dialog.focus(); }
	}

	window.openPromoModal = function ( postId ) {
		populateModal( cardByPostId( postId ) );
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		const element = modal();
		if ( ! element ) {
			return;
		}

		element.querySelector( '.modal-promocode__close' ).addEventListener( 'click', close );
		element.querySelector( '.modal-promocode__overlay' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
				return;
			}
			if ( 'Tab' === event.key && element.classList.contains( 'show' ) && 'function' === typeof element.querySelectorAll ) {
				const focusable = Array.prototype.filter.call( element.querySelectorAll( 'button:not([hidden]):not([disabled]), a[href]:not([hidden]), input:not([disabled]), [tabindex]:not([tabindex="-1"])' ), function ( item ) { return ! item.hidden; } );
				if ( focusable.length ) {
					const first = focusable[0];
					const last = focusable[focusable.length - 1];
					if ( event.shiftKey && document.activeElement === first ) { event.preventDefault(); last.focus(); }
					else if ( ! event.shiftKey && document.activeElement === last ) { event.preventDefault(); first.focus(); }
				}
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			const reactionButton = event.target.closest( '.promocodes__like' );
			if ( reactionButton ) {
				event.preventDefault();
				submitVote( reactionButton, cardFor( reactionButton ) );
				return;
			}

			const viewButton = event.target.closest( '.promocodes__view' );
			if ( viewButton ) {
				event.preventDefault();
				returnFocus = viewButton;
				populateModal( cardFor( viewButton ) );
				return;
			}

			const storeLink = event.target.closest( '.promocodes__link' );
			if ( storeLink ) {
				event.preventDefault();
				openStore( cardFor( storeLink ) );
			}
		} );

		document.getElementById( 'copyPromoBtn' ).addEventListener( 'click', function ( event ) {
			const button = event.currentTarget || event.target;
			const input = document.getElementById( 'modalPromoCode' );
			const status = document.getElementById( 'modalPromoCopyStatus' );
			copyText( input.value ).then( function () {
				const original = button.textContent;
				button.textContent = 'Скопировано';
				if ( status ) { status.textContent = 'Промокод скопирован'; }
				setTimeout( function () { button.textContent = original; }, 1600 );
			} ).catch( function () {
				input.select();
				if ( status ) { status.textContent = 'Не удалось скопировать. Нажмите Ctrl+C'; }
			} );
		} );

		document.getElementById( 'modalPromoLink' ).addEventListener( 'click', function ( event ) {
			event.preventDefault();
			openStore( activeCard );
		} );
	} );
}() );
