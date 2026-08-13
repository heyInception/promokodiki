# Shops Admitad Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/shops/` and `shops_category` fast, searchable, and reliably enriched from locally synchronized Admitad campaign data while preserving optional ACF overrides and coupon-card artwork.

**Architecture:** Extend the existing resumable Admitad reference sync with campaign profile fields and a bounded, owned logo pipeline. Expose one theme-side shop profile helper that resolves ACF → Admitad term meta → WordPress fallbacks, and make both shop templates consume it without live API calls or post-global leakage.

**Tech Stack:** WordPress 6.4+, PHP 8.1+, classic `promokodiki` theme, `admitad-coupons` plugin, WP-Cron, Media API, term meta, optional ACF, WP-CLI integration harness, Node `node:test`, WPCS/PHPCS.

## Global Constraints

- Public requests never call Admitad or download media.
- Filled ACF fields override API values independently; the feature works without ACF and does not register duplicate ACF fields.
- Empty or malformed API fields never erase the last valid local snapshot.
- Automatic updates require the exact stable `admitad_campaign_id`; names are never an automatic join key.
- `.promocodes__imgs` in `template-parts/promocode-card.php` remains unchanged.
- Missing or invalid ratings are hidden; no values are synthesized.
- The public design remains unchanged except for conditional empty states and accessibility fixes.
- Managed logo cleanup may delete only unreferenced attachments explicitly owned by this plugin, and only after an administrator preview and nonce-protected request.
- Preserve the user's existing uncommitted changes in `wp-content/themes/promokodiki/functions.php`, `wp-content/themes/promokodiki/style.css`, and `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`.

---

## File Structure

### Plugin

- `includes/class-campaign-normalizer.php`: normalize description, rating, image URL, and website into deterministic campaign snapshots.
- `includes/class-schema.php`: add durable campaign enrichment fields to `admitad_company_profile` and bump schema version.
- `includes/class-activator.php`, `includes/class-plugin.php`: run idempotent schema upgrades and register enrichment/logo hooks.
- `includes/class-reference-repository.php`: preserve non-empty campaign snapshots and expose linked/unlinked campaign profile reads.
- `includes/class-shop-profile-sync.php` (new): copy source-owned profile fields only to terms with an exact campaign ID.
- `includes/class-managed-logo-service.php` (new): bounded validation, download/import, hash reuse, ownership tracking, safe replacement, preview, and cleanup.
- `includes/class-sync-coordinator.php`: invoke term enrichment per campaign page and schedule bounded logo batches.
- `admin/class-admin-actions.php`, `admin/pages/class-sync-page.php`, `admin/views/sync.php`: preview/start backfill and preview/execute cleanup behind capability and nonce checks.
- `admitad-coupons.php`: load the new classes.

### Theme

- `inc/shops.php` (new): source precedence, sanitization, summary extraction, active-shop cache, search matching, robots decision, and asset hooks.
- `functions.php`: require `inc/shops.php` only; preserve existing reaction-cache changes.
- `archive-shops.php`: single catalog query/grouping pass and server search fallback.
- `taxonomy-shops_category.php`: queried-term-only shop rendering, stable ratings/logos/descriptions, optimized related shops, empty state.
- `js/shops.js` (new): progressive client-side filtering and group visibility.
- `assets/css/overrides.css` or the existing shop component stylesheet: only states required by accessible empty/search output; do not use unrelated `style.css` cleanup as part of this task.

### Tests

- New focused plugin PHP tests for campaign enrichment, shop linking, logo lifecycle, admin security, and sync integration.
- New `tests/js/test-shops-search.cjs` for the theme script.
- Extend the existing theme integration harness without overwriting its current uncommitted reaction-script assertions.

---

### Task 1: Normalize and Persist Campaign Enrichment

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-campaign-normalizer.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-schema.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-activator.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-reference-repository.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-campaign-enrichment.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-schema.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Campaign_Normalizer::normalize(array $raw): array` with `description`, `raw_description`, `rating`, `image_url`, and `site_url`.
- Produces: `Promokodiki_Admitad_Reference_Repository::sync_campaigns(array $items): int` preserving prior non-empty fields.
- Produces: `Promokodiki_Admitad_Reference_Repository::campaign(int $campaign_id): ?array` for later term and logo services.

- [ ] **Step 1: Write failing normalizer tests**

Create fixtures that assert HTML-bearing `raw_description` is retained as source input, scalar description/site/image fields are normalized, rating `4.7` remains `4.7`, invalid/NaN/out-of-range ratings become `null`, and the payload hash changes when any enrichment field changes.

- [ ] **Step 2: Run the focused test and verify RED**

Run: `studio --version`, then `studio wp --path C:\Users\Inception\Studio\promokodiki eval-file wp-content/plugins/admitad-coupons/tests/php/test-campaign-enrichment.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter`

Expected: FAIL because normalized enrichment keys and repository columns do not exist.

- [ ] **Step 3: Extend normalization minimally**

Add exact fields to the canonical snapshot. Treat a numeric rating as valid only when finite and `0 < rating <= 5`; return `null` otherwise. Use `esc_url_raw()` for `image_url` and `site_url`, `sanitize_textarea_field()` for plain description, and retain raw description as a bounded string for later allowlist cleaning.

- [ ] **Step 4: Add failing schema/repository preservation tests**

Assert a schema upgrade adds `description`, `raw_description`, `rating`, `image_url`, and `site_url` columns; a later empty API item retains existing non-empty values; a later valid non-empty item replaces only the corresponding source-owned fields; category and manual classification fields remain intact.

- [ ] **Step 5: Run repository tests and verify RED**

Run: `studio wp --path C:\Users\Inception\Studio\promokodiki eval-file wp-content/plugins/admitad-coupons/tests/php/test-campaign-enrichment.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter`

Expected: FAIL on missing columns or overwritten empty values.

- [ ] **Step 6: Implement schema v5 and idempotent runtime upgrade**

Bump `Promokodiki_Admitad_Schema::VERSION` to `5`, extend `company_profile`, and on `init` compare `promokodiki_admitad_db_version` with the constant before calling `install()`. Persist enrichment via SQL expressions equivalent to `CASE WHEN VALUES(field) <> '' THEN VALUES(field) ELSE field END`; preserve rating unless the new normalized rating is non-null.

- [ ] **Step 7: Run focused and schema tests and verify GREEN**

Run the focused test, then `studio wp --path C:\Users\Inception\Studio\promokodiki eval-file wp-content/plugins/admitad-coupons/tests/php/test-schema.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter`.

Expected: both exit successfully with zero harness failures.

- [ ] **Step 8: Commit**

Stage only Task 1 files and commit: `feat: persist Admitad shop profiles`.

---

### Task 2: Sanitize Descriptions and Enrich Exact-ID Shop Terms

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-shop-profile-sync.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-sync-coordinator.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-profile-sync.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-sync-coordinator.php`

**Interfaces:**
- Consumes: `Promokodiki_Admitad_Reference_Repository::campaign(int): ?array`.
- Produces: `Promokodiki_Admitad_Shop_Profile_Sync::sanitize_description(string): string`.
- Produces: `Promokodiki_Admitad_Shop_Profile_Sync::summary(string, int $limit = 700): string`.
- Produces: `Promokodiki_Admitad_Shop_Profile_Sync::sync_campaign(array $campaign): array{updated:int,unlinked:int,term_id:int}`.

- [ ] **Step 1: Write failing pure-description tests**

Assert the cleaner retains paragraphs, headings, lists, emphasis, and safe links; removes scripts, iframes, forms, event attributes, styles, and unsafe schemes. Assert the summary takes at most two meaningful paragraphs, excludes headings/paragraphs beginning with partner-facing markers such as `Минус-слова`, `Вебмастерам`, `Условия программы`, and `Рекомендации по продвижению`, stays within 700 Unicode characters, and does not cut a word.

- [ ] **Step 2: Run and verify RED**

Run the new `test-shop-profile-sync.php` through `studio wp eval-file`.

Expected: FAIL because the class is missing.

- [ ] **Step 3: Implement the cleaner and summary**

Define a narrow `wp_kses()` allowlist (`p`, `br`, `h2`–`h4`, `ul`, `ol`, `li`, `strong`, `em`, and `a[href|title|target|rel]`). Normalize external links to safe output at render time. Build summaries from cleaned block text using `wp_strip_all_tags()`, Unicode-aware length functions, and word-boundary truncation.

- [ ] **Step 4: Write failing exact-link tests**

Create two `shops_category` terms with similar names but different/no IDs. Assert only the term whose `admitad_campaign_id` exactly equals the campaign ID receives `_admitad_shop_description`, `_admitad_shop_summary`, `_admitad_shop_rating`, `_admitad_shop_image_url`, and `_admitad_shop_website`. Assert empty source fields leave previous meta unchanged and taxonomy `description` plus ACF-named meta are never overwritten.

- [ ] **Step 5: Run and verify RED**

Expected: FAIL because exact-ID enrichment is not connected.

- [ ] **Step 6: Implement exact-ID term synchronization**

Use one bounded term-meta lookup for the stable campaign ID. Store only plugin-owned `_admitad_shop_*` keys. Return `unlinked=1` when no unique exact match exists and never fall back to a name lookup.

- [ ] **Step 7: Integrate the service into each campaign reference page**

After `sync_campaigns()`, loop only the normalized items from that API page and invoke `sync_campaign()`. Extend the coordinator result/counters without changing retry or continuation semantics.

- [ ] **Step 8: Run focused and coordinator tests and verify GREEN**

Run `test-shop-profile-sync.php` and `test-sync-coordinator.php`.

Expected: both pass; existing coordinator pagination assertions remain unchanged.

- [ ] **Step 9: Commit**

Commit Task 2 files as `feat: enrich shops by Admitad campaign ID`.

---

### Task 3: Build the Managed Logo Lifecycle

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-managed-logo-service.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-sync-coordinator.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-managed-logo-service.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-sync-coordinator.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Managed_Logo_Service::__construct(?callable $downloader = null, ?callable $importer = null)` for deterministic tests.
- Produces: `preview(): array{linked:int,download:int,reuse:int,unlinked:int,unsupported:int}`.
- Produces: `process_campaign(int $campaign_id): array{state:string,attachment_id:int}`.
- Produces: `cleanup_preview(): array{attachment_ids:int[],bytes:int}` and `cleanup(array $attachment_ids): array{deleted:int,skipped:int}`.
- Produces hook: `promokodiki_admitad_logo_batch(int $run_id, int $offset)`.

- [ ] **Step 1: Write failing validation and ownership tests**

Assert downloads reject responses beyond 2 MiB, non-image MIME types, MIME/extension mismatches, and undecodable SVG. Assert PNG/JPEG/WebP are accepted; SVG only proceeds through an injected successful rasterizer. Assert attachments receive `_admitad_managed_logo=yes`, `_admitad_logo_hash`, `_admitad_logo_source_url`, and `_admitad_campaign_ids`.

- [ ] **Step 2: Run and verify RED**

Run `test-managed-logo-service.php`.

Expected: FAIL because the service is missing.

- [ ] **Step 3: Implement bounded download/import**

Use `download_url($url, 30)` plus actual file size and `wp_check_filetype_and_ext()` checks before `media_handle_sideload()`. Never add SVG to global upload MIME types. Inject downloader/importer/rasterizer callables for tests. Always remove temporary files after success or failure.

- [ ] **Step 4: Write failing reuse and replacement tests**

Assert an unchanged source URL performs no download, matching content hash reuses an owned attachment, a valid changed logo switches `_admitad_shop_logo_id` only after import succeeds, failed replacement retains the old ID/URL, and one attachment can list multiple campaign IDs.

- [ ] **Step 5: Run and verify RED**

Expected: FAIL on missing idempotence/deduplication behavior.

- [ ] **Step 6: Implement hash reuse and atomic switching**

Hash verified file bytes with SHA-256, search only owned attachments by exact hash, update campaign references, then detach the old campaign reference. Do not delete during replacement; cleanup owns deletion decisions.

- [ ] **Step 7: Write failing cleanup tests**

Create owned referenced, owned orphaned, shared, and unrelated attachments. Assert preview returns only owned orphans. Assert cleanup rechecks ownership and references at execution time and skips IDs not in the submitted preview set or that became referenced.

- [ ] **Step 8: Implement preview/cleanup and bounded batch hooks**

Schedule logo work after a successful campaign phase in chunks no larger than the configured `batch_size`. Add the hook to `cron_hooks()` and release/completion logic. Store first-backfill completion only after all logo batches finish.

- [ ] **Step 9: Run focused and coordinator tests and verify GREEN**

Run both logo and coordinator tests.

Expected: zero failures, no temp files left by fixtures.

- [ ] **Step 10: Commit**

Commit as `feat: manage Admitad shop logos safely`.

---

### Task 4: Add Administrator Preview, Backfill, and Cleanup Controls

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-sync-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/sync.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-fragments.php` if a dedicated fragment is required
- Modify: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-enrichment-admin.php`
- Test: `wp-content/plugins/admitad-coupons/tests/js/test-admin-shell.cjs`

**Interfaces:**
- Produces operations `shop_enrichment_preview`, `shop_enrichment_start`, `logo_cleanup_preview`, and `logo_cleanup_execute`.
- All mutations consume nonce action `promokodiki_admitad_shop_enrichment` and capability `manage_admitad_automation`.

- [ ] **Step 1: Write failing capability/nonce tests**

Assert preview and mutation operations reject anonymous/editor users; reject absent/invalid nonces; allow an administrator with `manage_admitad_automation`; sanitize submitted attachment IDs; and refuse cleanup without a server-recomputed preview match.

- [ ] **Step 2: Run and verify RED**

Run `test-shop-enrichment-admin.php`.

Expected: FAIL because operations are not registered.

- [ ] **Step 3: Implement admin action methods**

Return structured `WP_Error` codes (`forbidden`, `invalid_nonce`, `stale_cleanup_preview`, `sync_locked`) and reuse the existing AJAX response envelope. Starting enrichment schedules the existing reference sync plus logo phase; it does not perform unbounded work in the request.

- [ ] **Step 4: Write failing view and JavaScript contract tests**

Assert the sync page renders preview totals before start, explicitly labels unlinked campaigns and estimated downloads, includes a dry-run cleanup list, requires explicit confirmation to execute deletion, disables controls during requests, and presents partial logo failures without discarding prior totals.

- [ ] **Step 5: Run and verify RED**

Run the PHP admin test and `node --test wp-content/plugins/admitad-coupons/tests/js/test-admin-shell.cjs`.

Expected: FAIL on missing controls/contracts.

- [ ] **Step 6: Implement accessible admin controls**

Use existing `data-admitad-ajax` transport, notice region, loading behavior, and Russian copy. Keep destructive cleanup a separate submit action with the preview token and explicit attachment IDs.

- [ ] **Step 7: Run admin PHP/JS tests and verify GREEN**

Expected: both commands pass with no warnings introduced by the new paths.

- [ ] **Step 8: Commit**

Commit as `feat: add shop enrichment controls`.

---

### Task 5: Create the Theme Shop Profile and Eligibility Layer

**Files:**
- Create: `wp-content/themes/promokodiki/inc/shops.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Extend: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Produces: `promokodiki_shop_profile(WP_Term $term): array{name:string,full_description:string,about:string,rating:?float,website:string,logo_id:int,logo_url:string,logo_alt:string}`.
- Produces: `promokodiki_shop_active_term_ids(bool $force = false): int[]`.
- Produces: `promokodiki_shop_matches_search(WP_Term $term, string $search): bool`.
- Produces: `promokodiki_shop_should_noindex(WP_Term $term, bool $has_offers): bool`.

- [ ] **Step 1: Write failing precedence tests**

In the existing theme harness, use temporary `shops_category` terms and filter stubs for `get_field()` behavior. Assert each field independently resolves non-empty ACF first, then `_admitad_shop_*`, then taxonomy description/`shop_website`/`shops-category-image-id`. Assert `function_exists('get_field') === false` remains supported by guarding every ACF call.

- [ ] **Step 2: Run and verify RED**

Run the theme integration file through its existing safe runner or direct disposable-database command.

Expected: FAIL because the helper does not exist.

- [ ] **Step 3: Implement the profile resolver**

Normalize ACF image return shapes (ID, array, or URL), validate numeric ratings to `0 < rating <= 5`, use the plugin-cleaned `_admitad_shop_description/_summary`, and never query a promocode post for shop-level imagery.

- [ ] **Step 4: Write failing active-shop and cache tests**

Create terms with active, inactive, expired, future, draft, and undated published offers. Assert only terms with at least one public eligible offer are returned. Assert cached IDs are reused and invalidated on `save_post_promocode`, `set_object_terms`, `deleted_post`, and updates to `_promocode_is_active` or `_promocode_expiry_date`.

- [ ] **Step 5: Run and verify RED**

Expected: FAIL on missing eligibility/cache hooks.

- [ ] **Step 6: Implement one bounded eligibility query and invalidation**

Query eligible published promocode IDs/term relationships once using the same inactive and expiry semantics as `Promokodiki_Admitad_Visibility`; collect unique `shops_category` term IDs and cache them in an option/transient with a versioned key. Do not issue one `WP_Query` per term.

- [ ] **Step 7: Write and implement search/robots helper tests**

Assert Unicode case-insensitive substring matching, trimmed empty search behavior, and `noindex` only when both active offers and meaningful full description are absent.

- [ ] **Step 8: Run theme integration tests and verify GREEN**

Expected: all existing assertions, including the user's uncommitted reaction-script assertions, remain green.

- [ ] **Step 9: Commit**

Stage only the new helper, the minimal `require` addition in `functions.php`, and additive test hunks. Commit as `feat: add shop profile resolver`.

---

### Task 6: Refactor `/shops/` and Add Progressive Search

**Files:**
- Modify: `wp-content/themes/promokodiki/archive-shops.php`
- Create: `wp-content/themes/promokodiki/js/shops.js`
- Modify: `wp-content/themes/promokodiki/inc/shops.php`
- Create: `wp-content/plugins/admitad-coupons/tests/js/test-shops-search.cjs`
- Extend: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: `promokodiki_shop_active_term_ids()` and `promokodiki_shop_matches_search()`.
- JavaScript consumes `[data-shops-search]`, `[data-shop-name]`, `[data-shop-group]`, and `[data-shops-empty]` markup only.

- [ ] **Step 1: Write failing server-template tests**

Assert the template contains no inline `<script>`, calls `get_terms()` only once in its catalog path, restricts terms to active IDs, preserves alphabetical Unicode grouping, applies sanitized `?s=` matching, escapes links/names/data attributes, renders an associated search label, and shows a useful empty result.

- [ ] **Step 2: Run and verify RED**

Expected: FAIL on inline JavaScript, duplicate term queries, and missing server fallback.

- [ ] **Step 3: Refactor the PHP template minimally**

Build the term list, filtered list, letter set, and grouped list from one array. Use `get_term_link()` error checks and context-specific escaping. Preserve existing classes to avoid redesign.

- [ ] **Step 4: Write failing JavaScript behavior tests**

Using `node:test` and a minimal DOM harness, assert two-character live filtering, Unicode case folding, hiding empty groups, restoring all groups for shorter input, updating the empty state, and scrolling/focusing the first server/client match on submit without navigating.

- [ ] **Step 5: Run and verify RED**

Run: `node --test wp-content/plugins/admitad-coupons/tests/js/test-shops-search.cjs`.

Expected: FAIL because `js/shops.js` does not exist.

- [ ] **Step 6: Implement and conditionally enqueue `shops.js`**

Enqueue only for `is_page_template('page-shops.php')` or the shops archive. Use plain JavaScript, no new dependency, and progressive enhancement; the GET form remains valid without the script.

- [ ] **Step 7: Run PHP and JS tests and verify GREEN**

Expected: both focused suites pass.

- [ ] **Step 8: Commit**

Commit as `refactor: optimize shops catalog search`.

---

### Task 7: Refactor the Shop Taxonomy Template

**Files:**
- Modify: `wp-content/themes/promokodiki/taxonomy-shops_category.php`
- Modify: `wp-content/themes/promokodiki/inc/shops.php`
- Modify: a focused existing shop stylesheet or `wp-content/themes/promokodiki/assets/css/overrides.css`
- Extend: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: `promokodiki_shop_profile()`, `promokodiki_shop_active_term_ids()`, and `promokodiki_shop_should_noindex()`.
- Preserves: `promokodiki_filter_render(array('context' => 'shop', 'object_id' => $term_id))`.

- [ ] **Step 1: Write failing source-contract tests**

Assert the taxonomy template never calls `rand()`, never obtains shop-level `image_url` via `get_the_ID()`, does not call `category_description()` without the current term, contains one queried-term assignment, has no triply nested `.promocodes__store-items`, and leaves `template-parts/promocode-card.php` byte-for-byte untouched.

- [ ] **Step 2: Write failing rendered-output tests**

Render fixtures for: ACF overrides, Admitad-only data, missing rating, missing about text, safe full description, local logo, external logo fallback, no active offers, and no offers/no description. Assert star block visibility and accessible rating text, empty-block suppression, correct logos in related-shop items, empty offer message, and `noindex, follow` only for the final case.

- [ ] **Step 3: Run and verify RED**

Expected: FAIL on random ratings, post-global images, unsafe/duplicate markup, and robots behavior.

- [ ] **Step 4: Replace shop-level reads with the profile helper**

Resolve `$current_category`, `$term_id`, and `$profile` once. Use `wp_get_attachment_image()` for local images, escaped `<img>` only for validated external fallback, `wp_kses()` output for the full description, and safe text/limited HTML for the summary.

- [ ] **Step 5: Optimize related shops and recent offers**

Select related terms from the cached active IDs and render their own profile logos; remove the per-term `get_posts()` loop. Keep one bounded recent-promocode query and ensure `wp_reset_postdata()` runs in all paths.

- [ ] **Step 6: Implement empty state and robots hook**

Render `Сейчас активных предложений нет.` when the filter/fallback query has no public offers. Add `wp_robots` through `inc/shops.php` so only a descriptionless, offerless `shops_category` page gets `noindex` and `follow`.

- [ ] **Step 7: Add minimal style/accessibility adjustments**

Style only the new empty/status elements and hide the “Подробнее” control when no expandable text exists. Do not modify the user's unrelated whitespace-only `style.css` change.

- [ ] **Step 8: Run theme tests and verify GREEN**

Expected: all integration assertions pass and `template-parts/promocode-card.php` has no diff.

- [ ] **Step 9: Commit**

Commit as `refactor: render stable shop profiles`.

---

### Task 8: Full Verification, Operations Documentation, and Review

**Files:**
- Modify: `docs/admitad-automation-operations.md`
- Verify only: all touched plugin/theme/test files

**Interfaces:**
- Documents preview, first backfill, cron maintenance, external-logo fallback, cleanup dry run, and recovery from partial failures.

- [ ] **Step 1: Write the operations documentation update**

Document exact administrator steps: run enrichment preview, inspect linked/unlinked/download/reuse totals, start the first backfill, verify logo batch completion, preview cleanup, and execute only the displayed orphan set. State that empty API values and failures preserve prior values.

- [ ] **Step 2: Verify Studio availability and status**

Run: `studio --version` and `studio status --path C:\Users\Inception\Studio\promokodiki`.

Expected: CLI available and site status identified. Start with `studio start --skip-browser --path ...` only if stopped.

- [ ] **Step 3: Run all plugin PHP integration tests**

Run: `powershell -ExecutionPolicy Bypass -File wp-content/plugins/admitad-coupons/tests/run-all.ps1 -SitePath C:\Users\Inception\Studio\promokodiki`.

Expected: `All Admitad integration tests passed` with no failed harness cases. If the disposable test database sentinel is absent, do not bypass it; report the blocked integration layer and continue with non-mutating syntax/static checks.

- [ ] **Step 4: Run JavaScript tests**

Run: `node --test wp-content/plugins/admitad-coupons/tests/js/*.cjs`.

Expected: all tests pass.

- [ ] **Step 5: Run the AJAX filter/theme integration suite**

Run the PHP files through the same disposable database already verified by the Admitad runner:

```powershell
Get-ChildItem 'wp-content/plugins/promokodiki-ajax-filter/tests/php/test-*.php' |
	Sort-Object Name |
	ForEach-Object {
		studio wp --path C:\Users\Inception\Studio\promokodiki eval-file $_.FullName --skip-plugins=admitad-coupons,promokodiki-ajax-filter
		if ($LASTEXITCODE -ne 0) { throw "Filter PHP test failed: $($_.Name)" }
	}
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/*.test.js
```

Expected: every PHP invocation exits `0` and all Node tests pass, including unchanged existing reaction assertions and new shops cases.

- [ ] **Step 6: Run syntax and coding-standard checks**

Run PHP `-l` over every touched PHP file using Studio's PHP/WP runtime, then run `vendor/bin/phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist` for touched plugin files and the theme's available lint commands for `js/shops.js`. Expected: no syntax errors; resolve new WPCS violations without sweeping legacy files.

- [ ] **Step 7: Run live smoke checks without changing production-like content**

Use `studio wp eval` to confirm no public-template helper performs HTTP requests, inspect `/shops/?s=<known fixture>` and one `shops_category` URL, and verify page source for escaped description, stable rating, correct logo source, empty state/robots rules, and absence of inline shops JavaScript. Do not run cleanup execution against non-test media.

- [ ] **Step 8: Review the final diff against acceptance criteria**

Confirm every requirement in `docs/superpowers/specs/2026-08-13-shops-admitad-enrichment-design.md` maps to code/tests. Confirm `git diff -- wp-content/themes/promokodiki/template-parts/promocode-card.php` is empty and unrelated user changes remain present.

- [ ] **Step 9: Request code review**

Use `superpowers:requesting-code-review` on the complete diff. Address only verified findings through new failing tests before fixes.

- [ ] **Step 10: Run fresh final verification and commit documentation/fixes**

Repeat the full relevant test commands after review fixes. Commit remaining scoped files as `docs: document shop enrichment operations` (or a precise fix commit if review required code changes).

---

## Completion Checklist

- [ ] Public shop pages make zero Admitad requests.
- [ ] ACF → Admitad → WordPress precedence is independently tested per field.
- [ ] Empty API values preserve previous snapshots.
- [ ] Exact campaign ID is the only automatic link key.
- [ ] Random ratings and unrelated post-global images are gone.
- [ ] Coupon-card `.promocodes__imgs` has no diff.
- [ ] Logos are bounded, deduplicated, owned, reusable, and safely preview-cleanable.
- [ ] `/shops/` has one catalog term fetch, active-only results, and server/client search.
- [ ] Empty shop pages follow the agreed message and robots rules.
- [ ] All available PHP, JS, syntax, and focused WPCS checks pass with fresh output.
