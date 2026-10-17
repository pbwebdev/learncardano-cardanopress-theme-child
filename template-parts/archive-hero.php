<?php
/**
 * Archive hero in the podcast-archive style, shared by the Resources listing
 * (home.php) and category pages: gradient title, supporting copy, a search
 * form and, optionally, the blog category filter.
 * Styles live in style.css under the `pa__` namespace.
 *
 * Args: title, subtitle, placeholder, post_type (search scope; empty for the
 * whole site), show_categories, active_category (term ID, 0 for "All").
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'title'           => '',
		'subtitle'        => '',
		'placeholder'     => 'Search articles',
		'post_type'       => 'post',
		'show_categories' => false,
		'active_category' => 0,
	)
);
?>
<section class="pa__hero">
	<h1 class="pa__h1"><?php echo esc_html( $args['title'] ); ?></h1>
	<?php if ( $args['subtitle'] ) : ?>
		<p class="pa__sub"><?php echo wp_kses_post( $args['subtitle'] ); ?></p>
	<?php endif; ?>
	<form class="pa__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="sr-only" for="pa-search"><?php echo esc_html( $args['placeholder'] ); ?></label>
		<input id="pa-search" class="pa__search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>">
		<?php if ( $args['post_type'] ) : ?>
			<input type="hidden" name="post_type" value="<?php echo esc_attr( $args['post_type'] ); ?>">
		<?php endif; ?>
		<button class="pa__search-btn" type="submit">Search</button>
	</form>
	<?php if ( $args['show_categories'] ) : ?>
		<nav class="pa__cats" aria-label="<?php esc_attr_e( 'Article categories', 'learncardano' ); ?>">
			<?php $is_all = ! $args['active_category']; ?>
			<a class="pa__cat-pill<?php echo $is_all ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"<?php echo $is_all ? ' aria-current="page"' : ''; ?>>All</a>
			<?php foreach ( lc_blog_categories() as $term ) : ?>
				<?php $is_active = ( (int) $args['active_category'] === (int) $term->term_id ); ?>
				<a class="pa__cat-pill<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $term ) ); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $term->name ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</section>
