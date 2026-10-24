<?php
/**
 * One card in the podcast-archive style for the current post in the loop.
 *
 * Articles show their primary category beside the date and a "Read article"
 * button; other post types show their type name and "Read more". Posts
 * without a featured image get a branded gradient placeholder.
 *
 * Args: index (position in the loop), is_first_page, badge (label shown on
 * the first card of page 1; empty for none).
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'index'         => 0,
		'is_first_page' => true,
		'badge'         => '',
	)
);

$post_type   = get_post_type();
$is_article  = ( 'post' === $post_type );
$show_badge  = ( $args['badge'] && $args['is_first_page'] && 0 === $args['index'] );
$title_attr  = the_title_attribute( array( 'echo' => false ) );
$comments_no = get_comments_number();
$thumb_id    = get_post_thumbnail_id();

if ( $is_article ) {
	$category = lc_post_primary_category();
	$label    = $category ? $category->name : '';
} else {
	$type_object = get_post_type_object( $post_type );
	$label       = $type_object ? $type_object->labels->singular_name : '';
}
?>
<a class="pa__card" href="<?php echo esc_url( get_permalink() ); ?>">
	<div class="pa__media">
		<?php if ( $thumb_id ) : ?>
			<?php
			// The first card is the mobile LCP element: load it eagerly at high priority.
			echo lc_card_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lc_card_image().
				array(
					'class' => 'pa__media-img',
					'sizes' => '(max-width: 780px) calc(100vw - 32px), (max-width: 1128px) calc((100vw - 48px) * 0.505), 521px',
					'eager' => ( 0 === $args['index'] ),
				)
			);
			?>
		<?php else : ?>
			<div class="pa__media-img pa__placeholder" aria-hidden="true">
				<span class="pa__placeholder-mark">Learn Crypto</span>
			</div>
		<?php endif; ?>
		<?php if ( $show_badge ) : ?>
			<span class="pa__badge"><?php echo esc_html( $args['badge'] ); ?></span>
		<?php endif; ?>
	</div>
	<div class="pa__body">
		<div class="pa__date">
			<?php echo esc_html( get_the_date( 'd M, Y' ) ); ?>
			<?php if ( $label ) : ?>
				<span class="pa__cat"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
		</div>
		<h2 class="pa__title"><?php the_title(); ?></h2>
		<p class="pa__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34 ) ); ?></p>
		<div class="pa__actions">
			<span class="pa__btn">
				<?php echo $is_article ? esc_html__( 'Read article', 'learncardano' ) : esc_html__( 'Read more', 'learncardano' ); ?>
				<span class="pa__btn-arrow" aria-hidden="true">&rarr;</span>
			</span>
			<span class="pa__comments"><?php printf( esc_html( _n( '%s comment', '%s comments', $comments_no, 'learncardano' ) ), esc_html( number_format_i18n( $comments_no ) ) ); ?></span>
		</div>
	</div>
</a>
