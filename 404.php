<?php

/**
 * The template for displaying the 404 (page not found) screen
 *
 * @package learncardano
 */

get_header();

?>

<div class="container col-10">
	<main class="<?php echo esc_attr( lc_layout_class( 'content-full' ) ); ?>">

		<h1 class="entry-title"><?php esc_html_e( 'Page not found', 'learncardano' ); ?></h1>
		<p><?php esc_html_e( 'Sorry, we could not find that page. Try a search, or head to one of the sections below.', 'learncardano' ); ?></p>

		<?php get_search_form(); ?>

		<ul class="mt-4">
			<li><a href="<?php echo esc_url( home_url( '/podcasts/' ) ); ?>"><?php esc_html_e( 'Podcasts', 'learncardano' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/resources/' ) ); ?>"><?php esc_html_e( 'Resources', 'learncardano' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/kb/' ) ); ?>"><?php esc_html_e( 'Knowledge base', 'learncardano' ); ?></a></li>
			<li><a href="<?php echo esc_url( home_url( '/courses/' ) ); ?>"><?php esc_html_e( 'Courses', 'learncardano' ); ?></a></li>
		</ul>

	</main><!-- .content -->
</div>

<?php

get_footer();
