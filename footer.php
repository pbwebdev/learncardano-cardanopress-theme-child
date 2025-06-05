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
			<p>DRep ID</p>
			<ul style="text-wrap: wrap; word-wrap:break-word; font-size: 14px;">
				<li>CIP-105: drep127pc58jyky40fvess0c4tjxkrnkf8ta3sfltvm4ptypqc5fgav3</li>
				<li>CIP-129: drep1yftc8zs7gjcj4a9nxzplz4wg6cwweya0kxp8adnw59vsyrqvrysud</li>
			</ul>
                    </div>
                    <div class="col-md-5 offset-md-1 text-center">
                        <picture class="about-image">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.webp" alt="Peter Bui"  width="250" height="250" type="image/webp">
                            <source srcset="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.jpg" alt="Peter Bui"  width="250" height="250" type="image/jpeg">
                            <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.jpg" alt="Peter Bui" width="250" height="250">
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
                            <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/adaoz-kangaroo.jpg" alt="ADAOZ Stake Pool" width="250" height="250">
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
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01">
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
                            <a href="https://youtube.com/learncardano/" title="Watch Learn Cardano Podcast on YouTube" class="platform-button float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/youtube-watch.png" width="122" height="35" title="Watch on YouTube"  alt="Apple Podcasts">
                            </a>

                            <a href="https://podcasts.apple.com/podcast/learn-cardano-podcast/id1560654454" title="Listen to the Learn Cardano Podcast on Apple Podcasts" class="platform-button float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/apple-podcasts.svg" width="174" height="35" title="Listen on Apple Podcasts"  alt="Apple Podcasts">
                            </a>


                            <a href="https://open.spotify.com/show/11FpPiHPzivZoSOTgVcfgT" title="Listen to the Learn Cardano Podcast on Spotify" class="platform-button  float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/spotify.svg" width="122" height="35" title="Listen on Spotify"  alt="Spotify">
                            </a>

                            <a href="https://podcasts.google.com/feed/aHR0cHM6Ly93d3cuc3ByZWFrZXIuY29tL3Nob3cvNDg1NjIwNy9lcGlzb2Rlcy9mZWVk" title="Listen to the Learn Cardano Podcast on Google Podcasts" class="platform-button  float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/google-podcasts.svg" width="170" height="35" title="Listen on Google Podcasts"  alt="Google Podcasts">
                            </a>

                            <a href="https://www.iheart.com/podcast/53-learn-cardano-podcast-80684119/" title="Listen to the Learn Cardano Podcast on iHeart Radio" class="platform-button  float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/iheart.png" width="122" height="35" title="Listen on iHeart Radio"  alt="Google Podcasts">
                            </a>

                            <a href="https://www.spreaker.com/show/learn-cardano-podcast" title="Listen to the Learn Cardano Podcast on Spreaker" class="platform-button  float-start mr-1" target="_blank" rel="noreferrer">
                                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/spreaker.png" width="122" height="35" title="Listen on Google Podcasts"  alt="Google Podcasts">
                            </a>

                    </div>
                </div>
            </div>
        </div>

	<footer class="site-footer pt-2 bg-dark text-light" style="padding-bottom:70px;">
		<div class="container">
			<div class="row">
        		        <div class="copyright text-center">
                			<small>Copyright &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-light"><?php bloginfo( 'name' ); ?></a></small> -
                        		<span class="small">Powered by <a href="<?php echo esc_url( $theme->get( 'AuthorURI' ) ); ?>" class="text-light"><?php echo esc_html( $theme->get( 'Author' ) ); ?></a>.</span>
                    		</div>
                	</div>
		</div>
	</footer>
	<iframe src='https://widget.spreaker.com/player?show_id=4856207&theme=dark&playlist=show&playlist-continuous=true&chapters-image=true' width='100%' height='50px' title='Learn Cardano Podcast' frameborder='0' class="fixed-bottom" ></iframe>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

		<?php wp_footer(); ?>

        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme'  href='/wp-content/themes/learncardano-cardanopress-theme-child/style.css' media='all' />
        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme-fonts'  href='/wp-content/themes/learncardano-cardanopress-theme-child/fonts.css' media='all' />
        <link rel="stylesheet" href="/wp-content/themes/learncardano-cardanopress-theme-child/learndash.css"  >

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
