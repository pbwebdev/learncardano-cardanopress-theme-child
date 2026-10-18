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
