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

		<script type="text/javascript">document.documentElement.className = document.documentElement.className.replace( 'no-js', 'js' );</script>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
		<?php wp_head(); ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.1.1/css/fontawesome.min.css" integrity="sha384-zIaWifL2YFF1qaDiAo0JFgsmasocJ/rqu7LKYH8CoBEXqGbb9eO+Xi3s6fQhgFWM" crossorigin="anonymous">

        <link rel="stylesheet" href="/wp-content/themes/cardanopress-bootstrap-theme/assets/css/cardanopress-bootstrap.css">
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/favicon-16x16.png" sizes="16x16" />
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/android-chrome-192x192.png" sizes="192x192" />
        <link rel="apple-touch-icon" href="/wp-content/themes/learncardano-cardanopress-theme-child/apple-touch-icon.png" />
        <meta name="msapplication-TileImage" content="/wp-content/themes/learncardano-cardanopress-theme-child/android-chrome-192x192.pn" />

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,700;1,400&family=Rubik:wght@400;700&display=swap" rel="stylesheet">
        <link rel='stylesheet' id='cardanopress-bootstrap-child-theme'  href='/wp-content/themes/learncardano-cardanopress-theme-child/style.css' media='all' />


	</head>

	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>

		<a class="screen-reader-text" href="#site-content"><?php esc_html_e( 'Skip to content', 'cardanopress-bootstrap' ); ?></a>

		<header class="site-header navbar navbar-expand-xl navbar-light">
			<nav class="container flex-wrap flex-xl-nowrap">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="navbar-brand fs-1 fw-bold">
<!--					<img src="/wp-content/uploads/2022/05/gokey-logo-e1653886282380.png" title="Learn Cardano" alt="Learn Cardano">-->
					<?php bloginfo( 'name' ); ?>
				</a>

				<div class="d-xl-none">
					<?php echo do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]'); ?>
					<?php echo do_shortcode('[cardanopress_template name="menu-dropdown" if="isConnected"]'); ?>
				</div>

				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="collapse navbar-collapse flex-grow-0" id="navbarSupportedContent">
					<?php cardanopress_bootstrap_primary_menu(); ?>

					<div class="d-none d-xl-block ms-xl-5">
						<?php echo do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]'); ?>
						<?php echo do_shortcode('[cardanopress_template name="menu-dropdown" if="isConnected"]'); ?>
					</div>
				</div>
			</nav>
		</header><!-- .site-header -->

		<div id="site-content" class="site-content">
			<?php //get_template_part( 'template-parts/global', 'banner' ); ?>
