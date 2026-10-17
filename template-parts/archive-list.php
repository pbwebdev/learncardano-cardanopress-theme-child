<?php
/**
 * The card list, pagination and empty state for the main query, in the
 * podcast-archive style. Shared by home.php and category.php.
 *
 * Args: empty (text when nothing matches), badge (label on the first card of
 * page 1; empty for none), nav_label (screen-reader name for pagination).
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'empty'     => 'Nothing found.',
		'badge'     => '',
		'nav_label' => 'Posts navigation',
	)
);
$is_first_page = ( absint( get_query_var( 'paged' ) ) <= 1 );
?>
<section class="pa__wrap">
	<?php if ( have_posts() ) : ?>
		<div class="pa__list">
			<?php
			$i = 0;
			while ( have_posts() ) :
				the_post();
				get_template_part(
					'template-parts/archive-card',
					null,
					array(
						'index'         => $i,
						'is_first_page' => $is_first_page,
						'badge'         => $args['badge'],
					)
				);
				$i++;
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
				'screen_reader_text' => $args['nav_label'],
			)
		);
		?>

	<?php else : ?>
		<p class="pa__empty"><?php echo esc_html( $args['empty'] ); ?></p>
	<?php endif; ?>
</section>
