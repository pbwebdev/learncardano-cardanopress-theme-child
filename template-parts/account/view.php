<?php
/**
 * Template for every /account/* page (chosen by lc_auth_prepare_view()).
 *
 * @package CardanoPress Bootstrap
 */

get_header();
$lc_view = lc_auth_view();
$lc_part = 'notfound';
if ( 'home' === $lc_view['view'] ) {
	$lc_part = is_user_logged_in() ? 'manage' : 'signin';
} elseif ( 'email-confirm' === $lc_view['view'] ) {
	$lc_part = 'email-confirm';
}
?>
<main id="main" class="lc-account container py-5">
	<div class="row justify-content-center">
		<div class="col-lg-8 col-xl-6">
			<?php get_template_part( 'template-parts/account/' . $lc_part ); ?>
		</div>
	</div>
</main>
<?php
get_footer();
