<?php

/**
 * Single article — blog posts, and any post type without its own template.
 *
 * Laid out like the podcast episode page (centred header, single reading
 * column) in the archive redesign's fonts and accent: category kicker, title
 * and byline (author, date, updated date, reading time), the featured image
 * unless the post already shows it, an "On this page" box for posts with 3+
 * sections (sticky beside the text on wide screens), then tags, share links,
 * author box, previous/next links, related posts and comments. Styling lives
 * in style.css under the `lca__` namespace.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lc_content = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', get_the_content() ) );
	list( $lc_content, $lc_toc ) = lc_heading_ids_and_toc( $lc_content );

	$lc_is_article = ( 'post' === get_post_type() );
	$lc_has_toc    = count( $lc_toc ) >= 3;
	$lc_category   = $lc_is_article ? lc_post_primary_category() : null;
	$lc_minutes    = lc_reading_minutes( $lc_content );
	$lc_thumb_id   = get_post_thumbnail_id();
	$lc_show_hero  = $lc_thumb_id && false === strpos( $lc_content, 'wp-image-' . $lc_thumb_id );
	$lc_updated    = ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) ) > DAY_IN_SECONDS;
	$lc_bio        = get_the_author_meta( 'description' );
	$lc_share_url  = rawurlencode( get_permalink() );
	$lc_share_text = rawurlencode( wp_strip_all_tags( get_the_title() ) );
	?>

	<main class="lca">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'lca__article' ); ?>>

			<header class="lca__header">
				<?php if ( $lc_category ) : ?>
					<a class="lca__kicker" href="<?php echo esc_url( get_term_link( $lc_category ) ); ?>"><?php echo esc_html( $lc_category->name ); ?></a>
				<?php endif; ?>
				<h1 class="lca__title entry-title"><?php the_title(); ?></h1>
				<div class="lca__byline">
					<?php echo get_avatar( get_the_author_meta( 'ID' ), 64, '', '', array( 'class' => 'lca__avatar' ) ); ?>
					<span><?php esc_html_e( 'By', 'learncardano' ); ?> <strong><?php the_author(); ?></strong></span>
					<span class="lca__dot" aria-hidden="true">&middot;</span>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j M Y' ) ); ?></time>
					<?php if ( $lc_updated ) : ?>
						<span class="lca__dot" aria-hidden="true">&middot;</span>
						<span><?php esc_html_e( 'Updated', 'learncardano' ); ?> <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'j M Y' ) ); ?></time></span>
					<?php endif; ?>
					<span class="lca__dot" aria-hidden="true">&middot;</span>
					<span>
						<?php
						/* translators: %s: minutes */
						printf( esc_html( _n( '%s min read', '%s min read', $lc_minutes, 'learncardano' ) ), esc_html( number_format_i18n( $lc_minutes ) ) );
						?>
					</span>
				</div>
			</header>

			<?php if ( $lc_show_hero ) : ?>
				<figure class="lca__hero">
					<?php
					echo wp_get_attachment_image(
						$lc_thumb_id,
						'large',
						false,
						array(
							'class'         => 'lca__hero-img',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'sizes'         => '(max-width: 1080px) 100vw, 1080px',
						)
					);
					?>
				</figure>
			<?php endif; ?>

			<div class="lca__layout<?php echo $lc_has_toc ? ' lca__layout--toc' : ''; ?>">

				<?php if ( $lc_has_toc ) : ?>
					<aside class="lca__toc">
						<details class="lca__toc-box" open>
							<summary><?php esc_html_e( 'On this page', 'learncardano' ); ?></summary>
							<ol>
								<?php foreach ( $lc_toc as $lc_item ) : ?>
									<li><a href="#<?php echo esc_attr( $lc_item['id'] ); ?>"><?php echo esc_html( $lc_item['text'] ); ?></a></li>
								<?php endforeach; ?>
							</ol>
						</details>
						<script>
							if (window.matchMedia('(max-width: 1199px)').matches) {
								document.currentScript.previousElementSibling.removeAttribute('open');
							}
						</script>
					</aside>
				<?php endif; ?>

				<div class="lca__main">
					<div class="lca__content entry-content">
						<?php echo $lc_content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?>
					</div>

					<footer class="lca__footer">
						<?php the_tags( '<div class="lca__tags">', '', '</div>' ); ?>

						<div class="lca__share">
							<span class="lca__share-label"><?php esc_html_e( 'Share', 'learncardano' ); ?></span>
							<a href="https://twitter.com/intent/tweet?url=<?php echo $lc_share_url; ?>&amp;text=<?php echo $lc_share_text; ?>" target="_blank" rel="noopener">X</a>
							<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $lc_share_url; ?>" target="_blank" rel="noopener">LinkedIn</a>
							<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $lc_share_url; ?>" target="_blank" rel="noopener">Facebook</a>
							<a href="https://t.me/share/url?url=<?php echo $lc_share_url; ?>&amp;text=<?php echo $lc_share_text; ?>" target="_blank" rel="noopener">Telegram</a>
							<button type="button" class="lca__copy" data-url="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Copy link', 'learncardano' ); ?></button>
						</div>

						<?php if ( $lc_bio ) : ?>
							<div class="lca__author">
								<?php echo get_avatar( get_the_author_meta( 'ID' ), 144, '', '', array( 'class' => 'lca__author-img' ) ); ?>
								<div>
									<div class="lca__author-label"><?php esc_html_e( 'Written by', 'learncardano' ); ?></div>
									<p class="lca__author-name"><?php the_author(); ?></p>
									<p><?php echo wp_kses_post( $lc_bio ); ?></p>
								</div>
							</div>
						<?php endif; ?>
					</footer>
				</div>
			</div>
		</article><!-- #post-<?php the_ID(); ?> -->

		<div class="lca__wide">
			<?php
			the_post_navigation(
				array(
					'prev_text' => '<span class="screen-reader-text">' . __( 'Previous article', 'learncardano' ) . '</span><span aria-hidden="true" class="nav-subtitle">&larr; ' . __( 'Previous', 'learncardano' ) . '</span> <span class="nav-title">%title</span>',
					'next_text' => '<span class="screen-reader-text">' . __( 'Next article', 'learncardano' ) . '</span><span aria-hidden="true" class="nav-subtitle">' . __( 'Next', 'learncardano' ) . ' &rarr;</span> <span class="nav-title">%title</span>',
				)
			);
			?>

			<?php $lc_related = $lc_is_article ? lc_related_posts( get_the_ID() ) : array(); ?>
			<?php if ( $lc_related ) : ?>
				<section class="lca__related" aria-labelledby="lca-related-title">
					<h2 id="lca-related-title" class="lca__related-title"><?php esc_html_e( 'Keep reading', 'learncardano' ); ?></h2>
					<div class="lca__related-grid">
						<?php foreach ( $lc_related as $lc_post ) : ?>
							<?php $lc_rel_cat = lc_post_primary_category( $lc_post->ID ); ?>
							<a class="lca__rcard" href="<?php echo esc_url( get_permalink( $lc_post ) ); ?>">
								<div class="lca__rcard-media">
									<?php if ( has_post_thumbnail( $lc_post ) ) : ?>
										<?php echo get_the_post_thumbnail( $lc_post, 'medium_large', array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 780px) 100vw, 344px' ) ); ?>
									<?php else : ?>
										<div class="pa__placeholder" aria-hidden="true"><span class="pa__placeholder-mark">Learn Cardano</span></div>
									<?php endif; ?>
								</div>
								<div class="lca__rcard-body">
									<div class="lca__rcard-meta">
										<?php echo esc_html( get_the_date( 'd M, Y', $lc_post ) ); ?>
										<?php if ( $lc_rel_cat ) : ?>
											<span class="pa__cat"><?php echo esc_html( $lc_rel_cat->name ); ?></span>
										<?php endif; ?>
									</div>
									<h3 class="lca__rcard-title"><?php echo esc_html( get_the_title( $lc_post ) ); ?></h3>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="lca__comments">
					<?php comments_template(); ?>
				</div>
			<?php endif; ?>
		</div>
	</main><!-- .lca -->

	<script>
		document.querySelectorAll('.lca__copy').forEach(function (button) {
			button.addEventListener('click', function () {
				var label = button.textContent;
				navigator.clipboard.writeText(button.dataset.url).then(function () {
					button.textContent = '<?php echo esc_js( __( 'Link copied', 'learncardano' ) ); ?>';
					setTimeout(function () { button.textContent = label; }, 2000);
				});
			});
		});
	</script>

	<?php
endwhile;

get_footer();
