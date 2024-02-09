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
                            <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui.jpg" alt="Peter Bui" width="250" height="250">
                        </picture>
                    </div>
                </div>
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

		<footer class="site-footer py-2 bg-dark text-light">
			<div class="container">
				<div class="row">
                    <div class="col-sm-3 d-none d-sm-none d-md-none d-lg-block logo mb-2">
                        <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/microphone-white.png" title="Learn Cardano" alt="Learn Cardano icon" width="20" height="33" class="mx-1" > Learn Cardano
                    </div>
                    <div class="col-md-6 copyright text-center mb-2">
                        <small>Copyright &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-light"><?php bloginfo( 'name' ); ?></a></small> -
                        <span class="small">Powered by <a href="<?php echo esc_url( $theme->get( 'AuthorURI' ) ); ?>" class="text-light"><?php echo esc_html( $theme->get( 'Author' ) ); ?></a>.</span>
                    </div>
                    <div class="col-md-6 col-lg-3 socials mb-2 text-md-end">
                        <ul>
                            <li><a class="pe-1"  href="https://www.facebook.com/LearnCardano" title="Learn Cardano on Facebook" target="_blank" rel="noreferrer"><span class="fa-brands fa-facebook fa-xl"></span> </a></li>
                            <li><a class="pe-1"  href="https://twitter.com/LearnCardano" title="Learn Cardano on Twitter" target="_blank" rel="noreferrer"><span class="fa-brands fa-twitter fa-xl"></span> </a></li>
                            <li><a class="pe-1"  href="https://www.youtube.com/learncardano" title="Learn Cardano on YouTube" target="_blank" rel="noreferrer"><span class="fa-brands fa-youtube fa-xl"></span> </a></li>
                            <li><a class="pe-1"  href="https://open.spotify.com/show/11FpPiHPzivZoSOTgVcfgT" title="Learn Cardano on Spotify" target="_blank" rel="noreferrer"><span class="fa-brands fa-spotify fa-xl"></span> </a></li>
                            <li><a class="pe-1" href="https://discord.gg/V6mZGYUPNW" title="Learn Cardano Discord Community" target="_blank" rel="noreferrer"><span class="fa-brands fa-discord fa-xl"></span> </a></li>
                        </ul>
                    </div>
                </div>
			</div>
		</footer>
<!-- .site-footer -->


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

		<?php wp_footer(); ?>


<?php $post_type = get_post_type( $post->ID ); ?>

<?php if ($post_type=='sfwd-courses') :?>
    <link rel="stylesheet" href="/wp-content/themes/learncardano-cardanopress-theme-child/learndash.css"  >
<?php endif; ?>

<!--<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.1.1/css/fontawesome.min.css" integrity="sha384-zIaWifL2YFF1qaDiAo0JFgsmasocJ/rqu7LKYH8CoBEXqGbb9eO+Xi3s6fQhgFWM" crossorigin="anonymous">-->

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/js/all.min.js" integrity="sha512-6PM0qYu5KExuNcKt5bURAoT6KCThUmHRewN3zUFNaoI6Di7XJPTMoT6K0nsagZKk2OB4L7E3q1uQKHNHd4stIQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>-->
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-X7VNV35VXF"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-X7VNV35VXF');
</script>
<!--<?php echo $post_type; ?>-->
</html>

