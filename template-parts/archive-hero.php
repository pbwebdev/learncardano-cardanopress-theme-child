<?php
/**
 * Archive hero in the podcast-archive style, shared by the Resources listing
 * (home.php), category pages, other archives and the knowledge base: gradient
 * title, supporting copy, a search form and an optional row of filter pills.
 * Styles live in style.css under the `pa__` namespace.
 *
 * Args: title, subtitle, placeholder, post_type (search scope; empty for the
 * whole site), show_search, pills (list of [label, url, active]),
 * pills_label (accessible name for the pill row).
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'title'       => '',
		'subtitle'    => '',
		// Algolia InstantSearch replaces the results page and ignores
		// post_type, so the box searches the whole site and says so.
		'placeholder' => __( 'Search Learn Cardano', 'learncardano' ),
		'post_type'   => '',
		'show_search' => true,
		'pills'       => array(),
		'pills_label' => __( 'Filter', 'learncardano' ),
	)
);
?>
<section class="pa__hero">
	<h1 class="pa__h1"><?php echo esc_html( $args['title'] ); ?></h1>
	<?php if ( $args['subtitle'] ) : ?>
		<div class="pa__sub"><?php echo wp_kses_post( $args['subtitle'] ); ?></div>
	<?php endif; ?>
	<?php if ( $args['show_search'] ) : ?>
		<form class="pa__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="sr-only" for="pa-search"><?php echo esc_html( $args['placeholder'] ); ?></label>
			<input id="pa-search" class="pa__search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>">
			<?php if ( $args['post_type'] ) : ?>
				<input type="hidden" name="post_type" value="<?php echo esc_attr( $args['post_type'] ); ?>">
			<?php endif; ?>
			<button class="pa__search-btn" type="submit">Search</button>
		</form>
	<?php endif; ?>
	<?php if ( $args['pills'] ) : ?>
		<nav class="pa__cats" aria-label="<?php echo esc_attr( $args['pills_label'] ); ?>">
			<?php foreach ( $args['pills'] as $pill ) : ?>
				<?php list( $label, $url, $active ) = $pill; ?>
				<a class="pa__cat-pill<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</section>
