<?php

/**
 * @package ThemePlate
 */

function lc_lockbox() {
	$post_filter = get_field( 'post_type', 'option' );
	$allowed_roles = array( 'administrator', 'editor', 'author', 'student' );

	if (
		is_admin() ||
		! in_array( get_post_type(), (array) $post_filter, true ) ||
		( is_user_logged_in() && array() !== array_intersect( $allowed_roles, wp_get_current_user()->roles ) )
	) {
		// In admin or post type is not filtered or user is logged in and has allowed role
		the_content();
		return;
	}

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
