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

	function setOptions( select, items, placeholder ) {
		var html = '<option value="">' + escapeHtml( placeholder ) + '</option>';
		items.forEach( function ( item ) {
			html += '<option value="' + item.id + '">' + escapeHtml( item.name ) + '</option>';
		} );
		select.innerHTML = html;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrappers = document.querySelectorAll( '.fxr-zoeker' );

		wrappers.forEach( function ( wrapper ) {
			var taxonomy = wrapper.getAttribute( 'data-taxonomy' );
			var minChars = parseInt( wrapper.getAttribute( 'data-min-chars' ), 10 ) || 1;

			var merkSelect = wrapper.querySelector( '.fxr-zoeker__select--merk' );
			var serieSelect = wrapper.querySelector( '.fxr-zoeker__select--serie' );
			var nummerInput = wrapper.querySelector( '.fxr-zoeker__input--nummer' );
			var loader = wrapper.querySelector( '.fxr-zoeker__loader' );
			var statusText = wrapper.querySelector( '.fxr-zoeker__status-text' );
			var results = wrapper.querySelector( '.fxr-zoeker__results' );

			function resetResults() {
				loader.hidden = true;
				statusText.textContent = '';
				results.innerHTML = '';
			}

			function resetSerieAndNummer() {
				serieSelect.disabled = true;
				setOptions( serieSelect, [], 'Kies eerst een merk...' );
				nummerInput.disabled = true;
				nummerInput.value = '';
				resetResults();
			}

			// Stap 1: Merk gekozen -> series ophalen.
			merkSelect.addEventListener( 'change', function () {
				var merkId = merkSelect.value;
				resetSerieAndNummer();

				if ( ! merkId ) {
					return;
				}

				setOptions( serieSelect, [], 'Laden...' );

				var body = new URLSearchParams();
				body.append( 'action', 'fxr_get_series' );
				body.append( 'nonce', fxrZoeker.nonce );
				body.append( 'taxonomy', taxonomy );
				body.append( 'merk_id', merkId );

				fetch( fxrZoeker.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				} )
					.then( function ( res ) {
						return res.json();
					} )
					.then( function ( json ) {
						if ( ! json.success || ! json.data.series.length ) {
							setOptions( serieSelect, [], 'Geen series gevonden' );
							return;
						}
						setOptions( serieSelect, json.data.series, 'Kies een serie...' );
						serieSelect.disabled = false;
					} )
					.catch( function () {
						setOptions( serieSelect, [], 'Er ging iets mis' );
					} );
			} );

			// Stap 2: Serie gekozen -> nummerveld vrijgeven.
			serieSelect.addEventListener( 'change', function () {
				nummerInput.value = '';
				resetResults();
				nummerInput.disabled = ! serieSelect.value;
				if ( ! nummerInput.disabled ) {
					nummerInput.focus();
				}
			} );

			// Stap 3: Nummer typen -> zoeken binnen gekozen merk + serie.
			var doSearch = debounce( function () {
				var serieId = serieSelect.value;
				var nummer = nummerInput.value.trim();

				if ( ! serieId || nummer.length < minChars ) {
					resetResults();
					return;
				}

				loader.hidden = false;
				statusText.textContent = '';

				var body = new URLSearchParams();
				body.append( 'action', 'fxr_zoek_onderdelen' );
				body.append( 'nonce', fxrZoeker.nonce );
				body.append( 'taxonomy', taxonomy );
				body.append( 'serie_id', serieId );
				body.append( 'nummer', nummer );

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

			nummerInput.addEventListener( 'input', doSearch );
		} );
	} );
} )();
