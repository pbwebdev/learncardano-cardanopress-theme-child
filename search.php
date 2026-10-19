<?php

/**
 * Search results, in the same layout as the Resources listing: hero with the
 * search box prefilled, the result count, a filter pill per content type
 * (`?post_type=`), large cards and numbered pagination. The query itself is
 * scoped in includes/search.php.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

global $wp_query;

$lc_query = get_search_query( false );
$lc_has_q = ( '' !== trim( $lc_query ) );
$lc_total = $lc_has_q ? (int) $wp_query->found_posts : 0;

if ( ! $lc_has_q ) {
	$lc_title    = __( 'Search Learn Crypto', 'learncardano' );
	$lc_subtitle = esc_html__( 'Search articles, podcast episodes, courses and the developer knowledge base.', 'learncardano' );
} else {
	/* translators: %s: the search terms */
	$lc_title    = sprintf( __( 'Results for “%s”', 'learncardano' ), $lc_query );
	$lc_subtitle = esc_html(
		sprintf(
			/* translators: %s: number of results */
			_n( '%s result', '%s results', $lc_total, 'learncardano' ),
			number_format_i18n( $lc_total )
		)
	);
}
?>

<main class="resources-archive search-results <?php echo lc_layout_class( 'content-full' ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'       => $lc_title,
			'subtitle'    => $lc_subtitle,
			'placeholder' => __( 'Search Learn Crypto', 'learncardano' ),
			// Keep the active filter when the search is refined.
			'post_type'   => lc_search_current_group(),
			'pills'       => $lc_has_q ? lc_search_pills() : array(),
			'pills_label' => __( 'Filter results by type', 'learncardano' ),
		)
	);
	?>

	<section class="pa__wrap">
		<?php if ( $lc_has_q && have_posts() ) : ?>
			<div class="pa__list">
				<?php
				$lc_i = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part(
						'template-parts/archive-card',
						null,
						array(
							'index'         => $lc_i,
							'is_first_page' => false,
						)
					);
					$lc_i++;
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'end_size'           => 1,
					'prev_text'          => '&larr; Prev',
					'next_text'          => 'Next &rarr;',
					'screen_reader_text' => __( 'Search results navigation', 'learncardano' ),
				)
			);
			?>

		<?php elseif ( $lc_has_q ) : ?>
			<p class="pa__empty">
				<?php esc_html_e( 'Nothing matched your search. Try fewer or different words, or browse:', 'learncardano' ); ?>
				<a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Articles', 'learncardano' ); ?></a>,
				<a href="<?php echo esc_url( get_post_type_archive_link( 'podcasts' ) ); ?>"><?php esc_html_e( 'Podcasts', 'learncardano' ); ?></a>,
				<a href="<?php echo esc_url( get_post_type_archive_link( 'sfwd-courses' ) ); ?>"><?php esc_html_e( 'Courses', 'learncardano' ); ?></a>,
				<a href="<?php echo esc_url( ( lc_kb_hub_url() ? lc_kb_hub_url() : home_url( '/kb/' ) ) ); ?>"><?php esc_html_e( 'Knowledge base', 'learncardano' ); ?></a>.
			</p>
		<?php else : ?>
			<p class="pa__empty"><?php esc_html_e( 'Type a word or two in the box above to search the site.', 'learncardano' ); ?></p>
		<?php endif; ?>
	</section>

</main><!-- .resources-archive -->

<?php

get_footer();
