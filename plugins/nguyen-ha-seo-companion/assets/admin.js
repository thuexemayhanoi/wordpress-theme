/**
 * Nguyen Ha SEO Companion: admin behaviour.
 *
 * Plain ES5 without dependencies (the logo picker uses wp.media when it is available). Everything here is
 * progressive enhancement: the forms work and save without this script. Nothing is ever written with innerHTML.
 */
( function () {
	'use strict';

	var settings = window.nhSeoAdmin || {};
	var i18n = settings.i18n || {};

	/**
	 * Replace %1$s, %2$s, ... and %d / %s with values (positional first).
	 */
	function format( template, values ) {
		var next = 0;
		return String( template )
			.replace( /%(\d+)\$[sd]/g, function ( match, position ) {
				return String( values[ parseInt( position, 10 ) - 1 ] );
			} )
			.replace( /%[sd]/g, function () {
				var value = values[ next ];
				next += 1;
				return String( value );
			} );
	}

	/**
	 * Closest ancestor (or the element itself) matching a data attribute.
	 */
	function closestWith( element, attribute, boundary ) {
		while ( element && element !== boundary && element.nodeType === 1 ) {
			if ( element.hasAttribute( attribute ) ) {
				return element;
			}
			element = element.parentNode;
		}
		return null;
	}

	/**
	 * Number of characters, counting a surrogate pair (an emoji) once like the server does.
	 */
	function countCharacters( value ) {
		return value.replace( /[\uD800-\uDBFF][\uDC00-\uDFFF]/g, '_' ).length;
	}

	/**
	 * Number of non-empty lines.
	 */
	function countLines( value ) {
		var lines = value.split( /\r\n|\r|\n/ );
		var count = 0;
		var index;
		for ( index = 0; index < lines.length; index += 1 ) {
			if ( lines[ index ].replace( /^\s+|\s+$/g, '' ) !== '' ) {
				count += 1;
			}
		}
		return count;
	}

	/**
	 * Live counters for fields marked with data-nh-counter="chars|lines" and data-nh-max.
	 */
	function initCounters( root ) {
		var fields = root.querySelectorAll( '[data-nh-counter]' );
		var index;

		function bind( field ) {
			var counter = root.querySelector( '[data-nh-counter-for="' + field.id + '"]' );
			var max = parseInt( field.getAttribute( 'data-nh-max' ), 10 );
			var mode = field.getAttribute( 'data-nh-counter' );
			if ( ! counter || ! max ) {
				return;
			}
			var template = counter.getAttribute( 'data-nh-counter-text' ) || '%1$s / %2$s';

			function update() {
				var used = 'lines' === mode ? countLines( field.value ) : countCharacters( field.value );
				counter.textContent = format( template, [ used, max ] );
				if ( used > max ) {
					counter.className = counter.className.replace( /\s*nh-seo-counter--over/, '' ) + ' nh-seo-counter--over';
				} else {
					counter.className = counter.className.replace( /\s*nh-seo-counter--over/, '' );
				}
			}

			field.addEventListener( 'input', update );
			update();
		}

		for ( index = 0; index < fields.length; index += 1 ) {
			bind( fields[ index ] );
		}
	}

	/**
	 * Repeaters (FAQ and sources): add, remove and reorder rows, keeping field names consecutive.
	 */
	function initRepeater( root ) {
		var list = root.querySelector( '[data-nh-list]' );
		var template = root.querySelector( 'template[data-nh-template]' );
		var addWrap = root.querySelector( '[data-nh-add-wrap]' );
		var addButton = root.querySelector( '[data-nh-add]' );
		var max = parseInt( root.getAttribute( 'data-nh-max' ), 10 ) || 20;
		var labels = {
			up: root.getAttribute( 'data-nh-label-up' ) || '',
			down: root.getAttribute( 'data-nh-label-down' ) || '',
			remove: root.getAttribute( 'data-nh-label-remove' ) || ''
		};
		var live = document.createElement( 'div' );

		if ( ! list || ! template || ! addButton || ! template.content ) {
			return;
		}

		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );
		root.appendChild( live );

		function announce( message ) {
			live.textContent = '';
			window.setTimeout( function () {
				live.textContent = message;
			}, 50 );
		}

		function rows() {
			var found = [];
			var child = list.firstElementChild;
			while ( child ) {
				if ( child.hasAttribute( 'data-nh-item' ) ) {
					found.push( child );
				}
				child = child.nextElementSibling;
			}
			return found;
		}

		/**
		 * Give every control of every row a consecutive index in its name and refresh labels and states.
		 */
		function refresh() {
			var items = rows();
			var position;
			var controls;
			var control;
			var inner;
			var buttons;

			for ( position = 0; position < items.length; position += 1 ) {
				controls = items[ position ].querySelectorAll( '[name]' );
				for ( inner = 0; inner < controls.length; inner += 1 ) {
					control = controls[ inner ];
					control.setAttribute( 'name', control.getAttribute( 'name' ).replace( /\[(?:\d+|__INDEX__)\](\[[^\]]+\])$/, '[' + position + ']$1' ) );
				}

				buttons = items[ position ].querySelectorAll( '[data-nh-move], [data-nh-remove]' );
				for ( inner = 0; inner < buttons.length; inner += 1 ) {
					if ( 'up' === buttons[ inner ].getAttribute( 'data-nh-move' ) ) {
						buttons[ inner ].setAttribute( 'aria-label', format( labels.up, [ position + 1 ] ) );
						buttons[ inner ].disabled = 0 === position;
					} else if ( 'down' === buttons[ inner ].getAttribute( 'data-nh-move' ) ) {
						buttons[ inner ].setAttribute( 'aria-label', format( labels.down, [ position + 1 ] ) );
						buttons[ inner ].disabled = position === items.length - 1;
					} else {
						buttons[ inner ].setAttribute( 'aria-label', format( labels.remove, [ position + 1 ] ) );
					}
				}
			}

			addButton.disabled = items.length >= max;
		}

		function enhance() {
			var actions = root.querySelectorAll( '[data-nh-actions]' );
			var index;
			for ( index = 0; index < actions.length; index += 1 ) {
				actions[ index ].hidden = false;
			}
			if ( addWrap ) {
				addWrap.hidden = false;
			}
			refresh();
		}

		function add() {
			var fragment;
			var item;
			var first;
			var actions;

			if ( rows().length >= max ) {
				announce( i18n.limitReached || '' );
				return;
			}

			fragment = template.content.cloneNode( true );
			item = fragment.querySelector( '[data-nh-item]' );
			if ( ! item ) {
				return;
			}
			actions = item.querySelector( '[data-nh-actions]' );
			if ( actions ) {
				actions.hidden = false;
			}
			list.appendChild( item );
			refresh();

			first = item.querySelector( 'input, textarea' );
			if ( first ) {
				first.focus();
			}
			announce( i18n.itemAdded || '' );
		}

		function remove( item ) {
			list.removeChild( item );
			refresh();
			addButton.focus();
			announce( i18n.itemRemoved || '' );
		}

		function move( item, direction ) {
			var sibling = 'up' === direction ? item.previousElementSibling : item.nextElementSibling;
			var anchor;

			if ( ! sibling ) {
				return;
			}
			if ( 'up' === direction ) {
				list.insertBefore( item, sibling );
			} else {
				anchor = sibling.nextElementSibling;
				list.insertBefore( item, anchor );
			}
			refresh();

			anchor = item.querySelector( '[data-nh-move="' + direction + '"]' );
			if ( anchor && ! anchor.disabled ) {
				anchor.focus();
			} else {
				anchor = item.querySelector( '[data-nh-move]:not([disabled])' );
				if ( anchor ) {
					anchor.focus();
				}
			}
			announce( i18n.itemMoved || '' );
		}

		root.addEventListener( 'click', function ( event ) {
			var target = event.target;
			var item;
			var control;

			if ( closestWith( target, 'data-nh-add', root ) ) {
				event.preventDefault();
				add();
				return;
			}

			item = closestWith( target, 'data-nh-item', root );
			if ( ! item ) {
				return;
			}
			control = closestWith( target, 'data-nh-remove', item );
			if ( control ) {
				event.preventDefault();
				remove( item );
				return;
			}
			control = closestWith( target, 'data-nh-move', item );
			if ( control && ! control.disabled ) {
				event.preventDefault();
				move( item, control.getAttribute( 'data-nh-move' ) );
			}
		} );

		enhance();
	}

	/**
	 * Logo picker on the settings screen.
	 */
	function initMedia( root ) {
		var input = root.querySelector( '[data-nh-media-input]' );
		var preview = root.querySelector( '[data-nh-media-preview]' );
		var selectButton = root.querySelector( '[data-nh-media-select]' );
		var removeButton = root.querySelector( '[data-nh-media-remove]' );
		var frame = null;

		if ( ! input || ! preview || ! selectButton || ! removeButton ) {
			return;
		}

		function clearPreview() {
			while ( preview.firstChild ) {
				preview.removeChild( preview.firstChild );
			}
		}

		selectButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: i18n.selectLogo || '',
					button: { text: i18n.useLogo || '' },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var size = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail : attachment;
					var image = document.createElement( 'img' );

					input.value = String( attachment.id );
					image.src = size.url;
					image.alt = '';
					image.width = 96;
					clearPreview();
					preview.appendChild( image );
					removeButton.hidden = false;
				} );
			}
			frame.open();
		} );

		removeButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			input.value = '';
			clearPreview();
			removeButton.hidden = true;
			selectButton.focus();
		} );
	}

	function init() {
		var boxes = document.querySelectorAll( '.nh-seo-box' );
		var repeaters = document.querySelectorAll( '[data-nh-repeater]' );
		var media = document.querySelectorAll( '[data-nh-media]' );
		var index;

		for ( index = 0; index < boxes.length; index += 1 ) {
			initCounters( boxes[ index ] );
		}
		for ( index = 0; index < repeaters.length; index += 1 ) {
			initRepeater( repeaters[ index ] );
		}
		for ( index = 0; index < media.length; index += 1 ) {
			initMedia( media[ index ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
