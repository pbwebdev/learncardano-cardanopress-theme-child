<?php

/**
 * The template for displaying the footer
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

?>


        <div class="about-home">
            <div class="container col-10">
                <div class="row">
                    <div class="col align-self-center mb-3">
                        <h2>About Learn Crypto</h2>
                        <h3>Hi, I'm Peter, I've been podcasting since 2013</h3>
                        <p>After years of trading and learning about crypto, I decided that Cardano was my chain of choice
                            and put all my efforts into understanding everything about Cardano and the broader crypto and blockchain space. Learn Crypto is where I share
                            all that I learn with you.</p>
                        <?php // Peter's personal links and contact live on his Chatsoon profile. ?>
                        <p><a class="btn btn-primary" href="https://chatsoon.app/id/peter-bui-5ec50167" target="_blank" rel="noopener"><?php esc_html_e( 'Follow & contact Peter', 'learncardano' ); ?><span class="visually-hidden"> <?php esc_html_e( '(opens Chatsoon in a new tab)', 'learncardano' ); ?></span></a></p>
                    </div>
                    <div class="col-md-5 offset-md-1 text-center">
                        <?php $lc_peter = get_stylesheet_directory_uri() . '/images/peter-bui'; ?>
                        <picture class="about-image">
                            <source srcset="<?php echo esc_url( $lc_peter . '.webp' ); ?> 1x, <?php echo esc_url( $lc_peter . '@2x.webp' ); ?> 2x" type="image/webp">
                            <img src="<?php echo esc_url( $lc_peter . '.jpg' ); ?>" srcset="<?php echo esc_url( $lc_peter . '.jpg' ); ?> 1x, <?php echo esc_url( $lc_peter . '@2x.jpg' ); ?> 2x" alt="Peter Bui" width="250" height="250" loading="lazy" decoding="async">
                        </picture>
                    </div>
                </div>
            </div>
        </div>

        <div class="section delegation">
            <div class="container col-10">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <picture class="delegation-image mb-5">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/adaoz-kangaroo.webp" alt="ADAOZ Stake Pool"  width="250" height="250" type="image/webp">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/adaoz-kangaroo.jpg" alt="ADAOZ Stake Pool"  width="250" height="250" type="image/jpeg">
                            <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/adaoz-kangaroo.jpg" alt="ADAOZ Stake Pool" width="250" height="250" loading="lazy" decoding="async">
                        </picture>
                    </div>
                    <div class="col-md-7 align-self-center mb-3">
                        <h2>Support Our Content</h2>
                        <h3>Delegate to Our Stake Pool ADAOZ</h3>
                        <p>Delegate to our stake pool and help us continue to create content for the
                        Cardano ecosystem.</p>
                        <p style="font-size: 14px;word-wrap:break-word;">Pool ID: 6658713e2cbfa4e347691a0435953f5acbe9f03d330e94caa3a0cfb4</p>
                        <a href="/delegate" title="Delegate & stake your ADA" class="btn btn-primary">Stake Your ADA</a>
                    </div>
                </div>
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01" width="751" height="709" loading="lazy" decoding="async" aria-hidden="true">
            </div>
        </div>

		</div><!-- .site-content -->

		<div id="footer-links">
            <div class="container">
                <div class="row py-5">
                    <div class="col-md-3">
                        <h3>Learn Crypto</h3>
                        <p>Providing awesome crypto &amp; blockchain related content for beginners to experts alike.</p>
                        <p>Building for Cardano &amp; Crypto</p>
                        <?php if ( !function_exists('dynamic_sidebar') || !dynamic_sidebar("Footer Col 1") ) : ?>
                        <?php endif;?>
                    </div>
                    <div class="col-md-3">
                        <?php if ( !function_exists('dynamic_sidebar') || !dynamic_sidebar("Footer Col 2") ) : ?>
                        <?php endif;?>
                    </div>
                    <div class="col">
                        <h3>Subscribe</h3>
                        <?php
                        // Podcast platforms: vector brand icons (images/platforms/, CC0 marks
                        // from Simple Icons plus Apple's and Spotify's own icons) with live
                        // text, so the buttons stay sharp at any size. Google Podcasts shut
                        // down in 2024 and was removed.
                        $lc_platforms = array(
                            array( 'https://youtube.com/learncardano/', 'youtube', 'Watch on', 'YouTube' ),
                            array( 'https://podcasts.apple.com/podcast/learn-cardano-podcast/id1560654454', 'apple-podcasts', 'Listen on', 'Apple Podcasts' ),
                            array( 'https://open.spotify.com/show/11FpPiHPzivZoSOTgVcfgT', 'spotify', 'Listen on', 'Spotify' ),
                            array( 'https://www.iheart.com/podcast/53-learn-cardano-podcast-80684119/', 'iheart', 'Listen on', 'iHeart' ),
                            array( 'https://www.spreaker.com/show/learn-cardano-podcast', 'spreaker', 'Listen on', 'Spreaker' ),
                        );
                        ?>
                        <div class="lc-platforms">
                            <?php foreach ( $lc_platforms as $lc_platform ) : ?>
                                <?php list( $lc_url, $lc_icon, $lc_verb, $lc_name ) = $lc_platform; ?>
                                <a class="lc-platform" href="<?php echo esc_url( $lc_url ); ?>" target="_blank" rel="noopener noreferrer">
                                    <img class="lc-platform__icon" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/platforms/' . $lc_icon . '.svg' ); ?>" width="32" height="32" alt="" loading="lazy" decoding="async">
                                    <span class="lc-platform__text">
                                        <span class="lc-platform__verb"><?php echo esc_html( $lc_verb ); ?></span>
                                        <span class="lc-platform__name"><?php echo esc_html( $lc_name ); ?></span>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>

	<footer class="site-footer pt-2 bg-dark text-light" style="padding-bottom:70px;">
		<div class="container">
			<div class="row">
        		        <div class="copyright text-center">
                			<small>Copyright &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-light"><?php bloginfo( 'name' ); ?></a></small> -
							<span class="small">Built by <a href="https://meshwithus.com.au" target="_blank" rel="noopener" class="text-light" title="WordPress design and development, Gold Coast">Mesh With Us</a></span>
							<?php if ( ! function_exists( 'lc_auth_is_private_view' ) || ! lc_auth_is_private_view() ) : ?>
							- <span class="small"><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>" class="text-light" data-lc-consent-open><?php esc_html_e( 'Cookie settings', 'learncardano' ); ?></a></span>
							<?php endif; ?>
							<?php
							$lc_build = @include get_stylesheet_directory() . '/build-version.php';
							if ( is_array( $lc_build ) && ! empty( $lc_build['sha'] ) ) {
								echo ' - <span class="small">Build ' . esc_html( $lc_build['date'] ) . ' &middot; ' . esc_html( $lc_build['sha'] ) . '</span>';
							}
							?>
                    		</div>
                	</div>
		</div>
	</footer>
	<?php
	// Spreaker player: a lightweight bar in its place until it's wanted. The
	// real player is a heavy third-party iframe, and fixed to the viewport it
	// loaded on every page view; clicking the bar swaps in the same iframe.
	// Same 50px height, so nothing moves. Without JS the iframe loads as before.
	$lc_spreaker_src = 'https://widget.spreaker.com/player?show_id=4856207&theme=dark&playlist=show&playlist-continuous=true&chapters-image=true';
	?>
	<div class="lc-spreaker fixed-bottom">
		<button type="button" class="lc-spreaker__play" id="lc-spreaker-play" data-src="<?php echo esc_url( $lc_spreaker_src ); ?>">
			<span class="lc-spreaker__icon" aria-hidden="true"><?php echo lc_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="lc-spreaker__label"><?php esc_html_e( 'Play the latest podcast episode', 'learncardano' ); ?></span>
			<span class="lc-spreaker__meta"><?php esc_html_e( 'Spreaker player', 'learncardano' ); ?></span>
		</button>
	</div>
	<noscript><iframe src="<?php echo esc_url( $lc_spreaker_src ); ?>" width="100%" height="50px" title="Learn Crypto Podcast" frameborder="0" loading="lazy" class="fixed-bottom"></iframe></noscript>
	<script>
	(function () {
		var btn = document.getElementById('lc-spreaker-play');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var frame = document.createElement('iframe');
			frame.src = btn.getAttribute('data-src');
			frame.width = '100%';
			frame.setAttribute('height', '50px');
			frame.title = 'Learn Crypto Podcast';
			frame.setAttribute('frameborder', '0');
			frame.className = 'fixed-bottom';
			btn.parentNode.parentNode.replaceChild(frame, btn.parentNode);
			frame.focus();
		});
	})();
	</script>


<script src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/bootstrap/bootstrap.bundle.min.js" defer></script>

		<?php wp_footer(); ?>

<?php if ( ! function_exists( 'lc_auth_is_private_view' ) || ! lc_auth_is_private_view() ) : // No tag manager on /account/* (it carries tokens and account details). ?>
<!-- Cookie choice: Google Tag Manager (and the Google Analytics it loads) runs
     only after the visitor clicks Accept. The choice is kept in the lc_consent
     cookie for 180 days; "Cookie settings" in the footer reopens this box.
     Decided in the browser, so cached pages stay correct. Without JS nothing loads. -->
<div class="lc-consent" id="lc-consent" role="region" aria-label="<?php esc_attr_e( 'Cookie choice', 'learncardano' ); ?>" hidden>
	<p class="lc-consent__text"><?php esc_html_e( 'Can we use Google Analytics cookies to see how the site is used? Nothing is tracked unless you accept.', 'learncardano' ); ?> <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'learncardano' ); ?></a></p>
	<div class="lc-consent__actions">
		<button type="button" class="lc-consent__btn lc-consent__btn--yes" data-lc-consent="granted"><?php esc_html_e( 'Accept', 'learncardano' ); ?></button>
		<button type="button" class="lc-consent__btn" data-lc-consent="denied"><?php esc_html_e( 'Decline', 'learncardano' ); ?></button>
	</div>
</div>
<style>
.lc-consent{position:fixed;z-index:1040;left:12px;right:12px;bottom:62px;max-width:560px;margin:0 auto;padding:14px 16px;background:#fff;color:#1f1f1f;border:1px solid #e3d5f7;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.18);display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;font-size:15px;line-height:1.45}
.lc-consent[hidden]{display:none}
.lc-consent__text{margin:0;flex:1 1 260px}
.lc-consent__text a{color:#7e22ce}
.lc-consent__actions{display:flex;gap:8px;flex:0 0 auto}
.lc-consent__btn{min-width:96px;padding:8px 16px;border-radius:8px;border:2px solid #9333ea;background:#fff;color:#7e22ce;font-weight:600;cursor:pointer}
.lc-consent__btn--yes{background:#9333ea;color:#fff}
.lc-consent__btn:focus-visible{outline:3px solid #1f1f1f;outline-offset:2px}
</style>
<script>
(function (w, d) {
	var box = d.getElementById('lc-consent');
	var loaded = false;
	function choice() {
		var m = d.cookie.match(/(?:^|; )lc_consent=(granted|denied)/);
		return m ? m[1] : '';
	}
	function remember(v) {
		d.cookie = 'lc_consent=' + v + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + ('https:' === location.protocol ? '; Secure' : '');
	}
	function gtm() {
		if (loaded) { return; }
		loaded = true;
		(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
			new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
			j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
			'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(w,d,'script','dataLayer','GTM-W49L88K8');
	}
	// Loaded once the page has finished loading and the browser is idle, so it never competes with first render.
	function later() {
		function idle() {
			if ('requestIdleCallback' in w) { w.requestIdleCallback(gtm, { timeout: 3000 }); } else { setTimeout(gtm, 1500); }
		}
		if ('complete' === d.readyState) { idle(); } else { w.addEventListener('load', idle); }
	}
	// Withdrawing consent: remove the Google Analytics cookies (set on this host and on the parent domain).
	function forget() {
		var host = location.hostname, parent = '.' + host.split('.').slice(-2).join('.');
		d.cookie.split('; ').forEach(function (c) {
			var name = c.split('=')[0];
			if (/^_ga/.test(name) || '_gid' === name) {
				[ '', '; domain=' + host, '; domain=' + parent ].forEach(function (dom) {
					d.cookie = name + '=; Max-Age=0; Path=/' + dom;
				});
			}
		});
	}
	box.addEventListener('click', function (e) {
		var b = e.target.closest('[data-lc-consent]');
		if (!b) { return; }
		var v = b.getAttribute('data-lc-consent');
		remember(v);
		box.hidden = true;
		if ('granted' === v) {
			gtm();
		} else {
			forget();
			if (loaded) { location.reload(); } // Tag manager is already running on this page: reload without it.
		}
	});
	d.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('[data-lc-consent-open]')) {
			e.preventDefault();
			box.hidden = false;
			box.querySelector('button').focus();
		}
	});
	var c = choice();
	if ('granted' === c) {
		later();
	} else if ('' === c) {
		box.hidden = false;
	}
})(window, document);
</script>
<?php endif; ?>
</html>
