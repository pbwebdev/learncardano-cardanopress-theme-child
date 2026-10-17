# Courses migration (LearnDash → theme)

One-off WP-CLI script that moves the courses off LearnDash and onto the theme's own
course post types and ACF fields. `tools/` is never deployed with the theme; this folder
is copied to the server by hand.

| File | What it is |
|---|---|
| `migrate.php` | The script: phases `seed`, `cutover`, `rollback`. Dry run unless `apply` is given. |
| `quiz-data.json` | The 4 quizzes (25541, 25840, 26291, 28945) with the agreed fixes. Each quiz has a `changes` list. |
| `expected-tree.json` | Course → lessons → topics/quizzes (IDs only) from the analysis export, used by `seed` to cross-check the database. |
| `../../acf-json/group_lcc_*.json` | Not stored here: the theme's 4 course field groups, copied next to `migrate.php` so `seed` can run before the theme is deployed. |

Both JSON files were generated from the read-only analysis exports (`quizzes/quizzes.json`,
`structure/tree.json`, 2026-10-17). To change quiz wording later, edit it in WP admin after
the migration. Before the migration, edit `quiz-data.json` directly and re-run the `seed`
dry run, which validates it.

## What each phase does

**`seed`** is safe while LearnDash is active, and the old theme can still be live.
- When the theme's course field groups aren't loaded yet, it loads the
  `group_lcc_*.json` copies next to `migrate.php` for that run only (ACF local field
  groups; nothing is saved). `update_field()` then writes the same meta and `_field`
  reference rows the theme reads later.
- Reads each course's `ld_course_steps` (visitor order) and writes the ACF outline fields:
  `course_lessons`, `course_final_quiz`, `lesson_topics`, `lesson_quiz`, `topic_quiz`. The
  first placement wins, and extra quizzes at one level are reported. Unpublished steps stay
  in the outline; the theme skips them.
- Cross-checks the outlines against `expected-tree.json` and reports every mismatch.
- Sets `superseded_by` on course 25093 (old Minswap course) to 27739.
- Writes `quiz_pass_mark`, `quiz_randomise`, `quiz_questions` and `quiz_results` from
  `quiz-data.json`. The data is validated first: a single-choice question must have exactly
  one correct answer.
- Content fixes, each applied only if the exact text occurs once:
  - Quiz 25541: the stale GamiPress "earn the points" sentence is replaced with a short intro.
  - Quiz 26291: the logged-out-only wallet-connect paragraph, the `[cardanopress_template]`
    shortcode and the empty paragraphs around them are removed. The intro is kept.
  - Any literal `&lt;!--more--&gt;` text in course content is removed. There was none on
    2026-10-18.
- **Re-runs keep admin edits.** An outline field that is already stored and differs from
  LearnDash, or a quiz whose four fields are all stored, is left alone and reported. Add the
  bare word `overwrite` to replace them from LearnDash and `quiz-data.json` (for example
  after a seed that failed part-way through a quiz).
- **Apply refuses to run** unless the course field groups are loaded, from the theme or from
  the copies (`acf_get_field( 'field_lcc_course_lessons' )` and every other `field_lcc_*` key).

**`cutover`** runs right after LearnDash is deactivated.
1. Published lessons, topics and quizzes that sit in no live course and are **empty** (no
   visible text, no embed or iframe or YouTube link, no image or featured image, no
   shortcode or dynamic block) get a 301 to the nearest live course and are then set to
   draft:
   - wallet basics (24528) and Nami (26813) → `/courses/eternl-wallet-course-tutorials/`
   - Lace → `/courses/lace-wallet/`
   - Minswap → `/courses/minswap-decentralised-exchange-course/`
   - everything else → `/courses/`

   The redirect comes first, so a run that stops between the two leaves a published post
   that a re-run finds again, never a dead URL. Leftovers with real content, such as
   lesson 26186 (a YouTube video), keep their URL. A post whose URL already has an active
   redirect keeps that redirect and is still drafted (e.g. 26107, kept by #25). Placement
   counts from both `ld_course_steps` and the ACF outline.
2. Explicit 301s for 10 old URLs that bounce to the homepage, including the deleted Nami
   course (`/courses/how-to-use-nami-wallet-cardano/` → lesson 26816). Old slugs are
   verified against `_wp_old_slug`. Deleted course URLs are checked so they don't belong to
   a live post, and Gravity Forms entries are counted as evidence.
3. LearnDash utility pages are drafted and redirected: `/registration/` and
   `/registration-success/` → `/`, `/reset-password/` → `/wp-login.php?action=lostpassword`,
   `/profile/` → `/dashboard/`, `/courses1/` → `/courses/`. The `courses` page (29402) is
   drafted only, because the course archive serves `/courses/`. The empty LearnDash Groups
   archive `/groups/` (indexed, 200 today) gets a 301 to `/courses/` unless a published group
   or page owns it.
4. LearnDash term archives get 301s. Bases come from `learndash_settings_permalinks_taxonomies`
   and each term is verified in the DB.
5. Redirect #40 is retargeted to topic 26552 and #52 to lesson 26816, but only if they
   still point at the dead pages. Existing redirects that land on a post this run drafts
   (#22, #39, #64 on staging) are pointed straight at that post's new target, so there is
   one hop instead of a chain.
5c. Tidy-ups found on staging: 301s for 7 older draft steps outside any course whose URLs
   bounced to the homepage (CardanoPress drafts → `/topic/web-hosting/` or `/topic/overview/`,
   two Minswap drafts → the Minswap V2 course; drafts still in an outline are skipped), 301s
   for `/lessons/`, `/topic/`, `/quizzes/` and every `/<base>/archive/` (Augment Types) to
   `/courses/`, and topic 28704's "Governance Tools" link moved from drafted lesson 27429 to
   the Eternl governance lesson 28742.
6. The `widget_ldcoursenavigation-*` widget(s) move from `sidebar` to `wp_inactive_widgets`.

Report only, with no changes: duplicate exact sources (`lessons/lesson/` in 6 redirects,
`topic/topic/` in 2).

Redirects are created through Rank Math's own API (`RankMath\Redirections\Redirection::from()->save()`,
`DB::update()` and `DB::delete()`). They use comparison `exact`, the source is stored the way
Rank Math stores it (`lessons/slug/`, no leading slash, trailing slash) and the target is
built with `home_url()`, so staging points at staging. No source is ever duplicated.
Exact sources in any non-trashed redirect are checked, and so are active redirects that
Rank Math itself would match.

- **Apply refuses to run** while `sfwd-lms` is active unless `force-with-ld` is given.
  WP-CLI rejects unknown `--flags` on `eval-file`, so it is a bare word; a literal
  `--force-with-ld` is accepted only if it ever reaches the script. Apply also needs Rank
  Math's redirections module.

**`rollback <backup.json>`** undoes a previous `apply`, newest change first. It restores:
- meta rows exactly, or deletes fields that did not exist before
- post status and slug
- post content and modified date
- redirects: deletes the ones it created and restores the edited ones
- the `sidebars_widgets` option

It also undoes a run that stopped part-way. The backup is a journal: each write is marked
`running` before it starts and `done` / `failed` after, and rollback undoes `running` writes
too (restoring `before` is safe even if nothing was written). A rollback `apply` writes its
own backup, so it can be undone too. It refuses a backup taken on another site or install.

## Runbook

All commands run from your PC (Git Bash), from the theme repo root. Always pass `--path`:

- staging: `--path=$HOME/www/staging2.learncardano.io/public_html`
- live: `--path=$HOME/www/learncardano.io/public_html`

Take the usual `wp db export` before any `apply` on live: content edits leave no
revisions (SG Optimizer deletes them).

### 0. Copy the files to the server

```bash
ssh learncardano 'mkdir -p ~/claude-scripts/courses'
scp tools/courses/migrate.php tools/courses/quiz-data.json tools/courses/expected-tree.json \
    acf-json/group_lcc_course.json acf-json/group_lcc_lesson.json \
    acf-json/group_lcc_topic.json acf-json/group_lcc_quiz.json \
    learncardano:claude-scripts/courses/
ssh learncardano 'php -l ~/claude-scripts/courses/migrate.php'
```

Copy the group JSONs from the same branch you deploy, so seed writes exactly the fields the
theme reads.

### 1. Seed (old theme still live, LearnDash active)

```bash
# Dry run: prints every planned write, writes nothing.
ssh learncardano 'wp eval-file ~/claude-scripts/courses/migrate.php seed --path=$HOME/www/learncardano.io/public_html'
# Check: "ACF course fields: loaded from ...", cross-check "OK, identical", no "!" notes you don't expect.
ssh learncardano 'wp eval-file ~/claude-scripts/courses/migrate.php seed apply --path=$HOME/www/learncardano.io/public_html'
```

Visitors see no change: the old theme doesn't read these fields. The only visible edits are
the two quiz intro fixes.

### 2–6. Switch-over (one sitting, off-peak, steps back to back)

Two deploys keep every course URL working the whole time.

**2. Deploy A: only the 8 course ACF JSON files.** The four post types stay dormant while
LearnDash registers them (ACF skips post type keys that already exist); the field groups
only add boxes on the edit screens. This can be done any time after step 1.

```bash
git switch main && git pull
git checkout feat/courses-without-learndash -- acf-json/post_type_lcc_courses.json \
    acf-json/post_type_lcc_lessons.json acf-json/post_type_lcc_topic.json \
    acf-json/post_type_lcc_quiz.json acf-json/group_lcc_course.json \
    acf-json/group_lcc_lesson.json acf-json/group_lcc_topic.json acf-json/group_lcc_quiz.json
git commit -m "Courses: ACF post types and field groups (dormant while LearnDash is active)"
git push    # auto-deploys; wait for the deploy to finish
```

**3. Deactivate LearnDash** (the lead does this; not part of the script).

```bash
ssh learncardano 'wp plugin deactivate sfwd-lms learndash-certificate-builder --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp rewrite flush --path=$HOME/www/learncardano.io/public_html'   # LearnDash does not flush on deactivation
```

Until step 4 is live, the old theme keeps serving the course pages: ACF now registers the
post types with the same URLs, and `single-learndash.php` shows the content (its LearnDash
calls are guarded) without the Course Navigation widget.

**4. Deploy B: merge `feat/courses-without-learndash` into `main` and push.** The new
templates, `includes/courses.php`, CSS/JS and the deletions go live. Until cutover is
applied, empty leftover steps show the "isn't part of a current course" note.

**5. Cutover**

```bash
ssh learncardano 'wp eval-file ~/claude-scripts/courses/migrate.php cutover --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp eval-file ~/claude-scripts/courses/migrate.php cutover apply --path=$HOME/www/learncardano.io/public_html'
```

**6. Flush and purge**

```bash
ssh learncardano 'wp rewrite flush --path=$HOME/www/learncardano.io/public_html'
ssh learncardano 'wp sg purge --path=$HOME/www/learncardano.io/public_html'
# Rank Math's sitemap file cache only notices post changes made in wp-admin or cron,
# not WP-CLI, so the drafted steps stay in the sitemaps until it is cleared:
ssh learncardano "wp eval '\\RankMath\\Sitemap\\Cache::invalidate_storage();' --path=\$HOME/www/learncardano.io/public_html"
```

Purge Cloudflare as well (by hand): at least `/courses/*`, `/lessons/*`, `/topic/*` and
`/quizzes/*`, or everything.

**7. After-checks**

```bash
# LearnDash's daily payment-check job has no handler once LearnDash is off.
ssh learncardano 'wp action-scheduler action cancel learndash_payment_subscription_check_due_payments --all --path=$HOME/www/learncardano.io/public_html'
```

- Check the admin menu: on live (2026-10-18) Admin Menu Editor showed the new "Courses"
  menu (with Lessons, Topics, Quizzes) on its own, so nothing needed changing.
- Editors can now edit courses: the post types use normal post capabilities, where
  LearnDash used its own `course` capabilities (admins only).

Then spot-check, without following redirects. `/courses/`, a course, a lesson, a topic and
a quiz should return 200. `/lessons/overview/`, `/lessons/performing-a-swap/`,
`/course-tag/wallets/`, `/groups/` and `/registration/` should return 301 to the expected
target.

If deploying everything at once instead (skipping step 2): run step 3 the moment the
deploy finishes. Until LearnDash is off, it still injects its own course lists, lesson
navigation and quiz into the new pages, next to the new outline and quiz. Deactivating
first is worse: the old theme has no post type definitions, so every course URL 404s (and
Rank Math bounces it to the homepage) until the deploy lands.

### Backups and rollback

Each `apply` prints, before its first write:

```
Backup written before any change: /home/<user>/claude-backups/courses-migration-<phase>-<YYYYMMDDTHHMMSSZ>.json
Undo with: wp eval-file .../migrate.php rollback <that file> apply --path=...
```

The file holds every value before it changed (`before`) and the state of each write
(`planned` / `running` / `done` / `skipped` / `failed`), plus created redirect IDs. If an
`apply` stops part-way, either roll it back or fix the cause and run the same phase again:
writes that already happened are reported as already set or kept. To undo, paste the full
path that apply printed, or keep the `~` inside single quotes so it expands on the server,
not on your PC:

```bash
F='~/claude-backups/courses-migration-cutover-20261018T120000Z.json'   # example name; keep the single quotes
ssh learncardano "wp eval-file ~/claude-scripts/courses/migrate.php rollback $F --path=\$HOME/www/learncardano.io/public_html"        # dry run
ssh learncardano "wp eval-file ~/claude-scripts/courses/migrate.php rollback $F apply --path=\$HOME/www/learncardano.io/public_html"
```

Undo `cutover` before `seed` when undoing both. Rollback does not reactivate LearnDash,
flush rewrite rules or purge caches; do those by hand.

## Notes on how it writes

- Outline and quiz fields go through `update_field()` with the field keys, so ACF writes its
  `_field` reference rows. Every write is read back and checked.
- Post content is updated directly in `wp_posts` and the post cache is cleared. Hooks are
  skipped on purpose, because LearnDash is still active during `seed`.
- Status changes use `wp_update_post()`, so cache, sitemap and search hooks run. kses
  filters are removed for the run, because WP-CLI has no user and would otherwise filter
  stored HTML.
- The theme's `lc_course_map` transient is deleted at the end of every apply.
- Staging shares the production Algolia index. Keep the Algolia plugin inactive on staging
  before applying anything there.

## Dry run on staging, 2026-10-18 (LearnDash active, theme not deployed, group JSONs copied)

- `seed`:
  - ACF course fields loaded from the 4 `group_lcc_*.json` copies.
  - Cross-check with `expected-tree.json` identical: 6 courses, 44 lessons, 82 topics,
    3 quizzes.
  - 37 field writes: 6 courses, 25 lessons with topics and/or a quiz, 1 topic with a quiz
    (25520 → 25541), 4 quizzes, plus `superseded_by` on 25093.
  - Lessons with no topics and topics with no quiz have nothing to store.
  - 2 content fixes (25541, 26291). No literal more-tag text was found. 0 notes.
- `cutover`:
  - 129 placed steps and 52 published leftovers: 24 keep their URL, 28 are empty and get
    301 + draft.
  - 50 redirects to create (27 leftovers, 10 old URLs, 5 utility pages, `/groups/`, 7 term
    URLs). 1 source already redirected (26107 by #25).
  - 34 status changes, 5 retargets (#40, #52, and #22, #39, #64 straight to `/courses/`),
    1 widget move.
  - Report-only notes: duplicates `lessons/lesson/` (#84 wins) and `topic/topic/` (#56
    wins), plus the "LearnDash is still active" warning.
