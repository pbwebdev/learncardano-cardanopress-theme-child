# Wallet-login crypto tools

Test tooling for `includes/auth/cardano-crypto.php` (CIP-30 `signData` verifier). `tools/` is excluded from deploy.

## Regenerate the vectors

```
node tools/auth/vectors.mjs
```

Needs Node 22 and the dependencies in `tools/mesh/node_modules` (`@emurgo/cardano-message-signing-nodejs`, `@noble/hashes`, `@meshsdk/core`). Run `npm ci` in `tools/mesh` first, or point `LC_NODE_MODULES` at another `node_modules`. Output is deterministic (fixed Ed25519 seeds, fixed test mnemonic) and written to `tools/auth/vectors.json`.

Vectors cover valid signatures (mainnet, testnet, Ledger `hashed:true`, tag-18, emurgo library output, MeshWallet output) and every attack we know of, notably the CardanoPress flaw: an attacker's own key signing with a forged base address that embeds the victim's stake hash.

## Run the tests

No local PHP needed beyond a plain CLI with sodium (PHP 7.4+; the server has 8.2):

```
php tools/auth/test-cose.php .                       # from the theme root
php test-cose.php <dir-with-cardano-crypto.php>      # from a scratch dir holding test-cose.php, vectors.json
```

The argument is a directory containing `cardano-crypto.php` (directly or under `includes/auth/`). Output is PASS/FAIL per case; exit code is non-zero on any failure.

## Account-system flow tests (staging only)

`test-flows.php` exercises the R2 account system (wallet sign-in/link/claim, email link, email change, display names, delete) through the same internal functions the `/account/*` handlers call, with real sodium-made wallet signatures. It refuses to run on production, creates users only with `@lc-test.invalid` addresses and meta `lc_test=1`, and removes them (and test transients and the dev outbox) when done.

```
scp -r tools/auth ~/lc-tools/             # tools/ is not deployed
wp --path=$HOME/www/staging2.learncardano.io/public_html eval-file ~/lc-tools/auth/test-flows.php
wp --path=… eval-file ~/lc-tools/auth/outbox.php email=x@lc-test.invalid   # newest sign-in link mailed to a test address
```

Run `wp rewrite flush` after deploying the theme (the deploy does not flush); the tests check that the `/account/` rule exists.

`tools/accounts/migrate-cardanopress.php` is the one-off clean-up of CardanoPress-era accounts (dry run by default; `apply <backup-dir>`, `restore <backup.json>`).

## Google and Discord sign-in (R3)

Code: `includes/auth/oauth.php` (loaded only by `/account/*` routes and the pages that show the buttons). A provider is on only when both of its constants are set and non-empty in `wp-config.php`:

```php
define( 'LC_GOOGLE_CLIENT_ID',     '…' );
define( 'LC_GOOGLE_CLIENT_SECRET', '…' );
define( 'LC_DISCORD_CLIENT_ID',     '…' );
define( 'LC_DISCORD_CLIENT_SECRET', '…' );
```

Paste them in wp-config only; nothing prints or logs them. Remove a pair (or filter `lc_auth_provider_enabled`) to switch that provider off: its buttons disappear and its routes answer "not available".

**Redirect URIs** to register at each provider, exact, with the trailing slash, one pair per host:

| Host | Google | Discord |
|---|---|---|
| live | `https://learncardano.io/account/oauth/google/callback/` | `https://learncardano.io/account/oauth/discord/callback/` |
| staging | `https://staging2.learncardano.io/account/oauth/google/callback/` | `https://staging2.learncardano.io/account/oauth/discord/callback/` |

**Current setup (owner's choice):** the same Google client and the same Discord app serve BOTH staging and live; both sites run on the same server account and share the same four constants. Both redirect URIs are registered in each. Consequence: anything with the client secret on that account can act as either site, and a user's Google/Discord subject is the same on both sites (so a staging test account and a live account link to the same provider identity independently).

**To split them later:** create a second Google web client and a second Discord application, register only the staging redirect URIs in the new ones (and remove the staging URIs from the live ones), then define the new four constants in the staging `wp-config.php` only. No code change is needed. Identities linked on staging with the old client will not match the new one, so reconnect any staging test accounts.

**Account-linking rule ("connect once, then automatic"):** a sign-in joins an existing account by email only when the provider is authoritative for that address: Google with `email_verified` and either a `@gmail.com`/`@googlemail.com` address or a non-empty `hd` claim; Discord never. Otherwise it is refused with `oauth_email_exists` (sign in by email link, then connect on the account page), and a new member is created without an email.

One Google web client and one Discord application can serve both hosts: list both redirect URIs in each, and put the same four constants in both wp-configs.

- Google: Cloud console, a *Web application* OAuth client, consent screen **In production** (the `openid email profile` scopes need no verification). The id_token is read from the direct TLS token exchange, so no signature check is made (documented in `lc_auth_oauth_google_identity()`).
- Discord: scopes `identify email`. PKCE is on; if Discord rejects `code_verifier` on staging, turn it off with `add_filter( 'lc_auth_oauth_pkce', '__return_false' );` (the `state` and flow-cookie binding stay). Tokens are revoked right after the profile call.
- Routes: `GET /account/oauth/{google|discord}/` (start) and `GET /account/oauth/{google|discord}/callback/`. Connecting from the account page is a POST (`lc_action=link_oauth`) that needs a session at most 30 minutes old.
- Storage: user meta `lc_idp_google` / `lc_idp_discord` (the provider's stable subject), `lc_idp_info` (linked, last used, masked email). Support can disconnect a provider on the user-edit screen.

Tests: the "OAuth" sections of `test-flows.php` mock all provider HTTP with `pre_http_request` (no real call is made) and define `LC_*_CLIENT_ID/SECRET` as `test-client` values only if the site has none.
