/**
 * Partner page (template-partner.php): counts the hero stats up from zero the
 * first time they scroll into view. The final figures are already in the HTML
 * (and in a screen-reader copy), so without JS, or with reduced motion, the
 * page simply shows them.
 */
( function () {
	'use strict';

	var nums = document.querySelectorAll( '[data-pk-count]' );
	if ( ! nums.length || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	var DURATION = 1400;

	function format( value, decimals, suffix ) {
		return value.toLocaleString( 'en-AU', {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals,
		} ) + suffix;
	}

	function run( el ) {
		var target   = parseFloat( el.getAttribute( 'data-pk-count' ) );
		var decimals = parseInt( el.getAttribute( 'data-pk-decimals' ), 10 ) || 0;
		var suffix   = el.getAttribute( 'data-pk-suffix' ) || '';
		var final    = el.textContent;
		var start    = null;

		if ( isNaN( target ) ) {
			return;
		}

		function step( now ) {
			if ( null === start ) {
				start = now;
			}
			var t = Math.min( ( now - start ) / DURATION, 1 );
			// Ease-out so the number settles rather than stopping dead.
			var eased = 1 - Math.pow( 1 - t, 3 );
			el.textContent = t < 1 ? format( target * eased, decimals, suffix ) : final;
			if ( t < 1 ) {
				window.requestAnimationFrame( step );
			}
		}
		window.requestAnimationFrame( step );
	}

	var observer = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( entry.isIntersecting ) {
				observer.unobserve( entry.target );
				run( entry.target );
			}
		} );
	}, { threshold: 0.4 } );

	nums.forEach( function ( el ) {
		observer.observe( el );
	} );
}() );
