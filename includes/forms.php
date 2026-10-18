<?php
/**
 * Site forms without Gravity Forms: Contact, Newsletter Signup and Get Latest
 * Course Updates, embedded with [lc_form id="contact|newsletter|course-updates"].
 *
 * Fields, labels, choices, consent text, confirmations and notification texts
 * reproduce the Gravity Forms forms 1-3 they replace. Nothing is stored on the
 * site: a submission is validated, then sent to Mailchimp (newsletter and
 * course updates) and/or emailed (wp_mail; SMTP lives in includes/mail.php).
 *
 * Spam protection: nonce, honeypot field, signed timestamp with a minimum fill
 * time, a per-IP rate limit and reCAPTCHA v2 (checkbox) when the keys exist.
 * Pages are cached for visitors, so assets/js/forms.js fetches a fresh nonce
 * and timestamp when someone starts filling a form in, then submits through
 * admin-ajax and shows the result inline. Without JS the form posts to
 * admin-post.php and redirects back with the status in the query string.
 *
 * Secrets come from wp-config constants and every one is optional:
 * LC_MAILCHIMP_API_KEY, LC_MAILCHIMP_LIST_ID, LC_RECAPTCHA_SITE_KEY,
 * LC_RECAPTCHA_SECRET. Styles: assets/css/forms.css under `lc-form`.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Admin-ajax / admin-post action names.
 */
const LC_FORM_ACTION       = 'lc_form_submit';
const LC_FORM_TOKEN_ACTION = 'lc_form_token';

/**
 * Seconds a person needs at least between starting a form and sending it.
 */
const LC_FORM_MIN_SECONDS = 3;

/**
 * The forms, keyed by the id used in the shortcode.
 *
 * Field keys double as the POST names (prefixed `lc_`). Supported field keys:
 * type (text|email|url|select|textarea|checkbox), label, required, description,
 * placeholder, choices, maxlength, autocomplete, hide_label, confirm (email),
 * sublabels (email confirm), checkbox_label (HTML), half, show_if.
 *
 * @return array<string, array<string, mixed>>
 */
function lc_form_definitions() {
	static $forms = null;
	if ( null !== $forms ) {
		return $forms;
	}

	$privacy_url = get_privacy_policy_url();
	$privacy_url = $privacy_url ? $privacy_url : home_url( '/privacy-policy/' );

	$forms = array(
		// Gravity Forms form 1.
		'contact'        => array(
			'title'        => 'Contact',
			'description'  => '',
			'show_title'   => false,
			'button'       => __( 'Submit', 'learncardano' ),
			'confirmation' => __( 'Thanks for contacting us! We will get in touch with you shortly.', 'learncardano' ),
			'captcha'      => __( 'Anti spam', 'learncardano' ),
			'fields'       => array(
				'name'          => array(
					'type'         => 'text',
					'label'        => __( 'Name', 'learncardano' ),
					'autocomplete' => 'name',
				),
				'email'         => array(
					'type'      => 'email',
					'label'     => __( 'Email', 'learncardano' ),
					'required'  => true,
					'confirm'   => true,
					'sublabels' => array( __( 'Enter Email', 'learncardano' ), __( 'Confirm Email', 'learncardano' ) ),
				),
				'consent'       => array(
					'type'           => 'checkbox',
					'label'          => __( 'Agree to privacy policy', 'learncardano' ),
					'required'       => true,
					'checkbox_label' => __( 'I agree to the privacy policy.', 'learncardano' ),
				),
				'topic'         => array(
					'type'        => 'select',
					'label'       => __( 'Contact topic', 'learncardano' ),
					'required'    => true,
					'placeholder' => __( "Please note, I'm no longer doing interviews", 'learncardano' ),
					'description' => __( "Please note, I'm no longer doing interviews", 'learncardano' ),
					'choices'     => array(
						'testing'         => __( 'I actually was just testing the form', 'learncardano' ),
						'projectcatalyst' => __( 'Project Catalyst', 'learncardano' ),
						'partnership'     => __( 'Partnership', 'learncardano' ),
						'Other'           => __( 'Other', 'learncardano' ),
					),
				),
				'catalyst_link' => array(
					'type'        => 'url',
					'label'       => __( 'Catalyst Proposal Link', 'learncardano' ),
					'placeholder' => 'https://',
					'description' => __( 'Please provide your IdeaScale link.', 'learncardano' ),
					'show_if'     => array( 'topic', 'in', array( 'projectcatalyst' ) ),
				),
				'website'       => array(
					'type'        => 'url',
					'label'       => __( 'Your Project Website', 'learncardano' ),
					'placeholder' => 'https://',
					'show_if'     => array( 'topic', 'in', array( 'partnership', 'projectcatalyst' ) ),
				),
				'comments'      => array(
					'type'        => 'textarea',
					'label'       => __( 'Comments', 'learncardano' ),
					'required'    => true,
					'maxlength'   => 600,
					'description' => __( 'Max 500 characters, provide links, references and everything needed to learn more about your project.', 'learncardano' ),
					// Gravity Forms hid the comments when someone was "just testing".
					'show_if'     => array( 'topic', 'not_in', array( 'testing' ) ),
				),
			),
			'notifications' => array(
				array(
					'to'       => 'admin',
					'subject'  => 'Learn Cardano: Contact submission from {form_title}',
					'body'     => "{all_fields}\n\n&nbsp;\n\nEmail from the Learn Cardano website.",
					'reply_to' => 'email',
				),
				array(
					'to'        => 'email',
					'from_name' => 'Learn Cardano',
					'subject'   => "You're email has been sent!",
					'body'      => "Hello!\n\nThanks for contacting the Learn Cardano team.\n\nWe do get flooded with requests for support, collaborations, partnerships and interview requests.\n\nWe'll get back to you as soon as possible.\n\nRegards\n\nPeter &amp; Learn Cardano team\n\n&nbsp;\n<ul>\n \t<li><span style=\"font-size: xx-small;\"><a href=\"https://bit.ly/3Qzf7OI\" target=\"_blank\" rel=\"noopener\">YouTube</a></span></li>\n \t<li><span style=\"font-size: xx-small;\"><a href=\"https://spoti.fi/3PdFXdZ\" target=\"_blank\" rel=\"noopener\">Spotify</a></span></li>\n \t<li><span style=\"font-size: xx-small;\"><a href=\"https://chatsoon.app/id/peter-bui-5ec50167\" target=\"_blank\" rel=\"noopener\">Follow &amp; contact Peter (Chatsoon)</a></span></li>\n</ul>",
				),
			),
		),

		// Gravity Forms form 3 (also inside the newsletter popup).
		'newsletter'     => array(
			'title'         => 'Newsletter Signup',
			'description'   => __( 'Newsletter signup', 'learncardano' ),
			'show_title'    => false,
			'button'        => __( 'Keep me up to date!', 'learncardano' ),
			'confirmation'  => __( 'Thank you for signing up. Be on the lookout for our monthly newsletter!', 'learncardano' ),
			'captcha'       => __( 'CAPTCHA', 'learncardano' ),
			'fields'        => array(
				'name'    => array(
					'type'         => 'text',
					'label'        => __( 'Name', 'learncardano' ),
					'required'     => true,
					'autocomplete' => 'given-name',
				),
				'email'   => array(
					'type'       => 'email',
					'label'      => __( 'Email', 'learncardano' ),
					'required'   => true,
					'confirm'    => true,
					'hide_label' => true,
					'sublabels'  => array( __( 'Enter Email', 'learncardano' ), __( 'Confirm Email', 'learncardano' ) ),
				),
				'privacy' => array(
					'type'           => 'checkbox',
					'label'          => __( 'Privacy', 'learncardano' ),
					'required'       => true,
					'checkbox_label' => __( 'I agree with the storage and handling of my data by this website.', 'learncardano' ),
				),
			),
			// The Gravity Forms Mailchimp feed: double opt-in, FNAME, and the
			// one interest group it always assigned.
			'mailchimp'     => array(
				'tags'      => array(),
				'interests' => array( 'd8f13f469d' => true ),
			),
			'notifications' => array(
				array(
					'to'      => 'admin',
					'subject' => 'New submission from {form_title}',
					'body'    => '{all_fields}',
				),
			),
		),

		// Gravity Forms form 2 (course pages). It only stored entries; it now
		// subscribes to Mailchimp with a tag and tells the admin.
		'course-updates' => array(
			'title'         => 'Get Latest Course Updates',
			'description'   => __( 'Sign up to our newsletter to get updates about our courses & resources as they are updated.', 'learncardano' ),
			'show_title'    => true,
			'button'        => __( 'Keep me up to date!', 'learncardano' ),
			'confirmation'  => __( "Thank you for signing up. \n\nBe on the lookout for our monthly newsletter!", 'learncardano' ),
			'captcha'       => __( 'CAPTCHA', 'learncardano' ),
			'fields'        => array(
				'name'    => array(
					'type'         => 'text',
					'label'        => __( 'Name', 'learncardano' ),
					'required'     => true,
					'half'         => true,
					'autocomplete' => 'given-name',
				),
				'email'   => array(
					'type'     => 'email',
					'label'    => __( 'Email', 'learncardano' ),
					'required' => true,
					'half'     => true,
				),
				'course'  => array(
					'type'        => 'text',
					'label'       => __( 'Course', 'learncardano' ),
					'maxlength'   => 200,
					'description' => __( 'Which course are you interested in? Leave a comment to let us know.', 'learncardano' ),
				),
				'privacy' => array(
					'type'           => 'checkbox',
					'label'          => __( 'Privacy', 'learncardano' ),
					'required'       => true,
					'checkbox_label' => sprintf(
						/* translators: %s: privacy policy URL */
						__( 'I agree with the storage and handling of my data by this website. - <a target="_blank" href="%s" rel="noopener noreferrer">Privacy Policy</a>', 'learncardano' ),
						esc_url( $privacy_url )
					),
				),
			),
			'mailchimp'     => array(
				'tags'        => array( 'course-updates' ),
				'course_tags' => true,
			),
			'notifications' => array(
				array(
					'to'      => 'admin',
					'subject' => 'New submission from {form_title}',
					'body'    => '{all_fields}',
				),
			),
		),
	);

	return $forms;
}

/**
 * One form's definition, or null for an unknown id.
 *
 * @return array<string, mixed>|null
 */
function lc_form_get( $form_id ) {
	$forms = lc_form_definitions();
	return isset( $forms[ $form_id ] ) ? $forms[ $form_id ] : null;
}

/**
 * The reCAPTCHA v2 keys, or null when they are not both configured (then the
 * check is skipped and no widget is shown).
 *
 * @return array{site: string, secret: string}|null
 */
function lc_form_recaptcha_keys() {
	if ( ! defined( 'LC_RECAPTCHA_SITE_KEY' ) || ! defined( 'LC_RECAPTCHA_SECRET' ) || ! LC_RECAPTCHA_SITE_KEY || ! LC_RECAPTCHA_SECRET ) {
		return null;
	}
	return array(
		'site'   => (string) LC_RECAPTCHA_SITE_KEY,
		'secret' => (string) LC_RECAPTCHA_SECRET,
	);
}

/**
 * Signed timestamp for the minimum fill time: "time.signature".
 */
function lc_form_sign_time( $form_id, $time = null ) {
	$time = null === $time ? time() : (int) $time;
	return $time . '.' . hash_hmac( 'sha256', $time . '|' . $form_id, wp_salt( 'nonce' ) );
}

/**
 * Seconds since a signed timestamp was issued, or null when it is forged.
 */
function lc_form_elapsed( $form_id, $signed ) {
	$parts = explode( '.', (string) $signed, 2 );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return null;
	}
	if ( ! hash_equals( lc_form_sign_time( $form_id, (int) $parts[0] ), (string) $signed ) ) {
		return null;
	}
	return time() - (int) $parts[0];
}

/**
 * The nonce action for a form.
 */
function lc_form_nonce_action( $form_id ) {
	return 'lc_form_' . $form_id;
}

/**
 * Markup of a form. Called by the shortcode and by the newsletter popup.
 *
 * @param string               $form_id Form id (contact, newsletter, course-updates).
 * @param array<string, mixed> $args    show_title, show_description (bool),
 *                                      enqueue (load forms.css, default true),
 *                                      anchor (wrapper id suffix).
 */
function lc_form_render( $form_id, $args = array() ) {
	$form = lc_form_get( $form_id );
	if ( ! $form ) {
		return '';
	}
	static $instance = 0;
	++$instance;

	$args = wp_parse_args(
		$args,
		array(
			'show_title'       => $form['show_title'],
			'show_description' => $form['show_title'],
			'enqueue'          => true,
			'anchor'           => $form_id,
		)
	);

	if ( $args['enqueue'] ) {
		lc_forms_enqueue_assets();
	}

	// Status after a no-JS submission (admin-post redirect).
	$status = array();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	if ( isset( $_GET['lc_form'], $_GET['lc_status'] ) && $form_id === sanitize_key( wp_unslash( $_GET['lc_form'] ) ) ) {
		$errors = array();
		$pairs  = isset( $_GET['lc_errors'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_GET['lc_errors'] ) ) ) : array();
		foreach ( $pairs as $pair ) {
			$pair = sanitize_key( $pair );
			$dash = strrpos( $pair, '-' );
			if ( false !== $dash && isset( $form['fields'][ substr( $pair, 0, $dash ) ] ) ) {
				$errors[ substr( $pair, 0, $dash ) ] = substr( $pair, $dash + 1 );
			}
		}
		$status = array(
			'status' => sanitize_key( wp_unslash( $_GET['lc_status'] ) ),
			'errors' => $errors,
			'msg'    => isset( $_GET['lc_msg'] ) ? sanitize_key( wp_unslash( $_GET['lc_msg'] ) ) : '',
		);
	}
	// phpcs:enable

	ob_start();
	get_template_part(
		'template-parts/form',
		'wrapper',
		array(
			'form_id'  => $form_id,
			'form'     => $form,
			'uid'      => 'lc-' . $form_id . '-' . $instance,
			'args'     => $args,
			'status'   => $status,
			'recaptcha' => lc_form_recaptcha_keys(),
		)
	);
	return ob_get_clean();
}

/**
 * [lc_form id="contact|newsletter|course-updates" title="false" description="false"]
 */
function lc_form_shortcode( $atts ) {
	$atts    = shortcode_atts(
		array(
			'id'          => '',
			'title'       => '',
			'description' => '',
		),
		$atts,
		'lc_form'
	);
	$form_id = sanitize_key( $atts['id'] );
	$form    = lc_form_get( $form_id );
	if ( ! $form ) {
		return '';
	}
	$args = array();
	if ( '' !== $atts['title'] ) {
		$args['show_title'] = filter_var( $atts['title'], FILTER_VALIDATE_BOOLEAN );
	}
	if ( '' !== $atts['description'] ) {
		$args['show_description'] = filter_var( $atts['description'], FILTER_VALIDATE_BOOLEAN );
	}
	return lc_form_render( $form_id, $args );
}
add_shortcode( 'lc_form', 'lc_form_shortcode' );

/**
 * Error message for a field error code.
 */
function lc_form_error_text( $code ) {
	$texts = array(
		'required' => __( 'This field is required.', 'learncardano' ),
		'email'    => __( 'The email address entered is invalid, please check the formatting (e.g. email@domain.com).', 'learncardano' ),
		'mismatch' => __( 'Your emails do not match.', 'learncardano' ),
		'url'      => __( 'Please enter a valid Website URL (e.g. https://example.com).', 'learncardano' ),
		'choice'   => __( 'Please select one of the options.', 'learncardano' ),
		'long'     => __( 'The text entered exceeds the maximum number of characters.', 'learncardano' ),
		'captcha'  => __( 'The reCAPTCHA was invalid. Go back and try it again.', 'learncardano' ),
	);
	return isset( $texts[ $code ] ) ? $texts[ $code ] : $texts['required'];
}

/**
 * Message shown above a form for a form-level status code.
 */
function lc_form_message_text( $code ) {
	$texts = array(
		'invalid' => __( 'There was a problem with your submission. Please review the fields below.', 'learncardano' ),
		'expired' => __( 'This form has expired. Please reload the page and try again.', 'learncardano' ),
		'fast'    => __( 'That was quick! Please wait a moment and send the form again.', 'learncardano' ),
		'rate'    => __( 'Too many submissions from your connection. Please try again in a few minutes.', 'learncardano' ),
		'captcha' => __( 'Please complete the anti-spam check and send the form again.', 'learncardano' ),
		'failed'  => __( 'Sorry, something went wrong sending the form. Please try again later.', 'learncardano' ),
	);
	return isset( $texts[ $code ] ) ? $texts[ $code ] : $texts['invalid'];
}

/**
 * Whether a show_if rule hides a field for the given submitted values.
 */
function lc_form_field_hidden( $field, $values ) {
	if ( empty( $field['show_if'] ) ) {
		return false;
	}
	list( $other, $op, $list ) = $field['show_if'];
	$value                     = isset( $values[ $other ] ) ? $values[ $other ] : '';
	$in                        = in_array( $value, $list, true );
	return 'in' === $op ? ! $in : $in;
}

/**
 * Read, sanitize and validate a submission.
 *
 * @return array{0: array<string, string>, 1: array<string, string>} Values and field errors (key => code).
 */
function lc_form_validate( $form ) {
	$values = array();
	$errors = array();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in lc_form_handle().
	foreach ( $form['fields'] as $key => $field ) {
		$raw = isset( $_POST[ 'lc_' . $key ] ) ? wp_unslash( $_POST[ 'lc_' . $key ] ) : '';
		$raw = is_string( $raw ) ? $raw : '';
		switch ( $field['type'] ) {
			case 'textarea':
				$values[ $key ] = trim( sanitize_textarea_field( $raw ) );
				break;
			case 'email':
				$values[ $key ] = trim( sanitize_text_field( $raw ) );
				break;
			case 'url':
				$values[ $key ] = trim( sanitize_text_field( $raw ) );
				break;
			case 'checkbox':
				$values[ $key ] = '1' === $raw ? '1' : '';
				break;
			default:
				$values[ $key ] = trim( sanitize_text_field( $raw ) );
		}
	}
	$confirm = isset( $_POST['lc_email_confirm'] ) && is_string( $_POST['lc_email_confirm'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['lc_email_confirm'] ) ) ) : '';
	// phpcs:enable

	foreach ( $form['fields'] as $key => $field ) {
		if ( lc_form_field_hidden( $field, $values ) ) {
			$values[ $key ] = '';
			continue;
		}
		$value = $values[ $key ];
		if ( '' === $value ) {
			if ( ! empty( $field['required'] ) ) {
				$errors[ $key ] = 'required';
			}
			continue;
		}
		if ( ! empty( $field['maxlength'] ) && mb_strlen( $value ) > (int) $field['maxlength'] ) {
			$errors[ $key ] = 'long';
			continue;
		}
		if ( 'email' === $field['type'] ) {
			if ( ! is_email( $value ) ) {
				$errors[ $key ] = 'email';
			} elseif ( ! empty( $field['confirm'] ) && 0 !== strcasecmp( $value, $confirm ) ) {
				$errors[ $key ] = 'mismatch';
			} else {
				$values[ $key ] = sanitize_email( $value );
			}
		} elseif ( 'url' === $field['type'] ) {
			$url = esc_url_raw( $value, array( 'http', 'https' ) );
			if ( ! $url || ! wp_http_validate_url( $url ) ) {
				$errors[ $key ] = 'url';
			} else {
				$values[ $key ] = $url;
			}
		} elseif ( 'select' === $field['type'] && ! isset( $field['choices'][ $value ] ) ) {
			$errors[ $key ] = 'choice';
		} elseif ( 'text' === $field['type'] && mb_strlen( $value ) > 200 ) {
			$errors[ $key ] = 'long';
		}
	}
	return array( $values, $errors );
}

/**
 * The visitor's IP for the rate limit (only ever stored hashed).
 * lc_client_ip() (includes/security.php) trusts CF-Connecting-IP only from
 * Cloudflare's edges, so a forged header can't reset the limit.
 */
function lc_form_ip() {
	if ( function_exists( 'lc_client_ip' ) ) {
		return lc_client_ip();
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Count an attempt and say whether the visitor is over the limit
 * (5 submissions per 10 minutes per IP, across all forms).
 */
function lc_form_rate_limited( $count_it = true ) {
	$key   = 'lc_form_rl_' . substr( hash_hmac( 'sha256', lc_form_ip(), wp_salt( 'auth' ) ), 0, 32 );
	$count = (int) get_transient( $key );
	if ( $count >= (int) apply_filters( 'lc_form_rate_limit', 5 ) ) {
		return true;
	}
	if ( $count_it ) {
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	}
	return false;
}

/**
 * Check a reCAPTCHA v2 response with Google. True when the keys are not set,
 * false for a wrong or missing answer, WP_Error when Google can't be asked.
 *
 * @return true|false|WP_Error
 */
function lc_form_recaptcha_ok() {
	$keys = lc_form_recaptcha_keys();
	if ( ! $keys ) {
		return true;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in lc_form_handle().
	$token = isset( $_POST['g-recaptcha-response'] ) && is_string( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
	if ( '' === $token ) {
		return false;
	}
	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $keys['secret'],
				'response' => $token,
				'remoteip' => lc_form_ip(),
			),
		)
	);
	$body = is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) ) {
		// Fail closed: with the keys set, an unverified submission is not let
		// through (bots could otherwise ride out any Google hiccup).
		error_log( 'lc_form: reCAPTCHA verify failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response ) ) );
		return new WP_Error( 'lc_recaptcha_unavailable', 'reCAPTCHA could not be verified' );
	}
	if ( empty( $body['success'] ) ) {
		return false;
	}
	// A token solved on another site (key reuse / token farms) doesn't count.
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return isset( $body['hostname'] ) && strtolower( (string) $body['hostname'] ) === $host;
}

/**
 * Handle a submission from admin-ajax (JSON) or admin-post (redirect).
 */
function lc_form_handle() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is checked below.
	$form_id = isset( $_POST['lc_form'] ) && is_string( $_POST['lc_form'] ) ? sanitize_key( wp_unslash( $_POST['lc_form'] ) ) : '';
	$form    = lc_form_get( $form_id );
	if ( ! $form ) {
		lc_form_respond( $form_id, false, 'failed', array(), 400 );
	}

	// Honeypot: bots fill every field. Pretend it worked and do nothing.
	if ( ! empty( $_POST['lc_hp'] ) ) {
		lc_form_respond( $form_id, true );
	}

	$nonce = isset( $_POST['_lc_nonce'] ) && is_string( $_POST['_lc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_lc_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, lc_form_nonce_action( $form_id ) ) ) {
		lc_form_respond( $form_id, false, 'expired' );
	}

	$elapsed = lc_form_elapsed( $form_id, isset( $_POST['lc_ts'] ) && is_string( $_POST['lc_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['lc_ts'] ) ) : '' );
	// phpcs:enable
	if ( null === $elapsed || $elapsed > 7 * DAY_IN_SECONDS ) {
		lc_form_respond( $form_id, false, 'expired' );
	}
	if ( $elapsed < LC_FORM_MIN_SECONDS ) {
		lc_form_respond( $form_id, false, 'fast' );
	}

	if ( lc_form_rate_limited( false ) ) {
		lc_form_respond( $form_id, false, 'rate' );
	}

	list( $values, $errors ) = lc_form_validate( $form );
	if ( $errors ) {
		lc_form_respond( $form_id, false, 'invalid', $errors );
	}

	// Count everything that reaches the paid/external calls.
	lc_form_rate_limited( true );
	$captcha = lc_form_recaptcha_ok();
	if ( is_wp_error( $captcha ) ) {
		lc_form_respond( $form_id, false, 'failed' );
	}
	if ( ! $captcha ) {
		lc_form_respond( $form_id, false, 'captcha' );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$source = isset( $_POST['lc_source'] ) ? absint( $_POST['lc_source'] ) : 0;
	$source = $source ? get_post( $source ) : null;
	$source = ( $source && 'publish' === $source->post_status ) ? $source : null;

	$notes = array();
	if ( ! empty( $form['mailchimp'] ) ) {
		$result = lc_form_mailchimp_subscribe( $form_id, $form, $values, $source );
		if ( is_wp_error( $result ) ) {
			// Tell the admin so they can add the person by hand.
			$notes[] = sprintf( 'Mailchimp: not subscribed (%s).', $result->get_error_message() );
		}
	}

	$sent = lc_form_send_notifications( $form, $values, $source, $notes );
	if ( ! $sent && empty( $form['mailchimp'] ) && 'production' === wp_get_environment_type() ) {
		// A contact message that reached nobody is a failure. Off production
		// includes/mail.php stops all mail, so that is expected there.
		lc_form_respond( $form_id, false, 'failed' );
	}

	lc_form_respond( $form_id, true );
}
add_action( 'wp_ajax_' . LC_FORM_ACTION, 'lc_form_handle' );
add_action( 'wp_ajax_nopriv_' . LC_FORM_ACTION, 'lc_form_handle' );
add_action( 'admin_post_' . LC_FORM_ACTION, 'lc_form_handle' );
add_action( 'admin_post_nopriv_' . LC_FORM_ACTION, 'lc_form_handle' );

/**
 * Send the result: JSON for fetch, otherwise redirect back to the form.
 *
 * @param array<string, string> $errors Field key => error code.
 */
function lc_form_respond( $form_id, $ok, $code = '', $errors = array(), $http = 200 ) {
	$form = lc_form_get( $form_id );
	if ( wp_doing_ajax() ) {
		if ( $ok ) {
			wp_send_json_success( array( 'message' => wpautop( esc_html( $form['confirmation'] ) ) ), $http );
		}
		$field_errors = array();
		foreach ( $errors as $key => $error ) {
			$field_errors[ $key ] = lc_form_error_text( $error );
		}
		if ( 'captcha' === $code ) {
			$field_errors['captcha'] = lc_form_error_text( 'captcha' );
		}
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => lc_form_message_text( $code ),
				'errors'  => $field_errors,
			),
			$http
		);
	}

	$back = wp_validate_redirect( wp_get_referer(), home_url( '/' ) );
	$back = remove_query_arg( array( 'lc_form', 'lc_status', 'lc_errors', 'lc_msg' ), $back );
	$args = array(
		'lc_form'   => $form ? $form_id : '',
		'lc_status' => $ok ? 'success' : 'error',
	);
	if ( ! $ok ) {
		$args['lc_msg'] = $code;
		if ( $errors ) {
			// "field-code" pairs, e.g. "email-mismatch,comments-required".
			$pairs = array();
			foreach ( $errors as $key => $error ) {
				$pairs[] = $key . '-' . $error;
			}
			$args['lc_errors'] = implode( ',', $pairs );
		}
	}
	wp_safe_redirect( add_query_arg( $args, $back ) . '#lc-form-' . $form_id, 303 );
	exit;
}

/**
 * Fresh nonce and start time for a form. Pages are cached, so the copies in
 * the HTML can be old; forms.js asks for these when someone starts typing.
 */
function lc_form_token() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this hands out the nonce.
	$form_id = isset( $_POST['form'] ) && is_string( $_POST['form'] ) ? sanitize_key( wp_unslash( $_POST['form'] ) ) : '';
	if ( ! lc_form_get( $form_id ) ) {
		wp_send_json_error( null, 400 );
	}
	nocache_headers();
	wp_send_json_success(
		array(
			'nonce' => wp_create_nonce( lc_form_nonce_action( $form_id ) ),
			'ts'    => lc_form_sign_time( $form_id ),
		)
	);
}
add_action( 'wp_ajax_' . LC_FORM_TOKEN_ACTION, 'lc_form_token' );
add_action( 'wp_ajax_nopriv_' . LC_FORM_TOKEN_ACTION, 'lc_form_token' );

/**
 * Mailchimp list member for a submission: add a new address as pending
 * (double opt-in) with name and interests; an existing member is left as is.
 * Both get the form's tags. Never runs off production.
 *
 * @return true|WP_Error
 */
function lc_form_mailchimp_subscribe( $form_id, $form, $values, $source ) {
	$config = $form['mailchimp'];
	$tags   = isset( $config['tags'] ) ? $config['tags'] : array();
	if ( ! empty( $config['course_tags'] ) && $source && 'sfwd-courses' === $source->post_type ) {
		// Which course page the person signed up on, e.g. "course-lace-wallet".
		$tags[] = 'course-' . $source->post_name;
	}
	// The free-text "Course" answer is not a tag (anyone could create tags in
	// the audience with it); it only goes in the admin email.
	$tags = array_values( array_unique( $tags ) );

	if ( 'production' !== wp_get_environment_type() ) {
		error_log( sprintf( 'lc_form: would subscribe a %s signup to Mailchimp (tags: %s)', $form_id, $tags ? implode( ', ', $tags ) : 'none' ) );
		return true;
	}
	// Either an API key ("…-us21") or the OAuth token Gravity Forms' Mailchimp
	// add-on was connected with (LC_MAILCHIMP_OAUTH_TOKEN + LC_MAILCHIMP_SERVER).
	$use_oauth = defined( 'LC_MAILCHIMP_OAUTH_TOKEN' ) && LC_MAILCHIMP_OAUTH_TOKEN && defined( 'LC_MAILCHIMP_SERVER' ) && LC_MAILCHIMP_SERVER;
	if ( ! defined( 'LC_MAILCHIMP_LIST_ID' ) || ! LC_MAILCHIMP_LIST_ID || ( ! $use_oauth && ( ! defined( 'LC_MAILCHIMP_API_KEY' ) || ! LC_MAILCHIMP_API_KEY ) ) ) {
		error_log( 'lc_form: Mailchimp is not configured (LC_MAILCHIMP_LIST_ID and an API key or OAuth token)' );
		return new WP_Error( 'lc_mailchimp_config', 'Mailchimp is not configured' );
	}

	if ( $use_oauth ) {
		$dc         = (string) LC_MAILCHIMP_SERVER;
		$auth_value = 'OAuth ' . LC_MAILCHIMP_OAUTH_TOKEN;
	} else {
		$key        = (string) LC_MAILCHIMP_API_KEY;
		$dc         = substr( strrchr( $key, '-' ), 1 );
		$auth_value = 'Basic ' . base64_encode( 'learncardano:' . $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
	}
	if ( ! $dc || ! preg_match( '/^[a-z]+[0-9]+$/', $dc ) ) {
		error_log( 'lc_form: Mailchimp datacenter is missing or malformed' );
		return new WP_Error( 'lc_mailchimp_config', 'Mailchimp datacenter is malformed' );
	}

	$email  = strtolower( $values['email'] );
	$member = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . rawurlencode( (string) LC_MAILCHIMP_LIST_ID ) . '/members/' . md5( $email );
	$auth   = array(
		'Authorization' => $auth_value,
		'Content-Type'  => 'application/json',
	);

	// Look the address up first. The form is public, so anyone can submit
	// someone else's email: an existing member's name, interests and status
	// must not change, they only get the tags.
	$lookup = wp_remote_get(
		add_query_arg( 'fields', 'status', $member ),
		array(
			'timeout' => 15,
			'headers' => $auth,
		)
	);
	$exists = is_wp_error( $lookup ) || 404 !== (int) wp_remote_retrieve_response_code( $lookup );
	$error  = $exists ? lc_form_mailchimp_error( $lookup ) : '';
	if ( $error ) {
		error_log( sprintf( 'lc_form: Mailchimp lookup failed for a %s signup: %s', $form_id, $error ) );
		return new WP_Error( 'lc_mailchimp', $error );
	}

	if ( ! $exists ) {
		$body = array(
			'email_address' => $email,
			'status_if_new' => 'pending',
			'merge_fields'  => array( 'FNAME' => isset( $values['name'] ) ? $values['name'] : '' ),
		);
		if ( ! empty( $config['interests'] ) ) {
			$body['interests'] = $config['interests'];
		}

		$response = wp_remote_request(
			$member,
			array(
				'method'  => 'PUT',
				'timeout' => 15,
				'headers' => $auth,
				'body'    => wp_json_encode( $body ),
			)
		);
		$error = lc_form_mailchimp_error( $response );
		if ( $error ) {
			error_log( sprintf( 'lc_form: Mailchimp subscribe failed for a %s signup: %s', $form_id, $error ) );
			return new WP_Error( 'lc_mailchimp', $error );
		}
	}

	if ( $tags ) {
		$response = wp_remote_post(
			$member . '/tags',
			array(
				'timeout' => 15,
				'headers' => $auth,
				'body'    => wp_json_encode(
					array(
						'tags' => array_map(
							function ( $tag ) {
								return array(
									'name'   => $tag,
									'status' => 'active',
								);
							},
							$tags
						),
					)
				),
			)
		);
		$error = lc_form_mailchimp_error( $response );
		if ( $error ) {
			// The person is on the list; only the tags are missing.
			error_log( sprintf( 'lc_form: Mailchimp tagging failed for a %s signup: %s', $form_id, $error ) );
		}
	}
	return true;
}

/**
 * Error summary of a Mailchimp response (no personal data), or '' on success.
 */
function lc_form_mailchimp_error( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code >= 200 && $code < 300 ) {
		return '';
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return 'HTTP ' . $code . ( ! empty( $body['title'] ) ? ' ' . sanitize_text_field( $body['title'] ) : '' );
}

/**
 * Gravity Forms' {all_fields}: a table of the answered fields.
 */
function lc_form_all_fields( $form, $values, $source, $notes = array() ) {
	$rows = '';
	foreach ( $form['fields'] as $key => $field ) {
		$value = isset( $values[ $key ] ) ? $values[ $key ] : '';
		if ( '' === $value ) {
			continue;
		}
		if ( 'checkbox' === $field['type'] ) {
			$html = '&#10004; ' . wp_kses( $field['checkbox_label'], array( 'a' => array( 'href' => true ) ) );
		} elseif ( 'select' === $field['type'] ) {
			$html = esc_html( $field['choices'][ $value ] );
		} elseif ( 'url' === $field['type'] ) {
			$html = '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( 'email' === $field['type'] ) {
			$html = '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
		} else {
			$html = nl2br( esc_html( $value ) );
		}
		$rows .= '<tr><td style="background:#eaf2fa;padding:6px 10px;font-weight:bold;">' . esc_html( $field['label'] ) . '</td></tr>'
			. '<tr><td style="padding:6px 10px 14px 20px;">' . $html . '</td></tr>';
	}
	if ( $source ) {
		$rows .= '<tr><td style="background:#eaf2fa;padding:6px 10px;font-weight:bold;">Page</td></tr>'
			. '<tr><td style="padding:6px 10px 14px 20px;"><a href="' . esc_url( get_permalink( $source ) ) . '">' . esc_html( get_the_title( $source ) ) . '</a></td></tr>';
	}
	foreach ( $notes as $note ) {
		$rows .= '<tr><td style="padding:6px 10px;color:#b00;">' . esc_html( $note ) . '</td></tr>';
	}
	return '<table width="99%" cellpadding="0" cellspacing="0" style="border:1px solid #e9e9e9;font-family:sans-serif;font-size:13px;">' . $rows . '</table>';
}

/**
 * Send a form's notifications (Gravity Forms' admin notification and, for
 * Contact, the auto-reply, capped at one per address per day). True when at
 * least one email went out.
 */
function lc_form_send_notifications( $form, $values, $source, $notes = array() ) {
	$sent = false;
	foreach ( $form['notifications'] as $notification ) {
		if ( 'admin' === $notification['to'] ) {
			$to = get_option( 'admin_email' );
		} else {
			$to = isset( $values[ $notification['to'] ] ) ? $values[ $notification['to'] ] : '';
		}
		if ( ! $to || ! is_email( $to ) ) {
			continue;
		}

		// One auto-reply per address per day, so the form can't be used to
		// mail-bomb a stranger. The submission itself still goes to the admin.
		$reply_key = '';
		if ( 'admin' !== $notification['to'] ) {
			$reply_key = 'lc_form_ar_' . substr( wp_hash( strtolower( $to ) ), 0, 32 );
			if ( get_transient( $reply_key ) ) {
				continue;
			}
		}

		$body = str_replace( '{all_fields}', lc_form_all_fields( $form, $values, $source, $notes ), $notification['body'] );
		$body = wpautop( $body );

		$from_name = isset( $notification['from_name'] ) ? $notification['from_name'] : 'Learn Cardano';
		$headers   = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <mail@learncardano.io>',
		);
		if ( ! empty( $notification['reply_to'] ) && ! empty( $values[ $notification['reply_to'] ] ) && is_email( $values[ $notification['reply_to'] ] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $values[ $notification['reply_to'] ] );
		}

		$subject = str_replace( '{form_title}', $form['title'], $notification['subject'] );
		if ( wp_mail( $to, $subject, $body, $headers ) ) {
			$sent = true;
			if ( $reply_key ) {
				set_transient( $reply_key, 1, DAY_IN_SECONDS );
			}
		} else {
			error_log( sprintf( 'production' === wp_get_environment_type() ? 'lc_form: "%s" email could not be sent' : 'lc_form: would send "%s" email (mail is off outside production)', $subject ) );
		}
	}
	return $sent;
}

/**
 * Script and styles for the forms. forms.js runs on every front-end page
 * because it also drives the newsletter popup; it is small and deferred.
 * forms.css loads with a rendered form, or lazily when the popup opens.
 */
function lc_forms_register_assets() {
	if ( is_admin() ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/forms.css';
	$js  = '/assets/js/forms.js';

	wp_register_style( 'lc-forms', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
	wp_enqueue_script( 'lc-forms', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
	wp_script_add_data( 'lc-forms', 'strategy', 'defer' );

	$keys = lc_form_recaptcha_keys();
	wp_localize_script(
		'lc-forms',
		'lcForms',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'submit'     => LC_FORM_ACTION,
			'token'      => LC_FORM_TOKEN_ACTION,
			'css'        => add_query_arg( 'ver', file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : '', $uri . $css ),
			'recaptcha'  => $keys ? $keys['site'] : '',
			// Sign-in modal assets (includes/auth/account.php); forms.js loads them on the first click.
			'auth'       => function_exists( 'lc_auth_client_config' ) ? lc_auth_client_config() : null,
			'strings'    => array(
				'sending' => __( 'Sending…', 'learncardano' ),
				'failed'  => lc_form_message_text( 'failed' ),
				'captcha' => lc_form_message_text( 'captcha' ),
			),
		)
	);

	// Load the styles in the head when the page itself embeds a form.
	$post = get_queried_object();
	if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'lc_form' ) ) {
		wp_enqueue_style( 'lc-forms' );
	}
}
add_action( 'wp_enqueue_scripts', 'lc_forms_register_assets' );

/**
 * Make sure a form rendered outside post content (widget, template) still
 * gets its styles (printed in the footer then).
 */
function lc_forms_enqueue_assets() {
	if ( did_action( 'wp_enqueue_scripts' ) ) {
		wp_enqueue_style( 'lc-forms' );
	}
}

/**
 * SiteGround Speed Optimizer strips ?ver= and minifies into fixed, long-cached
 * files; keep the filemtime versions on the form assets (as for courses).
 */
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/(css|js)/forms\.(css|js)';
		return $list;
	}
);
foreach ( array( 'sgo_css_minify_exclude', 'sgo_js_minify_exclude', 'sgo_javascript_combine_exclude', 'sgo_css_combine_exclude' ) as $lc_sgo_filter ) {
	add_filter(
		$lc_sgo_filter,
		function ( $list ) {
			$list[] = 'lc-forms';
			return $list;
		}
	);
}
