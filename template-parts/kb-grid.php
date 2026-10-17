<?php
/**
 * Grid of compact knowledge base cards for the main query, with pagination.
 * Used by archive.php on knowledge base type and taxonomy archives.
 *
 * @package CardanoPress Bootstrap
 */
?>
<section class="pa__wrap kb__wrap">
	<?php if ( have_posts() ) : ?>
		<div class="kb__grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/kb-card', null, array( 'heading' => 'h2' ) );
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
				'screen_reader_text' => __( 'Resources navigation', 'learncardano' ),
			)
		);
		?>

	<?php else : ?>
		<p class="pa__empty"><?php esc_html_e( 'Nothing here yet.', 'learncardano' ); ?></p>
	<?php endif; ?>
</section>
