<?php

/**
 * The template for displaying the header
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

?>

<!DOCTYPE html>
<html class="no-js" <?php language_attributes(); ?>>
	<head>

        <style>
            body {
                font-family: 'Open Sans', sans-serif;
                color: #222 !important;
            }

            @media (min-width: 1200px) {
                .container, .container-lg, .container-md, .container-sm, .container-xl {
                    max-width: 1140px;
                }
            }
            @media (min-width: 992px){
                .container, .container-lg, .container-md, .container-sm {
                    max-width: 960px;
                }
            }
            @media (min-width: 768px){
                .container, .container-md, .container-sm {
                    max-width: 720px;
                }
            }
            @media (min-width: 576px) {
                .container, .container-sm {
                    max-width: 540px;
                }
            }
            .container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
                margin-left: auto;
                margin-right: auto;
                padding-left: var(--bs-gutter-x, .75rem);
                padding-right: var(--bs-gutter-x, .75rem);
                width: 100%;
            }

            .entry-title {
                margin-bottom: 2rem;
            }

            h1 {
                font-size: 2.5em;
                line-height: 1em;
            }
            h1, .navbar-brand {
                /* color: #0070ff; */
                background: rgb(220,16,255);
                background: linear-gradient(90deg, rgba(220,16,255,1) 0%, rgba(0,112,255,1) 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            @media (min-width: 1200px){}
            .navbar-expand-xl {
                flex-wrap: nowrap;
                justify-content: flex-start;
            }
            }
            .navbar-brand {
                font-size: calc(1.26563rem + .1875vw);
                margin-right: 1rem;
                padding-bottom: 0.2890625rem;
                padding-top: 0.2890625rem;
                text-decoration: none;
                white-space: nowrap;
            }
            .site-header {
                box-shadow: 0px 0px 13px 1px #ddd;
            }
            .screen-reader-text {
                clip: rect(1px,1px,1px,1px);
                word-wrap: normal!important;
                border: 0;
                -webkit-clip-path: inset(50%);
                clip-path: inset(50%);
                height: 1px;
                margin: -1px;
                overflow: hidden;
                padding: 0;
                position: absolute;
                width: 1px;
            }
            a, a:visited {
                color: #0070ff;
                text-decoration: underline;
            }


            @media (min-width: 1200px){
                .flex-xl-nowrap {
                    flex-wrap: nowrap!important;
                }
            }

            .row {
                --bs-gutter-x: 1.5rem;
                --bs-gutter-y: 0;
                display: flex;
                flex-wrap: wrap;
                margin-left: calc(var(--bs-gutter-x)*-.5);
                margin-right: calc(var(--bs-gutter-x)*-.5);
                margin-top: calc(var(--bs-gutter-y)*-1);
            }

            .py-3 {
                padding-bottom: 1.5rem!important;
                padding-top: 1.5rem!important;
            }

            @media (min-width: 992px){
                .col-lg-8 {
                    flex: 0 0 auto;
                    width: 66.66666667%;
                }
            }
            @media (min-width: 768px){
                .col-md-7 {
                    flex: 0 0 auto;
                    width: 58.33333333%;
                }
            }
            @media (min-width: 576px){
                .col-sm-12 {
                    flex: 0 0 auto;
                    width: 100%;
                }
            }

        </style>

		<script type="text/javascript">document.documentElement.className = document.documentElement.className.replace( 'no-js', 'js' );</script>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/images/favicon-16x16.png" sizes="16x16" />
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/images/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/images/android-chrome-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/images/apple-touch-icon.png" />
        <meta name="msapplication-TileImage" content="/wp-content/themes/learncardano-cardanopress-theme-child/android-chrome-192x192.png" />
        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme-lcp'  href='/wp-content/themes/learncardano-cardanopress-theme-child/lcp.css' media='all' />

		<?php wp_head(); ?>

	</head>

	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>

		<a class="screen-reader-text" href="#site-content"><?php esc_html_e( 'Skip to content', 'cardanopress-bootstrap' ); ?></a>

		<header class="site-header navbar navbar-expand-xl navbar-light">
			<nav class="container flex-wrap flex-xl-nowrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="navbar-brand fs-1 fw-bold">
					<?php bloginfo( 'name' ); ?>
				</a>

				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse flex-grow-0" id="navbarSupportedContent">
					<?php cardanopress_bootstrap_primary_menu(); ?>

					<div class="d-none d-xl-block ms-xl-5">
						<?php echo do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]'); ?>
						<?php echo do_shortcode('[cardanopress_template name="menu-dropdown" if="isConnected"]'); ?>
					</div>
					
					<div class="d-xl-none">
						<?php echo do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]'); ?>
						<?php echo do_shortcode('[cardanopress_template name="menu-dropdown" if="isConnected"]'); ?>
					</div>
					
				</div>
			</nav>
		</header><!-- .site-header -->

		<div id="site-content" class="site-content">
