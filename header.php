<?php

/**
 * The template for displaying the header
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

?>

<!DOCTYPE html>
<!--

███╗   ███╗███████╗███████╗██╗  ██╗    ██╗    ██╗██╗████████╗██╗  ██╗    ██╗   ██╗███████╗
████╗ ████║██╔════╝██╔════╝██║  ██║    ██║    ██║██║╚══██╔══╝██║  ██║    ██║   ██║██╔════╝
██╔████╔██║█████╗  ███████╗███████║    ██║ █╗ ██║██║   ██║   ███████║    ██║   ██║███████╗
██║╚██╔╝██║██╔══╝  ╚════██║██╔══██║    ██║███╗██║██║   ██║   ██╔══██║    ██║   ██║╚════██║
██║ ╚═╝ ██║███████╗███████║██║  ██║    ╚███╔███╔╝██║   ██║   ██║  ██║    ╚██████╔╝███████║
╚═╝     ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝     ╚══╝╚══╝ ╚═╝   ╚═╝   ╚═╝  ╚═╝     ╚═════╝ ╚══════╝

Built by Mesh With Us
https://meshwithus.com.au

-->
<html class="no-js" <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <style media="all">
            .container,.container-fluid,.container-lg,.container-md,.container-sm,.container-xl,.container-xxl{margin-left:auto;margin-right:auto;padding-left:var(--bs-gutter-x,.75rem);padding-right:var(--bs-gutter-x,.75rem);width:100%}
            .entry-title{margin-bottom:2rem}
            h1{font-size:2.5em;line-height:1em}
            h1,.navbar-brand{background:#dc10ff;background:linear-gradient(90deg,rgba(220,16,255,1) 0%,rgba(0,112,255,1) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
            .navbar-brand{font-size:calc(1.26563rem + .1875vw);margin-right:1rem;padding-bottom:.2890625rem;padding-top:.2890625rem;text-decoration:none;white-space:nowrap}
            .navbar-brand.lc-brand{background:none;-webkit-text-fill-color:initial;padding-top:.35rem;padding-bottom:.35rem;line-height:0}
            .lc-brand__logo{display:block;width:auto;height:48px}
            @media (min-width:1200px){.lc-brand__logo{height:56px}}
            .site-header{box-shadow:0 0 13px 1px #ddd}
            .row{--bs-gutter-x:1.5rem;--bs-gutter-y:0;display:flex;flex-wrap:wrap;margin-left:calc(var(--bs-gutter-x)*-.5);margin-right:calc(var(--bs-gutter-x)*-.5);margin-top:calc(var(--bs-gutter-y)*-1)}
            .py-3{padding-bottom:1.5rem!important;padding-top:1.5rem!important}
            .navbar-light .navbar-toggler{border-color:rgba(0,0,0,.1);color:rgba(0,0,0,.55)}
            .navbar-toggler{background-color:transparent;border:1px solid transparent;border-radius:.25rem;font-size:calc(1.26563rem + .1875vw);line-height:1;padding:.25rem .75rem;transition:box-shadow .15s ease-in-out}
            .navbar-nav{display:flex;flex-direction:column;list-style:none;margin-bottom:0;padding-left:0}
            li.nav-item{padding:0 .5em}
            .navbar-light .navbar-nav .nav-link.active,.navbar-light .navbar-nav .show>.nav-link{color:rgba(0,0,0,.9)}
            .navbar-light .navbar-nav .nav-link{color:#222;font-weight:700}
        </style>

		<script type="text/javascript">document.documentElement.className = document.documentElement.className.replace( 'no-js', 'js' );</script>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<?php $lc_brand = get_stylesheet_directory_uri() . '/assets/brand/'; ?>
		<link rel="icon" href="<?php echo esc_url( $lc_brand . 'favicon.ico' ); ?>" sizes="any" />
		<?php foreach ( array( 16, 32, 96, 192 ) as $lc_s ) : ?>
		<link rel="icon" type="image/png" sizes="<?php echo (int) $lc_s . 'x' . (int) $lc_s; ?>" href="<?php echo esc_url( $lc_brand . 'icon-' . $lc_s . '.png' ); ?>" />
		<?php endforeach; ?>
		<?php foreach ( array( 76, 120, 152, 167, 180 ) as $lc_s ) : ?>
		<link rel="apple-touch-icon" sizes="<?php echo (int) $lc_s . 'x' . (int) $lc_s; ?>" href="<?php echo esc_url( $lc_brand . 'apple-touch-icon-' . $lc_s . '.png' ); ?>" />
		<?php endforeach; ?>
		<meta name="msapplication-TileImage" content="<?php echo esc_url( $lc_brand . 'icon-192.png' ); ?>" />

		<?php wp_head(); ?>

	</head>

	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>

		<a class="screen-reader-text" href="#site-content"><?php esc_html_e( 'Skip to content', 'cardanopress-bootstrap' ); ?></a>

		<header class="site-header navbar navbar-expand-xl navbar-light">
			<nav class="container flex-wrap flex-xl-nowrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="navbar-brand lc-brand">
					<img class="lc-brand__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/brand/logo.svg' ); ?>" width="1450" height="512" alt="<?php esc_attr_e( 'Learn Crypto Podcast', 'learncardano' ); ?>" fetchpriority="high" />
				</a>

				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse flex-grow-0" id="navbarSupportedContent">
					<?php lc_primary_menu(); ?>

					<div class="d-none d-xl-block ms-xl-5">
						<?php lc_auth_header_slot( 'desktop' ); ?>
					</div>

					<div class="d-xl-none">
						<?php lc_auth_header_slot( 'mobile' ); ?>
					</div>
					
				</div>
			</nav>
		</header><!-- .site-header -->

		<div id="site-content" class="site-content">
