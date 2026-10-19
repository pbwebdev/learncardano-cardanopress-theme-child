<?php
/**
 * Outgoing mail without WP Mail SMTP: send through Resend's SMTP relay
 * (smtp.resend.com:465, implicit TLS, user "resend", the API key from the
 * LC_RESEND_API_KEY constant in wp-config.php) with the From settings WP Mail
 * SMTP used: address always mail@learncardano.io, name "Learn Crypto" unless
 * the sender set its own (WP Mail SMTP had "force from name" off).
 *
 * Anything other than production (staging2 is a full clone with real
 * subscriber addresses) never sends mail at all.
 *
 * @package CardanoPress Bootstrap
 */

defined( 'LC_MAIL_FROM_EMAIL' ) || define( 'LC_MAIL_FROM_EMAIL', 'mail@learncardano.io' );
defined( 'LC_MAIL_FROM_NAME' ) || define( 'LC_MAIL_FROM_NAME', 'Learn Crypto' );

/**
 * Staging and local copies must never email anyone: short-circuit wp_mail().
 */
add_filter(
	'pre_wp_mail',
	function ( $return ) {
		if ( 'production' === wp_get_environment_type() ) {
			return $return;
		}
		if ( true === $return ) {
			return true; // Already handled: the staging test outbox captured it (includes/auth/dev-outbox.php).
		}
		error_log( 'learncardano: wp_mail suppressed on ' . wp_get_environment_type() . ' environment' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return false;
	},
	1
);

add_filter(
	'wp_mail_from',
	function () {
		return LC_MAIL_FROM_EMAIL;
	},
	20
);

add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		// Only replace core's default, as WP Mail SMTP did.
		return ( '' === $name || 'WordPress' === $name ) ? LC_MAIL_FROM_NAME : $name;
	},
	20
);

/**
 * Point PHPMailer at Resend. Skipped while the key isn't configured (mail
 * then goes out via the host's PHP mail) and while WP Mail SMTP is still
 * active, so the two never fight over the mailer.
 */
add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		if ( ! defined( 'LC_RESEND_API_KEY' ) || ! LC_RESEND_API_KEY || function_exists( 'wp_mail_smtp' ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = 'smtp.resend.com'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port       = 465; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = 'ssl'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth   = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Username   = 'resend'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Password   = LC_RESEND_API_KEY; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
);
