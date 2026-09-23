( function () {
	'use strict';

	var cfg = window.ratingGlanceAdmin || {};
	var t = cfg.i18n || {};

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function message( out, text ) {
		out.replaceChildren( el( 'p', 'description', text ) );
	}

	function find( button ) {
		var source = button.dataset.source;
		var target = button.dataset.target;
		var query = document.getElementById( 'rating-glance-query-' + source ).value.trim();
		var out = document.getElementById( 'rating-glance-results-' + source );
		var keyField = document.getElementById( 'rating-glance-api-key' );

		var body = new FormData();
		body.append( 'action', 'rating_glance_lookup' );
		body.append( '_ajax_nonce', cfg.nonce );
		body.append( 'source', source );
		body.append( 'query', query );
		body.append( 'api_key', keyField ? keyField.value : '' );

		button.disabled = true;
		message( out, t.searching );

		fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json.success ) {
					message( out, ( json.data && json.data.message ) || t.failed );
					return;
				}
				if ( ! json.data.length ) {
					message( out, t.none );
					return;
				}
				var list = el( 'ul' );
				json.data.forEach( function ( item ) {
					var li = el( 'li' );
					var info = el( 'div' );
					info.appendChild( el( 'strong', '', item.title ) );
					if ( item.detail ) {
						info.appendChild( el( 'span', 'description', item.detail ) );
					}
					var use = el( 'button', 'button button-small', t.use );
					use.type = 'button';
					use.addEventListener( 'click', function () {
						var input = document.getElementById( target );
						input.value = item.value;
						input.focus();
						message( out, t.filled );
					} );
					li.appendChild( info );
					li.appendChild( use );
					list.appendChild( li );
				} );
				out.replaceChildren( list );
			} )
			.catch( function () {
				message( out, t.failed );
			} )
			.finally( function () {
				button.disabled = false;
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.rating-glance-find' );
		if ( button ) {
			event.preventDefault();
			find( button );
		}
	} );

	// Enter in a search box searches instead of submitting the settings form.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && event.target.classList.contains( 'rating-glance-query' ) ) {
			event.preventDefault();
			find( document.querySelector( '.rating-glance-find[data-source="' + event.target.dataset.source + '"]' ) );
		}
	} );
} )();
