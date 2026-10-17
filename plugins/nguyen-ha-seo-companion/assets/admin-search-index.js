/**
 * Nguyen Ha SEO Companion: search index Tools panel.
 *
 * Builds the index in small batches (one request per batch, the server decides the batch size), shows
 * progress, and can clear the index. Plain JavaScript, no dependencies. Every request carries the nonce
 * printed by the panel and is answered with { success, data } by WordPress.
 */
( function () {
	'use strict';

	var config = window.nhSeoSearchIndex;
	var buildButton = document.getElementById( 'nh-seo-search-build' );
	var clearButton = document.getElementById( 'nh-seo-search-clear' );
	var progress = document.getElementById( 'nh-seo-search-progress' );
	var statusText = document.getElementById( 'nh-seo-search-status-text' );

	if ( ! config || ! buildButton || ! clearButton || ! progress || ! statusText ) {
		return;
	}

	var strings = config.i18n || {};
	var running = false;
	var MAX_ATTEMPTS = 3;

	/**
	 * Minimal sprintf for the translated strings: %s, %1$s, %2$s, %3$s and %%.
	 *
	 * @param {string} template Template.
	 * @param {Array}  values   Values.
	 * @return {string} Formatted text.
	 */
	function format( template, values ) {
		var next = 0;
		return String( template ).replace( /%(?:(\d+)\$)?s|%%/g, function ( match, position ) {
			if ( '%%' === match ) {
				return '%';
			}
			var index = position ? parseInt( position, 10 ) - 1 : next++;
			return undefined === values[ index ] ? '' : String( values[ index ] );
		} );
	}

	/**
	 * Show a message in the status area.
	 *
	 * @param {string} message Message.
	 */
	function say( message ) {
		statusText.textContent = message;
	}

	/**
	 * Enable or disable the buttons while a request series is running.
	 *
	 * @param {boolean} busy Whether work is in progress.
	 */
	function setBusy( busy ) {
		running = busy;
		buildButton.disabled = busy;
		clearButton.disabled = busy;
		buildButton.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	}

	/**
	 * Send one AJAX request and resolve with the data of a successful WordPress JSON response.
	 *
	 * @param {Object} fields Request fields (action, step, ...).
	 * @return {Promise} Resolves with response.data, rejects with an Error (error.fatal when retrying is pointless).
	 */
	function request( fields ) {
		var body = new URLSearchParams();
		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );
		body.append( 'nonce', config.nonce );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json().catch( function () {
				throw new Error( strings.failed );
			} ).then( function ( payload ) {
				if ( payload && payload.success ) {
					return payload.data;
				}
				var error = new Error( payload && payload.data && payload.data.message ? payload.data.message : strings.failed );
				// An answer from WordPress that says "no" will not change by asking again.
				error.fatal = true;
				throw error;
			} );
		} );
	}

	/**
	 * Request with a few retries for network problems. Batches are idempotent on the server, so repeating one is safe.
	 *
	 * @param {Object} fields Request fields.
	 * @return {Promise} Resolves with response.data.
	 */
	function requestWithRetry( fields ) {
		var attempt = 0;

		function run() {
			attempt += 1;
			return request( fields ).catch( function ( error ) {
				if ( error.fatal || attempt >= MAX_ATTEMPTS ) {
					throw error;
				}
				say( strings.retrying );
				return new Promise( function ( resolve ) {
					window.setTimeout( resolve, 1000 * attempt );
				} ).then( run );
			} );
		}

		return run();
	}

	/**
	 * Update the progress bar and text from a batch result.
	 *
	 * @param {Object} data Server data: indexed, total, percent, done.
	 */
	function showProgress( data ) {
		progress.hidden = false;
		progress.value = data.percent || 0;
		if ( data.done ) {
			say( format( strings.done, [ data.indexed ] ) );
		} else {
			say( format( strings.progress, [ data.indexed, data.total, data.percent || 0 ] ) );
		}
	}

	/**
	 * Walk the batches until the server says it is done.
	 *
	 * @param {Object} state Server data from the previous step: build, cursor.
	 * @return {Promise} Resolves with the final data.
	 */
	function nextBatch( state ) {
		return requestWithRetry( {
			action: config.buildAction,
			step: 'batch',
			cursor: state.cursor,
			build: state.build
		} ).then( function ( data ) {
			showProgress( data );
			return data.done ? data : nextBatch( data );
		} );
	}

	function onBuild() {
		if ( running ) {
			return;
		}
		setBusy( true );
		progress.hidden = false;
		progress.value = 0;
		say( strings.starting );

		requestWithRetry( { action: config.buildAction, step: 'start' } )
			.then( function ( data ) {
				showProgress( data );
				return nextBatch( data );
			} )
			.catch( function ( error ) {
				say( error && error.message ? error.message : strings.failed );
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	function onClear() {
		if ( running || ! window.confirm( strings.confirmClear ) ) {
			return;
		}
		setBusy( true );
		say( strings.clearing );

		requestWithRetry( { action: config.clearAction } )
			.then( function () {
				progress.hidden = true;
				progress.value = 0;
				say( strings.cleared );
			} )
			.catch( function ( error ) {
				say( error && error.message ? error.message : strings.failed );
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	buildButton.addEventListener( 'click', onBuild );
	clearButton.addEventListener( 'click', onClear );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( running ) {
			event.preventDefault();
			event.returnValue = strings.leaveWarning || '';
		}
	} );
}() );
