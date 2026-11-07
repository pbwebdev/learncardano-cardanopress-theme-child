<?php
/**
 * Template Name: Brand assets
 *
 * Public brand assets page (/brand-assets/): logos, icon, colours, usage
 * rules and examples in use, in the style of cardano.org/brand-assets and
 * midnight.network/brand-hub. Files live in assets/brand-kit/ and are built
 * from the private brand kit by tools/brand-kit/build.mjs. Only a short list
 * of the kit is published here on purpose.
 *
 * Styles: assets/css/brand-page.css (`.bk__`), enqueued by
 * includes/brand-page.php on this template only. The page's own editor
 * content is not used.
 *
 * @package CardanoPress Bootstrap
 */

$bk_dir = get_stylesheet_directory() . '/assets/brand-kit/';
$bk_uri = get_stylesheet_directory_uri() . '/assets/brand-kit/';

/**
 * URL and human size of a file in assets/brand-kit/.
 *
 * @param string $rel Path inside assets/brand-kit/.
 * @return array{0:string,1:string}
 */
$bk_file = function ( $rel ) use ( $bk_dir, $bk_uri ) {
	$size = file_exists( $bk_dir . $rel ) ? size_format( filesize( $bk_dir . $rel ), 0 ) : '';
	return array( $bk_uri . $rel, $size );
};

/**
 * Responsive example image (WebP at the given widths).
 *
 * @param string $name   Base name in examples/.
 * @param int[]  $widths Built widths, smallest first.
 * @param string $alt    Alt text.
 * @param string $sizes  Sizes attribute.
 * @param int    $ratio_w Intrinsic width ratio.
 * @param int    $ratio_h Intrinsic height ratio.
 */
$bk_img = function ( $name, $widths, $alt, $sizes, $ratio_w, $ratio_h ) use ( $bk_uri ) {
	$set = array();
	foreach ( $widths as $w ) {
		$set[] = esc_url( $bk_uri . 'examples/' . $name . '-' . $w . '.webp' ) . ' ' . $w . 'w';
	}
	$w = $widths[0];
	printf(
		'<img src="%1$s" srcset="%2$s" sizes="%3$s" width="%4$d" height="%5$d" alt="%6$s" loading="lazy" decoding="async">',
		esc_url( $bk_uri . 'examples/' . $name . '-' . $w . '.webp' ),
		esc_attr( implode( ', ', $set ) ),
		esc_attr( $sizes ),
		(int) $w,
		(int) round( $w * $ratio_h / $ratio_w ),
		esc_attr( $alt )
	);
};

list( $bk_zip_url, $bk_zip_size ) = $bk_file( 'learn-crypto-brand-assets.zip' );

// Logos: file base name, label, preview background (light, navy, mist).
$bk_logos = array(
	array( 'learn-crypto-logo-stacked-colour', __( 'Stacked, full colour', 'learncardano' ), 'light' ),
	array( 'learn-crypto-logo-stacked-white', __( 'Stacked, white', 'learncardano' ), 'navy' ),
	array( 'learn-crypto-logo-stacked-navy', __( 'Stacked, navy', 'learncardano' ), 'mist' ),
	array( 'learn-crypto-logo-horizontal-colour', __( 'Horizontal, full colour', 'learncardano' ), 'light' ),
	array( 'learn-crypto-logo-horizontal-white', __( 'Horizontal, white', 'learncardano' ), 'navy' ),
	array( 'learn-crypto-logo-horizontal-navy', __( 'Horizontal, navy', 'learncardano' ), 'mist' ),
);

$bk_icons = array(
	array( 'learn-crypto-icon-colour', __( 'Microphone, full colour', 'learncardano' ), 'light' ),
	array( 'learn-crypto-icon-white', __( 'Microphone, white', 'learncardano' ), 'navy' ),
	array( 'learn-crypto-icon-navy', __( 'Microphone, navy', 'learncardano' ), 'mist' ),
	array( 'learn-crypto-avatar', __( 'Social avatar', 'learncardano' ), 'light' ),
);

// Colours: name, hex, RGB, text colour on the swatch.
$bk_colours = array(
	array( __( 'Magenta', 'learncardano' ), '#E312F1', '227, 18, 241', '#fff' ),
	array( __( 'Violet', 'learncardano' ), '#7834F7', '120, 52, 247', '#fff' ),
	array( __( 'Cyan', 'learncardano' ), '#05B9F2', '5, 185, 242', '#10134B' ),
	array( __( 'Midnight navy', 'learncardano' ), '#10134B', '16, 19, 75', '#fff' ),
	array( __( 'White', 'learncardano' ), '#FFFFFF', '255, 255, 255', '#10134B' ),
);

// Don'ts: modifier class (applies the misuse in CSS), caption.
$bk_donts = array(
	array( 'stretch', __( 'Don’t stretch or squash the logo.', 'learncardano' ) ),
	array( 'recolour', __( 'Don’t change the colours.', 'learncardano' ) ),
	array( 'rotate', __( 'Don’t rotate or tilt it.', 'learncardano' ) ),
	array( 'effects', __( 'Don’t add shadows, glows or outlines.', 'learncardano' ) ),
	array( 'busy', __( 'Don’t place it on busy or low contrast backgrounds.', 'learncardano' ) ),
	array( 'crowd', __( 'Don’t crowd it or crop it at the edges.', 'learncardano' ) ),
);

get_header();
?>

<main class="bk" id="brand">

	<section class="bk__hero" aria-labelledby="bk-title">
		<div class="bk__container">
			<img class="bk__hero-logo" src="<?php echo esc_url( $bk_uri . 'download/learn-crypto-logo-horizontal-white.svg' ); ?>" width="440" height="155" alt="<?php esc_attr_e( 'Learn Crypto Podcast logo', 'learncardano' ); ?>">
			<h1 id="bk-title" class="bk__title"><?php esc_html_e( 'Learn Crypto brand assets', 'learncardano' ); ?></h1>
			<p class="bk__lead"><?php esc_html_e( 'Logos, icons and colours for writing about Learn Crypto, featuring the podcast or listing an episode. Please follow the simple rules below so the brand looks right wherever it shows up.', 'learncardano' ); ?></p>
			<p class="bk__actions">
				<a class="bk__btn bk__btn--primary" href="<?php echo esc_url( $bk_zip_url ); ?>" download>
					<?php
					/* translators: %s: file size */
					printf( esc_html__( 'Download all assets (ZIP, %s)', 'learncardano' ), esc_html( $bk_zip_size ) );
					?>
				</a>
				<a class="bk__btn bk__btn--ghost" href="#rules"><?php esc_html_e( 'Read the brand rules', 'learncardano' ); ?></a>
			</p>
		</div>
	</section>

	<section class="bk__section" id="logos" aria-labelledby="bk-logos">
		<div class="bk__container">
			<h2 id="bk-logos" class="bk__h2"><?php esc_html_e( 'Logo', 'learncardano' ); ?></h2>
			<p class="bk__intro"><?php esc_html_e( 'The stacked logo is the main version. Use the horizontal logo where space is wide and short, such as a header or a banner. Use full colour on white or light backgrounds and the white logo on navy or dark images. SVG files scale to any size; PNG files have a transparent background.', 'learncardano' ); ?></p>
			<div class="bk__grid bk__grid--3">
				<?php foreach ( $bk_logos as $bk_item ) : ?>
					<?php list( $bk_name, $bk_label, $bk_bg ) = $bk_item; ?>
					<figure class="bk__card">
						<div class="bk__preview bk__preview--<?php echo esc_attr( $bk_bg ); ?>">
							<img src="<?php echo esc_url( $bk_uri . 'download/' . $bk_name . '.svg' ); ?>" alt="" width="300" height="300" loading="lazy" decoding="async">
						</div>
						<figcaption class="bk__meta">
							<span class="bk__label"><?php echo esc_html( $bk_label ); ?></span>
							<span class="bk__files">
								<?php foreach ( array( 'svg', 'png' ) as $bk_ext ) : ?>
									<?php list( $bk_url, $bk_size ) = $bk_file( 'download/' . $bk_name . '.' . $bk_ext ); ?>
									<a href="<?php echo esc_url( $bk_url ); ?>" download aria-label="<?php echo esc_attr( sprintf( '%s %s, %s', $bk_label, strtoupper( $bk_ext ), $bk_size ) ); ?>"><?php echo esc_html( strtoupper( $bk_ext ) ); ?></a>
								<?php endforeach; ?>
							</span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="bk__section bk__section--mist" id="icon" aria-labelledby="bk-icon">
		<div class="bk__container">
			<h2 id="bk-icon" class="bk__h2"><?php esc_html_e( 'Icon and podcast cover', 'learncardano' ); ?></h2>
			<p class="bk__intro"><?php esc_html_e( 'The microphone works on its own where the full logo would be too small, such as a profile picture or a small badge. Use it as supplied: the shape is fixed and should never be redrawn.', 'learncardano' ); ?></p>
			<div class="bk__grid bk__grid--4">
				<?php foreach ( $bk_icons as $bk_item ) : ?>
					<?php list( $bk_name, $bk_label, $bk_bg ) = $bk_item; ?>
					<figure class="bk__card">
						<div class="bk__preview bk__preview--<?php echo esc_attr( $bk_bg ); ?> bk__preview--icon">
							<img src="<?php echo esc_url( $bk_uri . 'download/' . $bk_name . '.svg' ); ?>" alt="" width="160" height="160" loading="lazy" decoding="async">
						</div>
						<figcaption class="bk__meta">
							<span class="bk__label"><?php echo esc_html( $bk_label ); ?></span>
							<span class="bk__files">
								<?php foreach ( array( 'svg', 'png' ) as $bk_ext ) : ?>
									<?php list( $bk_url, $bk_size ) = $bk_file( 'download/' . $bk_name . '.' . $bk_ext ); ?>
									<a href="<?php echo esc_url( $bk_url ); ?>" download aria-label="<?php echo esc_attr( sprintf( '%s %s, %s', $bk_label, strtoupper( $bk_ext ), $bk_size ) ); ?>"><?php echo esc_html( strtoupper( $bk_ext ) ); ?></a>
								<?php endforeach; ?>
							</span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>

			<div class="bk__cover">
				<?php $bk_img( 'podcast-cover', array( 600, 1000 ), __( 'Learn Crypto Podcast cover artwork', 'learncardano' ), '(min-width: 768px) 280px, 70vw', 1, 1 ); ?>
				<div>
					<h3 class="bk__h3"><?php esc_html_e( 'Podcast cover', 'learncardano' ); ?></h3>
					<p><?php esc_html_e( 'For listing the show in an app, an article or an event program. Please use it unchanged and don’t add text over it.', 'learncardano' ); ?></p>
					<?php list( $bk_url, $bk_size ) = $bk_file( 'download/learn-crypto-podcast-cover-3000.png' ); ?>
					<a class="bk__btn bk__btn--primary" href="<?php echo esc_url( $bk_url ); ?>" download>
						<?php
						/* translators: %s: file size */
						printf( esc_html__( 'Download cover (PNG, 3000 px, %s)', 'learncardano' ), esc_html( $bk_size ) );
						?>
					</a>
				</div>
			</div>
		</div>
	</section>

	<section class="bk__section" id="colours" aria-labelledby="bk-colours">
		<div class="bk__container">
			<h2 id="bk-colours" class="bk__h2"><?php esc_html_e( 'Colours', 'learncardano' ); ?></h2>
			<p class="bk__intro"><?php esc_html_e( 'Navy and white do most of the work. Magenta, violet and cyan make up the brand gradient, which runs left to right on the microphone and on small accents. Select a colour to copy its hex code.', 'learncardano' ); ?></p>
			<div class="bk__swatches">
				<?php foreach ( $bk_colours as $bk_c ) : ?>
					<?php list( $bk_cname, $bk_hex, $bk_rgb, $bk_ink ) = $bk_c; ?>
					<button type="button" class="bk__swatch" data-bk-copy="<?php echo esc_attr( $bk_hex ); ?>" style="--bk-sw:<?php echo esc_attr( $bk_hex ); ?>;--bk-sw-ink:<?php echo esc_attr( $bk_ink ); ?>">
						<span class="bk__swatch-name"><?php echo esc_html( $bk_cname ); ?></span>
						<span class="bk__swatch-code"><?php echo esc_html( $bk_hex ); ?></span>
						<span class="bk__swatch-code">RGB <?php echo esc_html( $bk_rgb ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="bk__gradient" role="img" aria-label="<?php esc_attr_e( 'Brand gradient: magenta to violet to cyan', 'learncardano' ); ?>">
				<span><?php esc_html_e( 'Brand gradient', 'learncardano' ); ?></span>
				<code>#E312F1 0%, #7834F7 48%, #05B9F2 100%</code>
			</div>
			<p class="bk__note" aria-live="polite" data-bk-copied></p>

			<h3 class="bk__h3"><?php esc_html_e( 'Type', 'learncardano' ); ?></h3>
			<p class="bk__intro"><?php esc_html_e( 'The logo lettering is artwork, so always use the logo files rather than typing the name in a font. For headings next to the logo, a heavy geometric sans such as Inter Black in uppercase is a close match. Use Inter Regular and Bold for body text.', 'learncardano' ); ?></p>
		</div>
	</section>

	<section class="bk__section bk__section--mist" id="rules" aria-labelledby="bk-rules">
		<div class="bk__container">
			<h2 id="bk-rules" class="bk__h2"><?php esc_html_e( 'Brand rules', 'learncardano' ); ?></h2>

			<div class="bk__rules">
				<div class="bk__space">
					<div class="bk__space-box">
						<img src="<?php echo esc_url( $bk_uri . 'download/learn-crypto-logo-horizontal-colour.svg' ); ?>" alt="" width="440" height="155" loading="lazy" decoding="async">
					</div>
				</div>
				<div>
					<h3 class="bk__h3"><?php esc_html_e( 'Do', 'learncardano' ); ?></h3>
					<ul class="bk__list bk__list--do">
						<li><?php esc_html_e( 'Use the files from this page as they are.', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Leave clear space around the logo, at least 15% of the microphone’s width on every side (the dashed area).', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Keep it big enough to read: the stacked logo at least 180 px wide, the icon at least 24 px.', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Pick the version with the best contrast: full colour or navy on light, white on dark.', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Write the name as Learn Crypto, and the show as Learn Crypto Podcast.', 'learncardano' ); ?></li>
					</ul>
				</div>
			</div>

			<h3 class="bk__h3"><?php esc_html_e( 'Don’t', 'learncardano' ); ?></h3>
			<div class="bk__grid bk__grid--3">
				<?php foreach ( $bk_donts as $bk_d ) : ?>
					<figure class="bk__card bk__dont">
						<div class="bk__preview bk__preview--light bk__dont-<?php echo esc_attr( $bk_d[0] ); ?>">
							<img src="<?php echo esc_url( $bk_uri . 'download/learn-crypto-logo-stacked-colour.svg' ); ?>" alt="" width="200" height="200" loading="lazy" decoding="async">
						</div>
						<figcaption class="bk__meta"><span class="bk__label"><?php echo esc_html( $bk_d[1] ); ?></span></figcaption>
					</figure>
				<?php endforeach; ?>
			</div>

			<div class="bk__callout">
				<h3 class="bk__h3"><?php esc_html_e( 'Using the brand with your project', 'learncardano' ); ?></h3>
				<p><?php esc_html_e( 'You’re welcome to use the logo to link to the podcast, list an episode you appeared on or mention Learn Crypto in an article. Please don’t use it in a way that suggests Learn Crypto endorses, sponsors or partners with your project unless we’ve agreed to that in writing. Don’t combine the logo with your own mark or with other project logos to make a new one.', 'learncardano' ); ?></p>
				<p><?php esc_html_e( 'Making merchandise, need another format or not sure if your use is OK?', 'learncardano' ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Get in touch', 'learncardano' ); ?></a>.</p>
			</div>
		</div>
	</section>

	<section class="bk__section" id="in-use" aria-labelledby="bk-use">
		<div class="bk__container">
			<h2 id="bk-use" class="bk__h2"><?php esc_html_e( 'The brand in use', 'learncardano' ); ?></h2>
			<p class="bk__intro"><?php esc_html_e( 'How the logo looks on merchandise and channels. Merchandise images are concept mockups.', 'learncardano' ); ?></p>

			<figure class="bk__example bk__example--wide">
				<?php $bk_img( 'cap', array( 700, 1400 ), __( 'Two navy caps: one with the full stacked logo embroidered on the front, one with only the microphone icon', 'learncardano' ), '(min-width: 1140px) 1100px, 94vw', 1448, 1086 ); ?>
				<figcaption><strong><?php esc_html_e( 'Caps.', 'learncardano' ); ?></strong> <?php esc_html_e( 'Navy cap with the stacked logo or the microphone alone, front and centre, about 40 to 55 mm high. For embroidery use white thread, or two or three separate brand colours, since thread can’t show a smooth gradient. Always approve a stitched sample first.', 'learncardano' ); ?></figcaption>
			</figure>

			<div class="bk__grid bk__grid--3">
				<figure class="bk__example">
					<?php $bk_img( 'tee-navy-front', array( 600, 1000 ), __( 'Navy T-shirt with the white stacked logo on the chest', 'learncardano' ), '(min-width: 992px) 340px, (min-width: 576px) 45vw, 94vw', 1, 1 ); ?>
					<figcaption><strong><?php esc_html_e( 'Navy tee.', 'learncardano' ); ?></strong> <?php esc_html_e( 'White stacked logo, one colour screen print.', 'learncardano' ); ?></figcaption>
				</figure>
				<figure class="bk__example">
					<?php $bk_img( 'tee-white-front', array( 600, 1000 ), __( 'White T-shirt with the full colour stacked logo on the chest', 'learncardano' ), '(min-width: 992px) 340px, (min-width: 576px) 45vw, 94vw', 1, 1 ); ?>
					<figcaption><strong><?php esc_html_e( 'White tee.', 'learncardano' ); ?></strong> <?php esc_html_e( 'Full colour logo, printed direct to garment so the gradient holds.', 'learncardano' ); ?></figcaption>
				</figure>
				<figure class="bk__example">
					<?php $bk_img( 'tee-white-chest', array( 600, 1000 ), __( 'White T-shirt with a small gradient microphone icon on the left chest', 'learncardano' ), '(min-width: 992px) 340px, (min-width: 576px) 45vw, 94vw', 1, 1 ); ?>
					<figcaption><strong><?php esc_html_e( 'Left chest.', 'learncardano' ); ?></strong> <?php esc_html_e( 'The microphone on its own, about 80 to 100 mm wide.', 'learncardano' ); ?></figcaption>
				</figure>
			</div>

			<figure class="bk__example bk__example--wide">
				<?php $bk_img( 'youtube-banner', array( 800, 1600 ), __( 'Learn Crypto Podcast YouTube channel banner on navy', 'learncardano' ), '(min-width: 1140px) 1100px, 94vw', 16, 9 ); ?>
				<figcaption><strong><?php esc_html_e( 'Channel banner.', 'learncardano' ); ?></strong> <?php esc_html_e( 'Horizontal logo on navy, kept inside the centre safe area so it isn’t cropped on phones or TVs.', 'learncardano' ); ?></figcaption>
			</figure>
		</div>
	</section>

</main>

<?php
get_footer();
