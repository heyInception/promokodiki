( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const config = window.PromokodikiSearchConfig;
		const container = document.querySelector( '.promocodes__items' );

		if ( ! config || ! config.ajaxUrl || ! container ) {
			return;
		}

		let isLoading = false;
		let page = 1;
		const noMorePosts = document.createElement( 'p' );
		noMorePosts.className = 'no-more-promocodes';
		noMorePosts.textContent = 'Больше результатов нет';
		noMorePosts.style.display = 'none';
		container.insertAdjacentElement( 'afterend', noMorePosts );

		const searchQuery = new URLSearchParams( window.location.search ).get( 's' ) || '';

		function loadMoreSearchResults() {
			if ( isLoading ) {
				return;
			}

			isLoading = true;
			page++;

			const loader = document.createElement( 'div' );
			loader.className = 'promocodes-loader';
			loader.innerHTML = '<div class="spinner"></div>';
			container.insertAdjacentElement( 'afterend', loader );

			const data = new FormData();
			data.append( 'action', 'load_more_search_results' );
			data.append( 'nonce', config.nonce );
			data.append( 'page', page );
			data.append( 'search_query', searchQuery );

			fetch( config.ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Network response was not ok: ' + response.status );
					}

					return response.text();
				} )
				.then( function ( html ) {
					loader.remove();

					if ( '' === html.trim() ) {
						noMorePosts.style.display = 'block';
						return;
					}

					container.insertAdjacentHTML( 'beforeend', html );
					const responseContent = document.createElement( 'div' );
					responseContent.innerHTML = html;

					if ( ! responseContent.querySelector( '.promocodes__item, .shops-category__item' ) ) {
						noMorePosts.style.display = 'block';
						return;
					}

					isLoading = false;
				} )
				.catch( function ( error ) {
					console.error( 'Error:', error );
					loader.remove();
					isLoading = false;
					page--;
				} );
		}

		let scrollTimeout;
		window.addEventListener( 'scroll', function () {
			clearTimeout( scrollTimeout );
			scrollTimeout = setTimeout( function () {
				if ( isLoading || 'block' === noMorePosts.style.display ) {
					return;
				}

				if ( document.documentElement.scrollHeight - ( window.scrollY + window.innerHeight ) < 300 ) {
					loadMoreSearchResults();
				}
			}, 200 );
		} );

		if ( ! container.querySelector( '.promocodes__item, .shops-category__item' ) ) {
			noMorePosts.style.display = 'block';
		}
	} );
}() );
