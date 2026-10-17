<?php

/**
 * The template for displaying the footer
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

$theme = wp_get_theme( CARDANOPRESS_BOOTSTRAP_THEME_BASE );

?>


        <div class="about-home">
            <div class="container col-10">
                <div class="row">
                    <div class="col align-self-center mb-3">
                        <h2>About Learn Cardano Podcast</h2>
                        <h3>Hi, I'm Peter, I've been podcasting since 2013</h3>
                        <p>After years of trading and learning about crypto, I decided that Cardano was my chain of choice
                            and put all my efforts into understanding everything about Cardano. Learn Cardano is where I share
                            all that I learn with you.</p>
                    </div>
                    <div class="col-md-5 offset-md-1 text-center">
                        <picture class="about-image">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.webp" alt="Peter Bui"  width="250" height="250" type="image/webp">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.jpg" alt="Peter Bui"  width="250" height="250" type="image/jpeg">
                            <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.jpg" alt="Peter Bui" width="250" height="250" loading="lazy" decoding="async">
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
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01" loading="lazy" decoding="async" aria-hidden="true">
            </div>
        </div>

		</div><!-- .site-content -->

		<div id="footer-links">
            <div class="container">
                <div class="row py-5">
                    <div class="col-md-3">
                        <h3>Learn Cardano Podcast</h3>
                        <p>Providing awesome Cardano & blockchain related content for beginners to experts alike.</p>
                        <p>Building for Cardano</p>
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
                        		<span class="small">Powered by <a href="<?php echo esc_url( $theme->get( 'AuthorURI' ) ); ?>" class="text-light"><?php echo esc_html( $theme->get( 'Author' ) ); ?></a></span>
							-
							<span class="small">Built by <a href="https://meshwithus.com.au" target="_blank" rel="noopener" class="text-light" title="WordPress design and development, Gold Coast">Mesh With Us</a></span>
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
	<iframe src='https://widget.spreaker.com/player?show_id=4856207&theme=dark&playlist=show&playlist-continuous=true&chapters-image=true' width='100%' height='50px' title='Learn Cardano Podcast' frameborder='0' loading='lazy' class="fixed-bottom" ></iframe>


<script src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/bootstrap/bootstrap.bundle.min.js" defer></script>

		<?php wp_footer(); ?>

        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme'  href='<?php echo esc_url( lc_theme_asset_url( 'style.css' ) ); ?>' media='all' />
        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme-fonts'  href='<?php echo esc_url( lc_theme_asset_url( 'fonts.css' ) ); ?>' media='all' />
        <link rel="stylesheet" href="<?php echo esc_url( lc_theme_asset_url( 'learndash.css' ) ); ?>"  >

<!--<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.1.1/css/fontawesome.min.css" integrity="sha384-zIaWifL2YFF1qaDiAo0JFgsmasocJ/rqu7LKYH8CoBEXqGbb9eO+Xi3s6fQhgFWM" crossorigin="anonymous">-->

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/js/all.min.js" integrity="sha512-6PM0qYu5KExuNcKt5bURAoT6KCThUmHRewN3zUFNaoI6Di7XJPTMoT6K0nsagZKk2OB4L7E3q1uQKHNHd4stIQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>-->


<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-W49L88K8');</script>
<!-- End Google Tag Manager -->
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W49L88K8"
                  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
</html>
