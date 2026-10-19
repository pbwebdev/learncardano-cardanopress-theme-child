# Rebrand tooling: Learn Cardano → Learn Crypto

Tools for the database side of the rebrand, plus a crawler to check the result. `tools/` is
never deployed with the theme. Theme template/CSS changes ship through the normal GitHub
deploy. These tools only cover what lives in the database.

| File | What it is |
|---|---|
| `rebrand-db.php` | WP-CLI script: `scan`, `export`, `dry-run`, `apply`, `restore`, `sync-from`, `checksum`. |
| `targets.json` | Locator list (no values) for `export`. 48 targets plus `leave`. |
| `copy-deck.json` | **You write this** from an export: one item per value, with exact `old` and `new`. |
| `content/` | **You write this**: long new values (`new_file`), e.g. `content/page-7-content.html`. |
| `brand-crawl.mjs` | Node 22 crawler (no dependencies). Finds old-brand text on the public site. |
| `allowlist.json` | Contexts the crawler ignores: URLs, slugs, "formerly Learn Cardano", alternateName, Discord name. |
| `make-assets.sh` | ImageMagick 7 (`magick`) script for the podcast cover sizes, default OG image and square logo. |

## Safety rules built into `rebrand-db.php`

- It runs only where `home_url()` is exactly `https://learncardano.io` (**live**) or
  `https://staging2.learncardano.io` (**staging**). Anything else aborts before it reads anything.
- `apply`, `restore` and `sync-from` need the site name as the **last** argument (`live` or
  `staging`), and it must match `home_url()`. `sync-from` refuses to run on live.
- `apply` refuses to start if any item is **MISS** (the current value is neither `old` nor `new`),
  or if a `post_content` change alters the sequence of block names without `"allow_block_change": true`.
- Before each write it re-reads the value. If the value changed since the snapshot, it does not
  write and marks the op `failed`. After each write it reads the value back.
- Only `page`, `attachment`, `nav_menu_item`, `wp_block` and `acf-post-type` posts can be written.
  A deck item for any other type stops the load. Historical types (podcasts, posts, courses/lessons/
  topics/quizzes, KB types) are counted, never written, and checksummed before and after.
- Posts, terms and users are written with `$wpdb->update` plus `clean_*_cache()`. That means no
  hooks, no revisions and no emails. Options go through `update_option()`. For a serialized option
  such as `rank-math-options-titles`, it reads the array, changes one key and saves it. It never
  string-replaces serialized data. `post_modified` is bumped only for **pages** whose title or
  content changes.
- `apply` writes into `<backup-dir>`, all mode 600:
  `manifest-before-<UTC>.json`, `rebrand-journal-<site>-<UTC>.json` (every op with `before`, `after`
  and `state` planned → running → done/failed, rewritten after each write) and
  `manifest-after-<UTC>.json`. It then prints every post/term whose checksum changed and
  `PROTECTED: N changed` for historical types. N must be 0, or the run ends in an error.
- `restore` refuses a journal from another site or another ABSPATH. It walks the ops newest first
  and puts a value back only while it still equals what `apply` wrote. Anything else is reported
  as `CONFLICT` and skipped. A restore writes its own journal, so a restore can be restored too.

## Deck format (`copy-deck.json`)

`export` writes the skeleton. Copy it to `copy-deck.json` and fill in `new`. `{{home}}` in any value
stands for `home_url()`, so one deck works on staging and live.

```json
{
  "items": [
    { "id": "opt-blogname", "type": "option", "option": "blogname", "old": "Learn Cardano", "new": "Learn Crypto" },
    { "id": "rm-kg-name", "type": "option_key", "option": "rank-math-options-titles", "path": ["knowledgegraph_name"], "old": "Learn Cardano", "new": "Learn Crypto" },
    { "id": "rm-website-alternate-name", "type": "option_key", "option": "rank-math-options-titles", "path": ["website_alternate_name"], "create": true, "old": null, "new": "Learn Cardano" },
    { "id": "rm-og-image", "type": "option_key", "option": "rank-math-options-titles", "path": ["open_graph_image"], "old": "{{home}}/wp-content/uploads/2023/01/old.jpg", "new": { "media": "og-default-1200x630.jpg", "as": "url" } },
    { "id": "page-7-content", "type": "post_field", "post_id": 7, "field": "post_content", "old_sha256": "…", "new_file": "page-7-content.html" },
    { "id": "page-24728-edits", "type": "post_content_edits", "post_id": 24728, "edits": [ { "find": "the Learn Cardano Podcast", "replace": "the Learn Crypto Podcast", "count": 2 } ] },
    { "id": "page-7-rm-title", "type": "post_meta", "post_id": 7, "key": "rank_math_title", "old": "…", "new": "…" },
    { "id": "cat-21-name", "type": "term_field", "taxonomy": "category", "term_id": 21, "field": "name", "old": "…", "new": "…" },
    { "id": "user-451-display-name", "type": "user_field", "user_id": 451, "field": "display_name", "old": "…", "new": "…", "sites": ["live"] }
  ],
  "leave": [
    { "id": "page-30372-title", "type": "post_field", "post_id": 30372, "field": "post_title", "why": "…" },
    { "key": "postmeta:*:_wp_attachment_metadata", "why": "pattern (fnmatch on scan keys)" }
  ]
}
```

- Types: `option`, `option_key` (`path` = list of keys, `create` allows an absent key),
  `post_field` (`post_title` | `post_excerpt` | `post_content`), `post_content_edits` (each `find`
  must occur exactly `count` times, and `replace` must not contain its own `find`), `post_meta`,
  `term_field` (`name` | `description`), `term_meta`, `user_field` (`display_name`), `user_meta`.
  Attachment alt text is `post_meta` `_wp_attachment_image_alt`. An attachment title is `post_field`
  `post_title`.
- Meta items need exactly one row. Zero rows is allowed only with `"create": true` and `"old": null`.
- Old value: `old`, `old_file` (under `content/`) or `old_sha256` (sha256 of the value with
  home_url replaced by `{{home}}`). Give one or more. Every one given must match.
- New value: `new`, or `new_file` (under `content/`; one trailing newline is stripped unless
  `"keep_trailing_newline": true`), or a media reference `{"media": "<file name>", "as": "id"|"url"}`.
  A media reference must resolve to exactly one attachment whose `_wp_attached_file` ends with that
  name.
- For `blogname`/`blogdescription`, WordPress escapes HTML on save. A `new` that would be stored
  differently (e.g. `&` → `&amp;`) is a MISS and the message shows the value to use.
- `"sites": ["staging"]` / `["live"]` limits an item. On other sites it shows as NOT_FOR_SITE.
- `acf-post-type` 28436 `post_content` is a serialized PHP array stored as a string. Replace the
  whole string, keep every `s:N:` length right, and check it in the dry-run diff.

## Rank Math key names (`targets.json`)

`knowledgegraph_name`, `knowledgegraph_logo`, `knowledgegraph_logo_id`, `website_name`,
`open_graph_image` and `open_graph_image_id` are Rank Math's standard keys in
`rank-math-options-titles`. Targets marked `"verify": true` are assumed and not yet confirmed:
`website_alternate_name` (absent until first saved, hence `create`) and the
`pt_<type>_archive_title` / `pt_<type>_archive_description` keys. Confirm them before writing the
deck. Either look at the `scan` output, which prints every hit as `option:rank-math-options-titles/<key>`,
or run:

```bash
ssh learncardano 'wp option pluck rank-math-options-titles pt_podcasts_archive_title --path=$HOME/www/learncardano.io/public_html'
ssh learncardano "wp eval 'print_r(array_filter(array_keys(get_option(\"rank-math-options-titles\")), function(\$k){return preg_match(\"/archive|website|knowledgegraph|open_graph/\", \$k);}));' --path=\$HOME/www/learncardano.io/public_html"
```

An export target whose key is absent shows `"absent": true`. An absent key without `create` turns
into a MISS at dry-run.

## Runbook

SSH alias `learncardano`. WordPress roots: live `$HOME/www/learncardano.io/public_html`, staging
`$HOME/www/staging2.learncardano.io/public_html`. Every command is written out in full, so you can
paste them one at a time.

### 0. Copy to the server and lint

```bash
ssh learncardano 'mkdir -p ~/claude-scripts/rebrand/content ~/claude-backups/rebrand'
scp tools/rebrand/rebrand-db.php tools/rebrand/targets.json tools/rebrand/make-assets.sh learncardano:claude-scripts/rebrand/
ssh learncardano 'php -l ~/claude-scripts/rebrand/rebrand-db.php'
```

### 1. Scan (read-only, both sites)

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php scan ~/claude-backups/rebrand/scan-live-1.json --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php scan ~/claude-backups/rebrand/scan-staging-1.json --path=$HOME/www/staging2.learncardano.io/public_html'
```

Each hit is `COVERED` (a deck item targets it), `COVERED-LEAVE` (in `leave`) or `UNCOVERED`.
Historical types are printed as counts only. Matches that sit only inside URLs, emails, file names
or lower-case slugs are counted but not listed. Add `with-urls` after the output file to list them.
Run the scan again once the deck exists, and add every `UNCOVERED` hit to `targets.json` or to `leave`.

### 2. Export from live (read-only)

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php export ~/claude-scripts/rebrand/targets.json ~/claude-backups/rebrand/live-export.json --path=$HOME/www/learncardano.io/public_html'
scp learncardano:claude-backups/rebrand/live-export.json tools/rebrand/live-export.json
scp -r learncardano:claude-backups/rebrand/export-content-live tools/rebrand/   # long values as .txt, for editing
```

The export writes a deck skeleton: `old` (exact, `{{home}}`-tokenised), `old_sha256`,
`"new": null`, review `hits` and, for `post_content_edits`, `content_old`. Do not commit
`live-export.json` or `export-content-live/`. They are site data.

### 3. Bring staging in line with live (staging only)

```bash
scp tools/rebrand/live-export.json learncardano:claude-backups/rebrand/live-export.json
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php sync-from ~/claude-backups/rebrand/live-export.json ~/claude-backups/rebrand/staging-sync staging --path=$HOME/www/staging2.learncardano.io/public_html'
```

Every target whose staging value differs from live's `old` is set to live's value. The writes are
journaled like `apply`, and the journal can be undone with `restore`. After this, the deck's `old`
values match on both sites.

### 4. Write the deck

Copy `live-export.json` to `copy-deck.json`. Fill `new`, or `new_file` with the file under
`content/`, or `edits`. Remove `content_old`/`hits` if you like. Then copy the deck to the server:

```bash
scp tools/rebrand/copy-deck.json learncardano:claude-scripts/rebrand/
scp -r tools/rebrand/content learncardano:claude-scripts/rebrand/
```

### 5. Image assets (only if the deck uses media references)

```bash
scp -r <local-brand-folder> learncardano:claude-backups/rebrand/brand-src
ssh learncardano 'bash ~/claude-scripts/rebrand/make-assets.sh ~/claude-backups/rebrand/brand-src ~/claude-backups/rebrand/assets'
ssh learncardano 'wp media import ~/claude-backups/rebrand/assets/og-default-1200x630.jpg ~/claude-backups/rebrand/assets/logo-square-600.png --title="Learn Crypto" --path=$HOME/www/staging2.learncardano.io/public_html'
```

If the server has no ImageMagick 7 `magick`, run `make-assets.sh` locally and copy the `assets/`
folder up. Import the assets on live only in step 8, right before the live dry-run. Media
references then resolve on each site to that site's attachment.

### 6. Staging: dry-run, apply, verify, restore test

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php dry-run ~/claude-backups/rebrand/review-staging --path=$HOME/www/staging2.learncardano.io/public_html'
```

Each item shows CHANGE / ALREADY / MISS / NOT_FOR_SITE. Long fields get
`<id>.diff`, `<id>.old.txt` and `<id>.new.txt` in the review folder (`diff -u a.old.txt a.new.txt`
for a classic view). A post_content change that alters the block-name sequence is flagged. The run
exits non-zero on any MISS or flag.

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php apply ~/claude-backups/rebrand/apply-staging staging --path=$HOME/www/staging2.learncardano.io/public_html'
ssh learncardano 'wp cache flush --path=$HOME/www/staging2.learncardano.io/public_html; wp sg purge --path=$HOME/www/staging2.learncardano.io/public_html'
node tools/rebrand/brand-crawl.mjs https://staging2.learncardano.io ../rebrand-crawl/staging-after.json
```

Check that `PROTECTED: 0 changed` is printed and that nothing says `[NOT IN DECK]` unexpectedly.
Then test the undo and re-apply:

```bash
ssh learncardano 'ls -t ~/claude-backups/rebrand/apply-staging/rebrand-journal-*.json | head -1'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php restore <journal> staging --path=$HOME/www/staging2.learncardano.io/public_html'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php dry-run --path=$HOME/www/staging2.learncardano.io/public_html'   # all CHANGE again
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php apply ~/claude-backups/rebrand/apply-staging staging --path=$HOME/www/staging2.learncardano.io/public_html'
```

### 7. Live: baseline crawl

Run this before the theme deploy and before `apply`. Keep crawl output outside the repo.

```bash
node tools/rebrand/brand-crawl.mjs https://learncardano.io ../rebrand-crawl/live-before.json
```

### 8. Live: backup, dry-run, apply

```bash
ssh learncardano 'wp db export ~/claude-backups/rebrand/live-db-before-$(date -u +%Y%m%dT%H%M%SZ).sql --path=$HOME/www/learncardano.io/public_html && chmod 600 ~/claude-backups/rebrand/live-db-before-*.sql'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php dry-run ~/claude-backups/rebrand/review-live --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php apply ~/claude-backups/rebrand/apply-live live --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp cache flush --path=$HOME/www/learncardano.io/public_html; wp sg purge --path=$HOME/www/learncardano.io/public_html'
```

Purge Cloudflare as well if HTML is cached there.

### 9. Verify

```bash
node tools/rebrand/brand-crawl.mjs https://learncardano.io ../rebrand-crawl/live-after.json --compare ../rebrand-crawl/live-before.json
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php scan ~/claude-backups/rebrand/scan-live-after.json --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php dry-run --path=$HOME/www/learncardano.io/public_html'   # all ALREADY / NOT_FOR_SITE
```

How the crawler works:

- It reads `sitemap_index.xml` and every sub-sitemap. It checks the **whole document** for every
  `page-sitemap` URL and for the extras: `/`, `/podcasts/`, `/resources/`, `/courses/`, `/kb/`,
  `/delegate/`, `/interview-booking/`, `/?s=cardano`, `/feed/`, `/feed/?post_type=podcasts`,
  `/llms.txt`, `/account/panel/`, `/category/learn-cardano-podcast/`, and
  `/podcast-category/learn-cardano/` if it exists.
- For the first 15 URLs of every other sitemap it checks only the site chrome: `<head>`, JSON-LD,
  `header.site-header`, `footer.site-footer`, `.about-home`, `.delegation`, `.lc-spreaker` and
  `#lc-popup-*`. Body matches on those pages are INFO.
- It records the title, `og:site_name`, `og:image`, `twitter:*` and the JSON-LD
  Organization/WebSite/PodcastSeries/publisher names. It fetches each og:image and reports its
  status and pixel size.
- Exit code: 1 if there is any FAIL (a non-allowlisted match in a checked region), else 2 if any
  fetch failed, else 0. `--compare` lists the fixed pages and any new FAIL snippets per URL.
- `allowlist.json` entries can be limited to some URLs (`urls`) and can search the whole document
  (`"window": 0`). The `historical-archive-listing` entry hides old names inside `<article>` /
  RSS `<item>` on archive, search and feed pages, because historical titles keep the old name.

### 10. Undo (live)

```bash
ssh learncardano 'ls -t ~/claude-backups/rebrand/apply-live/rebrand-journal-*.json | head -1'
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php restore <journal> live --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp cache flush --path=$HOME/www/learncardano.io/public_html; wp sg purge --path=$HOME/www/learncardano.io/public_html'
```

Restore skips (CONFLICT) anything edited after the run. Fix those by hand, or as a last resort
re-import the SQL dump from step 8.

### Checksums on their own

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/rebrand/rebrand-db.php checksum ~/claude-backups/rebrand/checksum-live.json --path=$HOME/www/learncardano.io/public_html'
```

This hashes every non-revision post: title, content, excerpt, slug, status, parent, author,
date_gmt, modified_gmt, menu_order, type, all post meta in meta_id order and its term
relationships. It also hashes every term (name, slug, description, parent, taxonomy) with its term
meta. These post meta keys are excluded and printed with counts: `_edit_lock`, `_edit_last`,
`_oembed_*`, `_oembed_time_*` and `rank_math_analytic*`.
