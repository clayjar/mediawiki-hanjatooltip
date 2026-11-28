( function () {
	'use strict';

	var cache = new Map();
	var popup = null;
	var current = null;

	function ensurePopup() {
		if ( !popup ) {
			popup = document.createElement( 'div' );
			popup.className = 'hanja-tooltip';
			popup.setAttribute( 'role', 'tooltip' );
			popup.hidden = true;
			document.body.appendChild( popup );
		}
		return popup;
	}

	function hide() {
		if ( popup ) {
			popup.hidden = true;
			popup.textContent = '';
		}
		current = null;
	}

	function position( popupEl, target ) {
		var rect = target.getBoundingClientRect();
		var top = rect.bottom + window.scrollY + 4;
		var left = rect.left + window.scrollX;
		popupEl.hidden = false;
		var width = popupEl.offsetWidth;
		var maxLeft = window.scrollX + document.documentElement.clientWidth - width - 8;
		if ( left > maxLeft ) {
			left = Math.max( window.scrollX + 8, maxLeft );
		}
		var height = popupEl.offsetHeight;
		var viewportBottom = window.scrollY + window.innerHeight;
		if ( top + height > viewportBottom - 8 ) {
			var above = rect.top + window.scrollY - height - 4;
			if ( above >= window.scrollY + 8 ) {
				top = above;
			}
		}
		popupEl.style.top = top + 'px';
		popupEl.style.left = left + 'px';
	}

	function show( target, text ) {
		var el = ensurePopup();
		el.textContent = text;
		position( el, target );
	}

	function lookup( character ) {
		if ( cache.has( character ) ) {
			return Promise.resolve( cache.get( character ) );
		}
		// Post the parameters. A GET puts action= in the query string, and the
		// site's edge challenge rejects that URL for a mobile user agent.
		var body = new URLSearchParams();
		body.set( 'action', 'hanja' );
		body.set( 'format', 'json' );
		body.set( 'formatversion', '2' );
		body.set( 'char', character );
		return fetch( mw.util.wikiScript( 'api' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
			body: body
		} ).then( function ( response ) {
			if ( !response.ok ) {
				throw new Error( 'lookup failed' );
			}
			return response.json();
		} ).then( function ( data ) {
			var info = data && data.hanja || {};
			var text = info.found ?
				character + ( info.definition ? '\n' + info.definition : '' ) :
				character + '\n' + mw.message( 'hanjatooltip-not-found' ).text();
			cache.set( character, text );
			return text;
		} ).catch( function () {
			return character + '\n' + mw.message( 'hanjatooltip-not-found' ).text();
		} );
	}

	function onClick( event ) {
		if ( !event.detail || event.detail < 1 ) {
			return;
		}
		var target = event.currentTarget;
		var character = target.getAttribute( 'data-char' );
		if ( !character ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		if ( current === target && popup && !popup.hidden ) {
			hide();
			return;
		}
		current = target;
		show( target, character );
		lookup( character ).then( function ( text ) {
			if ( current === target ) {
				show( target, text );
			}
		} );
	}

	function bind( $content ) {
		$content.find( '.hanja-word' ).each( function () {
			if ( this.hanjaTooltipBound ) {
				return;
			}
			this.hanjaTooltipBound = true;
			this.addEventListener( 'click', onClick );
		} );
	}

	mw.hook( 'wikipage.content' ).add( bind );

	document.addEventListener( 'click', function ( event ) {
		if ( !popup || popup.hidden ) {
			return;
		}
		if ( event.target.closest && (
			event.target.closest( '.hanja-word' ) ||
			event.target.closest( '.hanja-tooltip' )
		) ) {
			return;
		}
		hide();
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' ) {
			hide();
		}
	} );
}() );
