<?php
/**
 * Template Name: Knowledge base
 *
 * Hub for the developer knowledge base: hero, the page's own content as an
 * intro, a tile per type (Concepts, Recipes, Pitfalls…) linking to its
 * archive, then every published entry as a card grid with type, level and
 * era filters that work without reloading the page.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

$lc_types = lc_kb_types();
$lc_items = get_posts(
	array(
		'post_type'      => array_keys( $lc_types ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
usort(
	$lc_items,
	function ( $a, $b ) use ( $lc_types ) {
		$order = array_flip( array_keys( $lc_types ) );
		return $order[ $a->post_type ] <=> $order[ $b->post_type ] ?: strcasecmp( $a->post_title, $b->post_title );
	}
);

// Filter options: only the types, levels and eras that entries actually use.
$lc_counts = array_count_values( wp_list_pluck( $lc_items, 'post_type' ) );
$lc_filter = array(
	'level' => array(),
	'era'   => array(),
);
foreach ( array( 'level' => 'difficulty', 'era' => 'era' ) as $lc_key => $lc_tax ) {
	$lc_terms = get_terms(
		array(
			'taxonomy'   => $lc_tax,
			'object_ids' => wp_list_pluck( $lc_items, 'ID' ),
			'orderby'    => 'term_order',
		)
	);
	if ( ! is_wp_error( $lc_terms ) ) {
		foreach ( $lc_terms as $lc_term ) {
			$lc_filter[ $lc_key ][ $lc_term->slug ] = $lc_term->name;
		}
	}
}
$lc_level_order = array( 'beginner', 'intermediate', 'advanced' );
uksort(
	$lc_filter['level'],
	function ( $a, $b ) use ( $lc_level_order ) {
		$ia = array_search( $a, $lc_level_order, true );
		$ib = array_search( $b, $lc_level_order, true );
		return ( false === $ia ? 99 : $ia ) <=> ( false === $ib ? 99 : $ib );
	}
);
$lc_era_order = array( 'byron', 'shelley', 'goguen', 'mary', 'alonzo', 'babbage', 'conway' );
uksort(
	$lc_filter['era'],
	function ( $a, $b ) use ( $lc_era_order ) {
		$ia = array_search( $a, $lc_era_order, true );
		$ib = array_search( $b, $lc_era_order, true );
		return ( false === $ia ? 99 : $ia ) <=> ( false === $ib ? 99 : $ib );
	}
);
?>

<main class="resources-archive kb-hub <?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

	<?php
	while ( have_posts() ) :
		the_post();
		get_template_part(
			'template-parts/archive-hero',
			null,
			array(
				'title'       => get_the_title(),
				'subtitle'    => has_excerpt() ? get_the_excerpt() : __( 'Concepts, recipes, pitfalls and more for building on Cardano, from your first wallet connection to a working dApp.', 'learncardano' ),
				'placeholder' => __( 'Search Learn Cardano', 'learncardano' ),
				'post_type'   => '',
			)
		);
		if ( trim( get_the_content() ) ) :
			?>
			<div class="kb-hub__intro entry-content"><?php the_content(); ?></div>
			<?php
		endif;
	endwhile;
	?>

	<section class="pa__wrap">
		<div class="kb-hub__types">
			<?php foreach ( $lc_types as $lc_type => $lc_info ) : ?>
				<a class="kb-hub__type kb__card--<?php echo esc_attr( $lc_type ); ?>" href="<?php echo esc_url( get_post_type_archive_link( $lc_type ) ); ?>">
					<span class="kb-hub__type-count"><?php echo esc_html( number_format_i18n( isset( $lc_counts[ $lc_type ] ) ? $lc_counts[ $lc_type ] : 0 ) ); ?></span>
					<h2 class="kb-hub__type-title"><?php echo esc_html( $lc_info['label'] ); ?></h2>
					<p><?php echo esc_html( $lc_info['blurb'] ); ?></p>
					<span class="kb-hub__type-link"><?php esc_html_e( 'Browse', 'learncardano' ); ?> <span aria-hidden="true">&rarr;</span></span>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="kb-hub__browse">
			<h2 class="kb-hub__heading"><?php esc_html_e( 'Browse everything', 'learncardano' ); ?></h2>

			<div class="kb-hub__filters">
				<div class="kb-hub__filter" role="group" aria-label="<?php esc_attr_e( 'Type', 'learncardano' ); ?>" data-filter="type">
					<span class="kb-hub__filter-label"><?php esc_html_e( 'Type', 'learncardano' ); ?></span>
					<button type="button" class="pa__cat-pill is-active" data-value="" aria-pressed="true"><?php esc_html_e( 'All', 'learncardano' ); ?></button>
					<?php foreach ( $lc_types as $lc_type => $lc_info ) : ?>
						<?php if ( ! empty( $lc_counts[ $lc_type ] ) ) : ?>
							<button type="button" class="pa__cat-pill" data-value="<?php echo esc_attr( $lc_type ); ?>" aria-pressed="false"><?php echo esc_html( $lc_info['label'] ); ?></button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php foreach ( array( 'level' => __( 'Level', 'learncardano' ), 'era' => __( 'Era', 'learncardano' ) ) as $lc_key => $lc_label ) : ?>
					<?php if ( count( $lc_filter[ $lc_key ] ) > 1 ) : ?>
						<div class="kb-hub__filter" role="group" aria-label="<?php echo esc_attr( $lc_label ); ?>" data-filter="<?php echo esc_attr( $lc_key ); ?>">
							<span class="kb-hub__filter-label"><?php echo esc_html( $lc_label ); ?></span>
							<button type="button" class="pa__cat-pill is-active" data-value="" aria-pressed="true"><?php esc_html_e( 'All', 'learncardano' ); ?></button>
							<?php foreach ( $lc_filter[ $lc_key ] as $lc_slug => $lc_name ) : ?>
								<button type="button" class="pa__cat-pill" data-value="<?php echo esc_attr( $lc_slug ); ?>" aria-pressed="false"><?php echo esc_html( $lc_name ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<p class="kb-hub__count" aria-live="polite"></p>

			<div class="kb__grid kb-hub__grid">
				<?php foreach ( $lc_items as $lc_item ) : ?>
					<?php get_template_part( 'template-parts/kb-card', null, array( 'post_id' => $lc_item->ID, 'heading' => 'h3' ) ); ?>
				<?php endforeach; ?>
			</div>
			<p class="pa__empty kb-hub__none" hidden><?php esc_html_e( 'Nothing matches those filters yet.', 'learncardano' ); ?></p>
		</div>
	</section>

</main><!-- .kb-hub -->

<script>
	(function () {
		var browse = document.querySelector('.kb-hub__browse');
		if (!browse) { return; }
		var cards = browse.querySelectorAll('.kb__card');
		var count = browse.querySelector('.kb-hub__count');
		var none = browse.querySelector('.kb-hub__none');
		var active = { type: '', level: '', era: '' };

		function apply() {
			var shown = 0;
			cards.forEach(function (card) {
				var match = (!active.type || card.dataset.type === active.type)
					&& (!active.level || (' ' + card.dataset.level + ' ').indexOf(' ' + active.level + ' ') > -1)
					&& (!active.era || (' ' + card.dataset.era + ' ').indexOf(' ' + active.era + ' ') > -1);
				card.hidden = !match;
				if (match) { shown++; }
			});
			count.textContent = shown + (shown === 1 ? ' resource' : ' resources');
			none.hidden = shown > 0;
		}

		browse.querySelectorAll('.kb-hub__filter').forEach(function (group) {
			group.addEventListener('click', function (event) {
				var button = event.target.closest('button');
				if (!button) { return; }
				group.querySelectorAll('button').forEach(function (b) {
					b.classList.toggle('is-active', b === button);
					b.setAttribute('aria-pressed', b === button ? 'true' : 'false');
				});
				active[group.dataset.filter] = button.dataset.value;
				apply();
			});
		});
		apply();
	})();
</script>

<?php

get_footer();
