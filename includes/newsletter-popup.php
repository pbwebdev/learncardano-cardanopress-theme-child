<?php
/**
 * Newsletter popup, replacing WP Popups popup 27449 ("Newsletter Signup").
 *
 * Same content, look and behaviour: opens once the visitor has scrolled
 * halfway down any front-end page, is not shown again for 7 days after it is
 * closed (cookie spu_closing_27449) or for 30 days after a signup (cookie
 * spu_conversion_27449). The cookie names are the plugin's, so visitors who
 * already closed or converted keep their state.
 *
 * Every page is cached, so the dialog is always printed and assets/js/forms.js
 * decides whether to open it. The form sits in a <template> and forms.css and
 * reCAPTCHA load only when the popup opens, so normal page views pay nothing
 * but the small dialog markup.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Whether this request should carry the popup.
 */
function lc_newsletter_popup_enabled() {
	$enabled = ! is_admin()
		&& ! wp_doing_ajax()
		&& ! is_feed()
		&& ! is_embed()
		&& ! is_customize_preview()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		// The newsletter page already shows the form.
		&& ! is_page( 'newsletter' );
	return (bool) apply_filters( 'lc_newsletter_popup_enabled', $enabled );
}

/**
 * Print the dialog (closed) in the footer.
 */
function lc_newsletter_popup_markup() {
	if ( ! lc_newsletter_popup_enabled() ) {
		return;
	}
	$form = lc_form_render(
		'newsletter',
		array(
			'show_title'       => false,
			'show_description' => false,
			// forms.js loads forms.css when the popup opens.
			'enqueue'          => false,
			'anchor'           => 'newsletter-popup',
		)
	);
	if ( '' === $form ) {
		return;
	}
	?>
	<dialog class="lc-popup" id="lc-popup-27449" aria-labelledby="lc-popup-27449-title" data-trigger-percent="50" data-closing-cookie="spu_closing_27449" data-closing-days="7" data-conversion-cookie="spu_conversion_27449" data-conversion-days="30">
		<div class="lc-popup__box">
			<button type="button" class="lc-popup__close" data-lc-popup-close aria-label="<?php esc_attr_e( 'Close', 'learncardano' ); ?>"><span aria-hidden="true">&times;</span></button>
			<h3 class="lc-popup__title" id="lc-popup-27449-title"><?php esc_html_e( 'Keep Up To Date!', 'learncardano' ); ?></h3>
			<p class="lc-popup__text"><?php esc_html_e( 'Sign up for our newsletter. Latest crypto news, airdrops, courses and everything you need to know about Cardano, Midnight and the wider crypto space.', 'learncardano' ); ?></p>
			<div class="lc-popup__form" data-lc-popup-slot></div>
			<template data-lc-popup-template><?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped by template-parts/form-*.php. ?></template>
		</div>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'lc_newsletter_popup_markup', 5 );
