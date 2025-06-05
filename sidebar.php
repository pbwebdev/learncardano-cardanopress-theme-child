<?php

/**
 * The template containing the sidebar area
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

if ( ! is_active_sidebar( 'sidebar' ) ) {
	return;
}

?>

<aside class="<?php echo cardanopress_child_bootstrap_class( 'sidebar' ); ?>">
	<div class="text-center">
		<?php get_search_form(); ?>
	</div>

	<?php dynamic_sidebar( 'sidebar' ); ?>
</aside><!-- .sidebar -->
