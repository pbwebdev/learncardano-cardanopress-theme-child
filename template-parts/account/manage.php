<?php
/**
 * /account/ for signed-in members: profile, email, sign-in methods,
 * sessions and deleting the account. Every change is a POST form handled by
 * lc_auth_post_home() (includes/auth/account.php).
 *
 * @package CardanoPress Bootstrap
 */

$lc_user     = wp_get_current_user();
$lc_notice   = lc_auth_notice();
$lc_staff    = lc_auth_is_privileged( $lc_user );
$lc_verified = lc_auth_email_verified( $lc_user );
$lc_wallets  = lc_auth_wallets( $lc_user->ID );
$lc_info     = (array) get_user_meta( $lc_user->ID, 'lc_wallet_info', true );
$lc_methods  = lc_auth_methods( $lc_user );
lc_auth_load( 'oauth' );
$lc_idps     = lc_auth_idps( $lc_user->ID );
$lc_idp_info = (array) get_user_meta( $lc_user->ID, 'lc_idp_info', true );
$lc_date     = function ( $ts ) {
	return $ts ? wp_date( get_option( 'date_format' ), (int) $ts ) : '';
};
?>
<h1 class="entry-title"><?php esc_html_e( 'Your account', 'learncardano' ); ?></h1>
<?php if ( $lc_notice ) : ?>
	<p class="lc-auth__notice lc-auth__notice--<?php echo esc_attr( $lc_notice[0] ); ?>" role="status"><?php echo esc_html( $lc_notice[1] ); ?></p>
<?php endif; ?>

<?php if ( $lc_staff ) : ?>
	<section class="lc-auth__section">
		<p><?php esc_html_e( 'This account signs in with a password. Manage it in your WordPress profile.', 'learncardano' ); ?></p>
		<p><a class="btn btn-outline-primary btn-sm" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Open profile', 'learncardano' ); ?></a></p>
		<?php lc_auth_form_open( 'signout' ); ?>
			<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Sign out', 'learncardano' ); ?></button>
		</form>
	</section>
	<?php return; ?>
<?php endif; ?>

<?php if ( '' === $lc_user->user_email ) : ?>
	<p class="lc-auth__notice"><?php esc_html_e( 'Add an email address so you can sign in without your wallet.', 'learncardano' ); ?> <a href="#email"><?php esc_html_e( 'Add email', 'learncardano' ); ?></a></p>
<?php endif; ?>
<?php if ( lc_auth_needs_name( $lc_user ) ) : ?>
	<p class="lc-auth__notice"><?php esc_html_e( 'Choose a display name: it is what appears next to your comments.', 'learncardano' ); ?> <a href="#display-name"><?php esc_html_e( 'Choose a name', 'learncardano' ); ?></a></p>
<?php endif; ?>

<section class="lc-auth__section" id="display-name">
	<h2><?php esc_html_e( 'Display name', 'learncardano' ); ?></h2>
	<?php lc_auth_form_open( 'profile', 'lc-form lc-auth__form' ); ?>
		<label class="lc-form__label" for="lc-display-name"><?php esc_html_e( 'Shown next to your comments', 'learncardano' ); ?></label>
		<input class="lc-form__input" type="text" id="lc-display-name" name="display_name" minlength="2" maxlength="40" required value="<?php echo esc_attr( lc_auth_needs_name( $lc_user ) ? '' : $lc_user->display_name ); ?>" autocomplete="nickname">
		<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Save name', 'learncardano' ); ?></button>
	</form>
</section>

<section class="lc-auth__section" id="email">
	<h2><?php esc_html_e( 'Email', 'learncardano' ); ?></h2>
	<?php if ( '' !== $lc_user->user_email ) : ?>
		<p>
			<strong><?php echo esc_html( $lc_user->user_email ); ?></strong>
			<span class="lc-auth__badge <?php echo $lc_verified ? 'is-ok' : ''; ?>"><?php echo $lc_verified ? esc_html__( 'Verified', 'learncardano' ) : esc_html__( 'Not verified', 'learncardano' ); ?></span>
		</p>
		<?php if ( ! $lc_verified ) : ?>
			<?php lc_auth_form_open( 'email_verify' ); ?>
				<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Send verification link', 'learncardano' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>
	<?php lc_auth_form_open( 'email_change', 'lc-form lc-auth__form' ); ?>
		<label class="lc-form__label" for="lc-new-email"><?php echo '' === $lc_user->user_email ? esc_html__( 'Email address', 'learncardano' ) : esc_html__( 'Change email address', 'learncardano' ); ?></label>
		<input class="lc-form__input" type="email" id="lc-new-email" name="email" required autocomplete="email">
		<p class="lc-form__desc"><?php esc_html_e( 'We email a link to the new address. Nothing changes until you open it.', 'learncardano' ); ?></p>
		<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Send confirmation link', 'learncardano' ); ?></button>
	</form>
</section>

<section class="lc-auth__section" id="sign-in-methods">
	<h2><?php esc_html_e( 'Ways to sign in', 'learncardano' ); ?></h2>
	<ul class="lc-auth__list">
		<li>
			<strong><?php esc_html_e( 'Email link', 'learncardano' ); ?></strong>
			<?php echo $lc_verified ? esc_html__( 'On', 'learncardano' ) : esc_html__( 'Verify your email above to use this.', 'learncardano' ); ?>
		</li>
		<?php foreach ( $lc_wallets as $lc_stake ) : ?>
			<li>
				<strong><?php esc_html_e( 'Wallet', 'learncardano' ); ?></strong>
				<code><?php echo esc_html( lc_auth_mask_stake( $lc_stake ) ); ?></code>
				<small>
					<?php
					$lc_linked = $lc_date( $lc_info[ $lc_stake ]['linked'] ?? 0 );
					$lc_used   = $lc_date( $lc_info[ $lc_stake ]['used'] ?? 0 );
					echo esc_html( trim( ( $lc_linked ? sprintf( /* translators: %s: date */ __( 'Linked %s.', 'learncardano' ), $lc_linked ) : '' ) . ( $lc_used ? ' ' . sprintf( /* translators: %s: date */ __( 'Last used %s.', 'learncardano' ), $lc_used ) : '' ) ) );
					?>
				</small>
				<?php lc_auth_form_open( 'unlink', 'lc-auth__inline' ); ?>
					<input type="hidden" name="stake" value="<?php echo esc_attr( $lc_stake ); ?>">
					<button type="submit" class="lc-auth__linkbtn" <?php disabled( ! lc_auth_can_remove( $lc_user, 'wallet:' . $lc_stake ) ); ?>><?php esc_html_e( 'Remove', 'learncardano' ); ?></button>
				</form>
			</li>
		<?php endforeach; ?>
		<?php foreach ( lc_auth_providers( true ) as $lc_pid => $lc_prov ) : ?>
			<?php
			$lc_on = isset( $lc_idps[ $lc_pid ] );
			if ( ! $lc_on && empty( $lc_prov['enabled'] ) ) {
				continue; // Not set up on this site and not connected: nothing to show.
			}
			?>
			<li>
				<strong><?php echo esc_html( $lc_prov['label'] ); ?></strong>
				<?php if ( $lc_on ) : ?>
					<?php if ( ! empty( $lc_idp_info[ $lc_pid ]['hint'] ) ) : ?>
						<code><?php echo esc_html( $lc_idp_info[ $lc_pid ]['hint'] ); ?></code>
					<?php endif; ?>
					<small>
						<?php
						$lc_linked = $lc_date( $lc_idp_info[ $lc_pid ]['linked'] ?? 0 );
						$lc_used   = $lc_date( $lc_idp_info[ $lc_pid ]['used'] ?? 0 );
						echo esc_html( trim( ( $lc_linked ? sprintf( /* translators: %s: date */ __( 'Linked %s.', 'learncardano' ), $lc_linked ) : '' ) . ( $lc_used ? ' ' . sprintf( /* translators: %s: date */ __( 'Last used %s.', 'learncardano' ), $lc_used ) : '' ) ) );
						?>
					</small>
					<?php lc_auth_form_open( 'unlink', 'lc-auth__inline' ); ?>
						<input type="hidden" name="method" value="idp:<?php echo esc_attr( $lc_pid ); ?>">
						<button type="submit" class="lc-auth__linkbtn" <?php disabled( ! lc_auth_can_remove( $lc_user, 'idp:' . $lc_pid ) ); ?>><?php esc_html_e( 'Disconnect', 'learncardano' ); ?></button>
					</form>
				<?php else : ?>
					<?php lc_auth_form_open( 'link_oauth', 'lc-auth__inline' ); ?>
						<input type="hidden" name="provider" value="<?php echo esc_attr( $lc_pid ); ?>">
						<button type="submit" class="lc-auth__linkbtn"><?php esc_html_e( 'Connect', 'learncardano' ); ?></button>
					</form>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( count( lc_auth_usable_methods( $lc_user ) ) < 2 ) : ?>
		<p class="lc-form__desc"><?php esc_html_e( 'This is your only way to sign in, so it cannot be removed. Add another one first.', 'learncardano' ); ?></p>
	<?php endif; ?>

	<div class="lc-auth__connect" data-lc-account-wallets data-nonce="<?php echo esc_attr( wp_create_nonce( 'lc_wallet_link' ) ); ?>">
		<button type="button" class="lc-form__submit lc-auth__btn" data-lc-auth-wallet hidden><?php esc_html_e( 'Connect a wallet', 'learncardano' ); ?></button>
		<ul class="lc-auth__wallets" data-lc-auth-wallets hidden></ul>
		<div class="lc-auth__status" role="status" aria-live="polite" tabindex="-1" data-lc-auth-status></div>
	</div>

	<?php lc_auth_form_open( 'password_link' ); ?>
		<p class="lc-form__desc"><?php echo $lc_verified ? esc_html__( 'Prefer a password? We email you a link to set one.', 'learncardano' ) : esc_html__( 'Verify your email above to set a password.', 'learncardano' ); ?></p>
		<button type="submit" class="lc-form__submit lc-auth__btn" <?php disabled( ! $lc_verified ); ?>><?php esc_html_e( 'Set or change password', 'learncardano' ); ?></button>
	</form>
</section>

<section class="lc-auth__section" id="sessions">
	<h2><?php esc_html_e( 'Sessions', 'learncardano' ); ?></h2>
	<div class="lc-auth__row">
		<?php lc_auth_form_open( 'signout_others', 'lc-auth__inline' ); ?>
			<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Sign out other devices', 'learncardano' ); ?></button>
		</form>
		<?php lc_auth_form_open( 'signout', 'lc-auth__inline' ); ?>
			<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Sign out', 'learncardano' ); ?></button>
		</form>
	</div>
</section>

<section class="lc-auth__section" id="delete">
	<details>
		<summary><?php esc_html_e( 'Delete my account', 'learncardano' ); ?></summary>
		<p><?php esc_html_e( 'This removes your account and its sign-in methods. Your comments stay, but show "Former member". It cannot be undone.', 'learncardano' ); ?></p>
		<?php if ( lc_auth_session_age() > 30 * MINUTE_IN_SECONDS ) : ?>
			<p class="lc-auth__notice"><?php echo esc_html( lc_auth_text( 'delete_recent' ) ); ?></p>
		<?php else : ?>
			<?php lc_auth_form_open( 'delete', 'lc-form lc-auth__form' ); ?>
				<label class="lc-form__label" for="lc-delete-confirm"><?php esc_html_e( 'Type DELETE to confirm', 'learncardano' ); ?></label>
				<input class="lc-form__input" type="text" id="lc-delete-confirm" name="confirm" required autocomplete="off">
				<button type="submit" class="lc-form__submit lc-auth__btn lc-auth__danger"><?php esc_html_e( 'Delete my account', 'learncardano' ); ?></button>
			</form>
		<?php endif; ?>
	</details>
</section>
