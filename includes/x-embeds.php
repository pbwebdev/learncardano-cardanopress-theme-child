<?php
/**
 * Click-to-load X (Twitter) embeds.
 *
 * X's oEmbed HTML is a readable <blockquote class="twitter-tweet"> (the post
 * text, links, author and date) followed by widgets.js, which then pulls in
 * ~1MB of iframes, scripts and autoplaying video on every page view. Here the
 * blockquote stays as a styled card and widgets.js only loads when a reader
 * clicks "Load post"; that card then turns into the full embed. Without JS
 * the card is the post's text with a link to it on X.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Whether any X embed was rendered on this page (prints the loader once).
 *
 * @param bool|null $set Set to true when an embed is rendered.
 * @return bool
 */
function lc_x_embeds_used( $set = null ) {
	static $used = false;
	if ( true === $set ) {
		$used = true;
	}
	return $used;
}

add_filter(
	'embed_oembed_html',
	function ( $html, $url ) {
		if ( is_admin() || is_feed() || false === strpos( $html, 'twitter-tweet' ) ) {
			return $html;
		}
		if ( ! preg_match( '#^https?://(www\.|mobile\.)?(twitter|x)\.com/#i', (string) $url ) ) {
			return $html;
		}
		$html = preg_replace( '#<script[^>]*widgets\.js[^>]*>\s*</script>#i', '', $html );
		// Not "twitter-tweet" until clicked: widgets.js renders every
		// .twitter-tweet on the page as soon as it loads, so one click would
		// otherwise pull in every post on the page.
		$html = str_replace( 'class="twitter-tweet"', 'class="lc-x__tweet"', $html );
		lc_x_embeds_used( true );
		return '<div class="lc-x">' . $html
			. '<button type="button" class="lc-x__load">'
			. '<svg class="lc-x__logo" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M18.9 2H22l-6.8 7.8L23 22h-6.2l-4.8-6.3L6.4 22H3.3l7.3-8.3L1 2h6.3l4.4 5.8L18.9 2zm-1.1 18.1h1.7L6.3 3.8H4.5l13.3 16.3z"/></svg>'
			. esc_html__( 'Load post from X', 'learncardano' )
			. '</button></div>';
	},
	20,
	2
);

/**
 * The loader: one widgets.js request, made on the first click, then each
 * clicked card is upgraded in place.
 */
add_action(
	'wp_footer',
	function () {
		if ( ! lc_x_embeds_used() ) {
			return;
		}
		?>
<script>
(function () {
	var loading = null;
	function widgets() {
		if (window.twttr && window.twttr.widgets) { return Promise.resolve(window.twttr); }
		if (!loading) {
			loading = new Promise(function (resolve, reject) {
				var s = document.createElement('script');
				s.src = 'https://platform.twitter.com/widgets.js';
				s.async = true;
				s.onload = function () { window.twttr.ready(resolve); };
				s.onerror = reject;
				document.head.appendChild(s);
			});
		}
		return loading;
	}
	// A plain link to the post on X, in place of the button.
	function openLink(card) {
		var links = card.querySelectorAll('blockquote a');
		var a = document.createElement('a');
		a.className = 'lc-x__load lc-x__open';
		a.href = links.length ? links[links.length - 1].href : 'https://x.com/';
		a.target = '_blank';
		a.rel = 'noopener';
		a.textContent = <?php echo wp_json_encode( __( 'Open this post on X', 'learncardano' ) ); ?>;
		return a;
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('button.lc-x__load') : null;
		if (!btn) { return; }
		var card = btn.parentNode;
		var quote = card.querySelector('.lc-x__tweet');
		if (!quote) { return; }
		btn.disabled = true;
		btn.classList.add('is-loading');
		btn.lastChild.textContent = <?php echo wp_json_encode( __( 'Loading post…', 'learncardano' ) ); ?>;
		quote.className = 'twitter-tweet';

		// widgets.js may render the post itself as it initialises, so watch
		// for X's iframe rather than trusting load()'s promise alone. It only
		// counts once it has height: for a deleted or unavailable post X
		// leaves an empty iframe and the text card in place.
		var started = Date.now();
		var poll = setInterval(function () {
			var f = card.querySelector('iframe');
			if (f && f.offsetHeight > 0) {
				clearInterval(poll);
				var link = card.querySelector('.lc-x__open');
				if (link) { link.remove(); }
				if (btn.parentNode) { btn.remove(); }
				card.classList.add('is-loaded');
			} else if (btn.parentNode && Date.now() - started > 15000 && !document.hidden) {
				// Still nothing after 15s on screen: offer the post on X, but
				// keep watching in case X's embed arrives late.
				btn.replaceWith(openLink(card));
			} else if (Date.now() - started > 120000) {
				clearInterval(poll);
			}
		}, 250);

		widgets().then(function (twttr) {
			return twttr.widgets.load(card);
		}).catch(function () {
			// X's script is blocked (tracking protection, ad blockers):
			// put the text card back and link to the post.
			clearInterval(poll);
			quote.className = 'lc-x__tweet';
			if (btn.parentNode) { btn.replaceWith(openLink(card)); }
		});
	});
})();
</script>
		<?php
	},
	30
);
