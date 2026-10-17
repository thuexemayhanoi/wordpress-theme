/**
 * Node harness for plugins/nguyen-ha-seo-companion/assets/admin-search-index.js.
 *
 * Runs the real script against a tiny fake DOM and a fake server (no browser, no dependencies) and checks the
 * request sequence, retries, progress updates, button states and error handling.
 * Usage: node search-index-panel.harness.js <path to admin-search-index.js>
 * Exit code 0 and the line "ALL PASSED" when everything holds.
 */
'use strict';

const fs = require( 'fs' );
const vm = require( 'vm' );
const assert = require( 'assert' );

const source = fs.readFileSync( process.argv[ 2 ], 'utf8' );

function element( id ) {
	return {
		id,
		textContent: '',
		hidden: true,
		disabled: false,
		value: 0,
		attributes: {},
		listeners: {},
		addEventListener( type, listener ) {
			this.listeners[ type ] = listener;
		},
		setAttribute( name, value ) {
			this.attributes[ name ] = value;
		},
		click() {
			return this.listeners.click();
		}
	};
}

const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

async function settle( panel ) {
	// Let the promise chains of the script run to completion.
	for ( let i = 0; i < 200 && panel.window.__busy(); i++ ) {
		await tick();
	}
	await tick();
}

/**
 * Create a page with the script loaded.
 *
 * @param {Function} serverHandler Receives the decoded request fields and returns a response description:
 *                                 { json: object } | { text: string } | { reject: true }.
 * @param {Object}   options       confirm: result of window.confirm().
 */
function createPanel( serverHandler, options = {} ) {
	const els = {
		'nh-seo-search-build': element( 'nh-seo-search-build' ),
		'nh-seo-search-clear': element( 'nh-seo-search-clear' ),
		'nh-seo-search-progress': element( 'nh-seo-search-progress' ),
		'nh-seo-search-status-text': element( 'nh-seo-search-status-text' )
	};
	const requests = [];
	const windowListeners = {};
	let pending = 0;

	const windowObject = {
		nhSeoSearchIndex: {
			ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
			nonce: 'nonce123',
			buildAction: 'nh_seo_search_build',
			clearAction: 'nh_seo_search_clear',
			i18n: {
				starting: 'Starting',
				progress: 'Indexed %1$s of %2$s posts (%3$s%%).',
				done: 'Done: %s posts indexed.',
				clearing: 'Clearing',
				cleared: 'Cleared',
				confirmClear: 'Sure?',
				retrying: 'Retrying',
				failed: 'Failed',
				leaveWarning: 'Leave?'
			}
		},
		confirm: () => ( undefined === options.confirm ? true : options.confirm ),
		setTimeout: ( callback ) => {
			Promise.resolve().then( callback );
			return 0;
		},
		addEventListener( type, listener ) {
			windowListeners[ type ] = listener;
		},
		fetch( url, init ) {
			const fields = Object.fromEntries( new URLSearchParams( init.body ) );
			requests.push( { url, method: init.method, credentials: init.credentials, headers: init.headers, fields } );
			pending++;
			return Promise.resolve( serverHandler( fields, requests.length ) ).then( ( reply ) => {
				pending--;
				if ( reply.reject ) {
					throw new TypeError( 'network down' );
				}
				return {
					json: () => ( undefined !== reply.json ? Promise.resolve( reply.json ) : Promise.reject( new SyntaxError( 'not json' ) ) )
				};
			}, ( error ) => {
				pending--;
				throw error;
			} );
		},
		__busy: () => pending > 0 || els[ 'nh-seo-search-build' ].disabled
	};
	windowObject.window = windowObject;

	const context = {
		window: windowObject,
		document: { getElementById: ( id ) => els[ id ] || null },
		URLSearchParams,
		Promise,
		Object,
		String,
		Array,
		parseInt,
		undefined: undefined
	};
	vm.createContext( context );
	vm.runInContext( source, context );
	return { els, requests, window: windowObject, windowListeners, context };
}

const ok = ( data ) => ( { json: { success: true, data } } );
const no = ( message ) => ( { json: { success: false, data: { message } } } );

async function run( name, test ) {
	try {
		await test();
		console.log( 'ok - ' + name );
	} catch ( error ) {
		console.log( 'not ok - ' + name + '\n' + ( error && error.stack ? error.stack : error ) );
		process.exitCode = 1;
	}
}

( async () => {
	await run( 'does nothing when the panel is missing', async () => {
		const context = { window: { nhSeoSearchIndex: { i18n: {} } }, document: { getElementById: () => null } };
		vm.createContext( context );
		assert.doesNotThrow( () => vm.runInContext( source, context ) );
	} );

	await run( 'builds batch by batch with the cursor and build token, then reports completion', async () => {
		const panel = createPanel( ( fields ) => {
			if ( 'start' === fields.step ) {
				return ok( { build: 'tok', cursor: 0, total: 25, indexed: 0, done: false, percent: 0 } );
			}
			const cursor = parseInt( fields.cursor, 10 );
			if ( 0 === cursor ) {
				return ok( { build: 'tok', cursor: 10, total: 25, indexed: 10, done: false, percent: 40 } );
			}
			if ( 10 === cursor ) {
				return ok( { build: 'tok', cursor: 20, total: 25, indexed: 20, done: false, percent: 80 } );
			}
			return ok( { build: 'tok', cursor: 25, total: 25, indexed: 25, done: true, percent: 100 } );
		} );

		const run = panel.els[ 'nh-seo-search-build' ].click();
		assert.strictEqual( panel.els[ 'nh-seo-search-build' ].disabled, true, 'buttons are disabled while building' );
		assert.strictEqual( panel.els[ 'nh-seo-search-clear' ].disabled, true );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'Starting' );
		await run;
		await settle( panel );

		assert.deepStrictEqual( panel.requests.map( ( r ) => r.fields.step ), [ 'start', 'batch', 'batch', 'batch' ] );
		assert.deepStrictEqual( panel.requests.slice( 1 ).map( ( r ) => r.fields.cursor ), [ '0', '10', '20' ] );
		assert.ok( panel.requests.slice( 1 ).every( ( r ) => 'tok' === r.fields.build ), 'the build token is sent with every batch' );
		assert.ok( panel.requests.every( ( r ) => 'nonce123' === r.fields.nonce && 'nh_seo_search_build' === r.fields.action ) );
		assert.ok( panel.requests.every( ( r ) => 'POST' === r.method && 'same-origin' === r.credentials ) );
		assert.strictEqual( panel.requests[ 0 ].url, 'https://example.test/wp-admin/admin-ajax.php' );
		assert.strictEqual( panel.els[ 'nh-seo-search-progress' ].value, 100 );
		assert.strictEqual( panel.els[ 'nh-seo-search-progress' ].hidden, false );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'Done: 25 posts indexed.' );
		assert.strictEqual( panel.els[ 'nh-seo-search-build' ].disabled, false, 'buttons are enabled again' );
		assert.strictEqual( panel.els[ 'nh-seo-search-clear' ].disabled, false );
	} );

	await run( 'shows progress text with positional placeholders', async () => {
		const texts = [];
		const panel = createPanel( ( fields ) => {
			if ( 'start' === fields.step ) {
				return ok( { build: 'tok', cursor: 0, total: 25, indexed: 0, done: false, percent: 0 } );
			}
			texts.push( panel.els[ 'nh-seo-search-status-text' ].textContent );
			return '0' === fields.cursor ?
				ok( { build: 'tok', cursor: 10, total: 25, indexed: 10, done: false, percent: 40 } ) :
				ok( { build: 'tok', cursor: 25, total: 25, indexed: 25, done: true, percent: 100 } );
		} );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );
		assert.strictEqual( texts[ 0 ], 'Indexed 0 of 25 posts (0%).' );
		assert.strictEqual( texts[ 1 ], 'Indexed 10 of 25 posts (40%).' );
	} );

	await run( 'retries a batch after network problems and carries on', async () => {
		let failures = 0;
		const retryingTexts = [];
		const panel = createPanel( ( fields ) => {
			if ( 'start' === fields.step ) {
				return ok( { build: 'tok', cursor: 0, total: 5, indexed: 0, done: false, percent: 0 } );
			}
			if ( failures < 2 ) {
				failures++;
				retryingTexts.push( panel.els[ 'nh-seo-search-status-text' ].textContent );
				return { reject: true };
			}
			return ok( { build: 'tok', cursor: 5, total: 5, indexed: 5, done: true, percent: 100 } );
		} );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );

		assert.strictEqual( panel.requests.length, 4, 'start, two failed attempts, one success' );
		assert.deepStrictEqual( panel.requests.slice( 1 ).map( ( r ) => r.fields.cursor ), [ '0', '0', '0' ], 'the same batch is repeated' );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'Done: 5 posts indexed.' );
		assert.strictEqual( panel.els[ 'nh-seo-search-build' ].disabled, false );
	} );

	await run( 'gives up after three failed attempts and says so', async () => {
		const panel = createPanel( ( fields ) => 'start' === fields.step ?
			ok( { build: 'tok', cursor: 0, total: 5, indexed: 0, done: false, percent: 0 } ) :
			{ reject: true } );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );

		assert.strictEqual( panel.requests.length, 4, 'start plus three attempts' );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'network down' );
		assert.strictEqual( panel.els[ 'nh-seo-search-build' ].disabled, false, 'the user can run it again' );
	} );

	await run( 'does not retry an answer from WordPress that says no (expired nonce, superseded build)', async () => {
		const panel = createPanel( ( fields ) => 'start' === fields.step ?
			ok( { build: 'tok', cursor: 0, total: 5, indexed: 0, done: false, percent: 0 } ) :
			no( 'Another index build has started. This one was stopped.' ) );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );

		assert.strictEqual( panel.requests.length, 2, 'start and one refused batch, no retries' );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'Another index build has started. This one was stopped.' );
		assert.strictEqual( panel.els[ 'nh-seo-search-build' ].disabled, false );
	} );

	await run( 'a failing start is reported without retrying', async () => {
		const panel = createPanel( () => no( 'The index table could not be created.' ) );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );
		assert.strictEqual( panel.requests.length, 1 );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'The index table could not be created.' );
	} );

	await run( 'a response that is not JSON counts as a failed attempt', async () => {
		const panel = createPanel( () => ( { text: '<html>Fatal error</html>' } ) );
		await panel.els[ 'nh-seo-search-build' ].click();
		await settle( panel );
		assert.strictEqual( panel.requests.length, 3, 'three attempts at the start step' );
		assert.strictEqual( panel.els[ 'nh-seo-search-status-text' ].textContent, 'Failed' );
	} );

	await run( 'ignores a second click while a build is running', async () => {
		const panel = createPanel( ( fields ) => 'start' === fields.step ?
			ok( { build: 'tok', cursor: 0, total: 5, indexed: 0, done: false, percent: 0 } ) :
			ok( { build: 'tok', cursor: 5, total: 5, indexed: 5, done: true, percent: 100 } ) );
		const first = panel.els[ 'nh-seo-search-build' ].click();
		panel.els[ 'nh-seo-search-build' ].click();
		await first;
		await settle( panel );
		assert.strictEqual( panel.requests.filter( ( r ) => 'start' === r.fields.step ).length, 1 );
	} );

	await run( 'warns before leaving the page only while a build is running', async () => {
		let release;
		const gate = new Promise( ( resolve ) => {
			release = resolve;
		} );
		const panel = createPanel( ( fields ) => 'start' === fields.step ?
			gate.then( () => ok( { build: 'tok', cursor: 0, total: 5, indexed: 0, done: false, percent: 0 } ) ) :
			ok( { build: 'tok', cursor: 5, total: 5, indexed: 5, done: true, percent: 100 } ) );

		const event = { prevented: false, returnValue: undefined, preventDefault() {
			this.prevented = true;
		} };
		panel.windowListeners.beforeunload( event );
		assert.strictEqual( event.prevented, false, 'idle: no warning' );

		const running = panel.els[ 'nh-seo-search-build' ].click();
		panel.windowListeners.beforeunload( event );
		assert.strictEqual( event.prevented, true, 'running: warning' );
		assert.strictEqual( event.returnValue, 'Leave?' );

		release();
		await running;
		await settle( panel );
		const after = { prevented: false, preventDefault() {
			this.prevented = true;
		} };
		panel.windowListeners.beforeunload( after );
		assert.strictEqual( after.prevented, false, 'finished: no warning' );
	} );

	await run( 'clearing asks for confirmation first', async () => {
		const declined = createPanel( () => ok( {} ), { confirm: false } );
		await declined.els[ 'nh-seo-search-clear' ].click();
		assert.strictEqual( declined.requests.length, 0, 'declined: nothing is sent' );

		const accepted = createPanel( () => ok( { status: 'cleared' } ) );
		await accepted.els[ 'nh-seo-search-clear' ].click();
		await settle( accepted );
		assert.strictEqual( accepted.requests.length, 1 );
		assert.strictEqual( accepted.requests[ 0 ].fields.action, 'nh_seo_search_clear' );
		assert.strictEqual( accepted.requests[ 0 ].fields.nonce, 'nonce123' );
		assert.strictEqual( accepted.els[ 'nh-seo-search-status-text' ].textContent, 'Cleared' );
		assert.strictEqual( accepted.els[ 'nh-seo-search-progress' ].hidden, true );
		assert.strictEqual( accepted.els[ 'nh-seo-search-build' ].disabled, false );
	} );

	if ( ! process.exitCode ) {
		console.log( 'ALL PASSED' );
	}
} )();
