/**
 * Partner page (template-partner.php), on first scroll into view:
 * - [data-pk-count] counts one number up from zero (optional suffix);
 * - [data-pk-countup] counts every number inside its text ("5% to 10%", "72hrs");
 * - [data-pk-draw] charts draw in: line and area, then points (CSS in partner.css).
 * The final figures are already in the HTML, so without JS, or with reduced
 * motion, the page simply shows them.
 */
( function () {
	'use strict';

	// Calendly: load its widget script only when the booking section gets near
	// the screen, so the heavy scheduler never slows the first view of the page.
	( function () {
		var box = document.querySelector( '.calendly-inline-widget' );
		if ( ! box ) {
			return;
		}
		var loaded = false;
		function load() {
			if ( loaded ) {
				return;
			}
			loaded = true;
			var s = document.createElement( 'script' );
			s.src = 'https://assets.calendly.com/assets/external/widget.js';
			s.async = true;
			document.body.appendChild( s );
		}
		if ( ! ( 'IntersectionObserver' in window ) ) {
			load();
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			if ( entries.some( function ( e ) { return e.isIntersecting; } ) ) {
				io.disconnect();
				load();
			}
		}, { rootMargin: '800px 0px' } );
		io.observe( box );
		// A link to #book jumps straight there: start loading at once.
		document.addEventListener( 'click', function ( e ) {
			var a = e.target.closest && e.target.closest( 'a[href="#book"]' );
			if ( a ) {
				load();
			}
		} );
	}() );

	// Charts marked [data-pk-draw] start hidden and draw in on first view.
	var charts = document.querySelectorAll( '[data-pk-draw]' );

	var nums = document.querySelectorAll( '[data-pk-count], [data-pk-countup]' );
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

	// Every number in the text counts up together; everything else stays put.
	function runText( el ) {
		var final = el.textContent;
		var parts = final.split( /(\d+(?:\.\d+)?)/ );
		var start = null;
		// Hold the finished size so a wrapping figure does not jump while counting.
		el.style.minHeight = el.offsetHeight + 'px';
		function step( now ) {
			if ( null === start ) {
				start = now;
			}
			var t     = Math.min( ( now - start ) / DURATION, 1 );
			var eased = 1 - Math.pow( 1 - t, 3 );
			if ( t < 1 ) {
				el.textContent = parts.map( function ( part, i ) {
					if ( 0 === i % 2 ) {
						return part;
					}
					var decimals = ( part.split( '.' )[ 1 ] || '' ).length;
					return ( parseFloat( part ) * eased ).toFixed( decimals );
				} ).join( '' );
				window.requestAnimationFrame( step );
			} else {
				el.textContent = final;
				el.style.minHeight = '';
			}
		}
		window.requestAnimationFrame( step );
	}

	function run( el ) {
		if ( el.hasAttribute( 'data-pk-countup' ) ) {
			runText( el );
			return;
		}
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

	var chartObserver = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( entry.isIntersecting ) {
				chartObserver.unobserve( entry.target );
				entry.target.classList.add( 'is-drawn' );
				entry.target.classList.remove( 'is-pending' );
			}
		} );
	}, { threshold: 0.45 } );

	charts.forEach( function ( el ) {
		el.classList.add( 'is-pending' );
		chartObserver.observe( el );
	} );
}() );
