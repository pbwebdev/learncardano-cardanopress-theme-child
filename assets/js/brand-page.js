/**
 * Brand assets page (template-brand.php): selecting a colour swatch copies
 * its hex code and says so in the live region below the swatches.
 */
( function () {
	'use strict';

	var note = document.querySelector( '[data-bk-copied]' );
	var timer = null;

	function say( text ) {
		if ( ! note ) {
			return;
		}
		note.textContent = text;
		clearTimeout( timer );
		timer = setTimeout( function () {
			note.textContent = '';
		}, 2500 );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '[data-bk-copy]' );
		if ( ! btn ) {
			return;
		}
		var hex = btn.getAttribute( 'data-bk-copy' );
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( hex ).then(
				function () {
					say( 'Copied ' + hex );
				},
				function () {
					say( hex );
				}
			);
		} else {
			say( hex );
		}
	} );
}() );
