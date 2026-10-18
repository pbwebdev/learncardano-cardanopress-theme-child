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
