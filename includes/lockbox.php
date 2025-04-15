<?php

/**
 * @package ThemePlate
 */

function lc_lockbox() {
	$post_filter = get_field('post_type', 'option') ?: [];
	$post_type = get_post_type();

	// Skip if post type is not filtered, or in admin, or user is logged in
	if (!in_array($post_type, $post_filter) || is_admin() || is_user_logged_in()) {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;

		if (in_array('student', $roles)) {
			the_content();
		}

	} else {
		?>

		<div class="excerpt">
			<?php
				the_excerpt();
			?>
		</div>

		<div class="subscriber-lock-box py-3">
			<p><strong>Full show notes are for Members only.</strong></p>
			<p>Log in with your wallet that is holding a CardanoPress Wapuu NFT
				to continue reading and access exclusive resources.</p>
			<p>
				<?php echo do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]'); ?>
				Get a <a href="https://www.jpg.store/collection/cardanopresswapuus?tab=items"
				target="_blank">Wapuu NFT on JPG.store</a>.
			</p>
		</div>
		<?php
	}
}
