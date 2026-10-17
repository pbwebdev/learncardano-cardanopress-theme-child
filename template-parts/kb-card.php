<?php
/**
 * Compact knowledge base card: type, title, TL;DR (or excerpt), difficulty
 * and era. Used on knowledge base archives, the hub page and in the
 * "related" lists on entries. Carries data-* attributes for the hub filters.
 *
 * Args: post_id (default: the current post), heading (h2 or h3).
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'post_id' => get_the_ID(),
		'heading' => 'h3',
	)
);

$kb_id      = (int) $args['post_id'];
$kb_type    = get_post_type( $kb_id );
$kb_types   = lc_kb_types();
$kb_heading = in_array( $args['heading'], array( 'h2', 'h3' ), true ) ? $args['heading'] : 'h3';
$kb_tldr    = get_field( 'tldr', $kb_id, false );
$kb_summary = $kb_tldr ? $kb_tldr : get_the_excerpt( $kb_id );
$kb_level   = lc_kb_term_names( $kb_id, 'difficulty' );
$kb_era     = lc_kb_term_names( $kb_id, 'era' );
?>
<a class="kb__card kb__card--<?php echo esc_attr( $kb_type ); ?>" href="<?php echo esc_url( get_permalink( $kb_id ) ); ?>"
	data-type="<?php echo esc_attr( $kb_type ); ?>"
	data-level="<?php echo esc_attr( lc_kb_term_slugs( $kb_id, 'difficulty' ) ); ?>"
	data-era="<?php echo esc_attr( lc_kb_term_slugs( $kb_id, 'era' ) ); ?>">
	<span class="kb__card-type"><?php echo esc_html( isset( $kb_types[ $kb_type ] ) ? $kb_types[ $kb_type ]['single'] : '' ); ?></span>
	<<?php echo $kb_heading; ?> class="kb__card-title"><?php echo esc_html( get_the_title( $kb_id ) ); ?></<?php echo $kb_heading; ?>>
	<?php if ( $kb_summary ) : ?>
		<p class="kb__card-summary"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $kb_summary ), 26 ) ); ?></p>
	<?php endif; ?>
	<?php if ( $kb_level || $kb_era ) : ?>
		<span class="kb__card-meta">
			<?php echo esc_html( implode( ' · ', array_filter( array( $kb_level, $kb_era ? sprintf( __( '%s era', 'learncardano' ), $kb_era ) : '' ) ) ) ); ?>
		</span>
	<?php endif; ?>
</a>
