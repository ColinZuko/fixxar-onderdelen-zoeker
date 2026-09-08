( function () {
	'use strict';

	function debounce( fn, wait ) {
		var timer;
		return function () {
			var args = arguments;
			var ctx = this;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( ctx, args );
			}, wait );
		};
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function renderResults( container, products ) {
		if ( ! products.length ) {
			container.innerHTML = '';
			return;
		}

		var html = '<div class="fxr-zoeker__grid">';
		products.forEach( function ( p ) {
			html +=
				'<a class="fxr-zoeker__card" href="' + p.permalink + '">' +
					'<img class="fxr-zoeker__card-img" src="' + p.image + '" alt="' + escapeHtml( p.title ) + '" loading="lazy" />' +
					'<div class="fxr-zoeker__card-body">' +
						'<h3 class="fxr-zoeker__card-title">' + escapeHtml( p.title ) + '</h3>' +
						'<div class="fxr-zoeker__card-price">' + p.price_html + '</div>' +
						( p.in_stock ? '' : '<div class="fxr-zoeker__card-stock">Niet op voorraad</div>' ) +
					'</div>' +
				'</a>';
		} );
		html += '</div>';
		container.innerHTML = html;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrappers = document.querySelectorAll( '.fxr-zoeker' );

		wrappers.forEach( function ( wrapper ) {
			var input = wrapper.querySelector( '.fxr-zoeker__input' );
			var loader = wrapper.querySelector( '.fxr-zoeker__loader' );
			var statusText = wrapper.querySelector( '.fxr-zoeker__status-text' );
			var results = wrapper.querySelector( '.fxr-zoeker__results' );
			var minChars = parseInt( wrapper.getAttribute( 'data-min-chars' ), 10 ) || 2;

			var doSearch = debounce( function () {
				var term = input.value.trim();

				if ( term.length < minChars ) {
					loader.hidden = true;
					statusText.textContent = '';
					results.innerHTML = '';
					return;
				}

				// Loader-animatie i.p.v. "Zoeken..."-tekst, terwijl het verzoek loopt.
				loader.hidden = false;
				statusText.textContent = '';

				var body = new URLSearchParams();
				body.append( 'action', 'fxr_zoek_onderdelen' );
				body.append( 'nonce', fxrZoeker.nonce );
				body.append( 'term', term );

				fetch( fxrZoeker.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				} )
					.then( function ( res ) {
						return res.json();
					} )
					.then( function ( json ) {
						loader.hidden = true;

						if ( ! json.success ) {
							statusText.textContent = 'Er ging iets mis, probeer het opnieuw.';
							results.innerHTML = '';
							return;
						}

						var data = json.data;
						statusText.textContent = data.message || ( data.products.length + ' onderdelen gevonden' );
						renderResults( results, data.products );
					} )
					.catch( function () {
						loader.hidden = true;
						statusText.textContent = 'Er ging iets mis, probeer het opnieuw.';
					} );
			}, 350 );

			input.addEventListener( 'input', doSearch );
		} );
	} );
} )();
