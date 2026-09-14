# Stage 3 SEO, Performance, and Observability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import approved SEO copy through Yoast, improve card image stability and asset loading, expose safe import health, and make existing regressions run in CI.

**Architecture:** A focused `promokodiki-seo-import` plugin owns the versioned CSV, WP-CLI dry-run/apply/rollback flow, Yoast metadata/templates, backup enforcement, and indexability rules. Existing theme and source plugins retain presentation and synchronization responsibilities. CI provisions disposable WordPress data before any integration or browser checks.

**Tech Stack:** WordPress 6+, PHP 8.1/8.4, Yoast SEO 26.6, WP-CLI, JavaScript/Node test runner, Python unittest, GitHub Actions, Playwright.

**Spec:** `docs/superpowers/plans/2026-09-12-site-audit-plan.md`, stage 3, refined by the 2026-09-14 grill-me decisions.

## Global Constraints

- Use sheet `Лист1`; persist `Название`, `Title`, `H1`, and `Description` as UTF-8 CSV.
- Match exact normalized names; never create objects, change slugs, or import stale seasonal rows.
- Use Yoast fields and options without a competing SEO output layer.
- Require a real full DB backup before apply; retain five JSON field backups and support rollback.
- Skip analytics events entirely.
- Keep production secrets, source content, and personal data out of diagnostics.

---

### Task 1: Yoast SEO import and indexability

**Files:**
- Create: `wp-content/plugins/promokodiki-seo-import/promokodiki-seo-import.php`
- Create: `wp-content/plugins/promokodiki-seo-import/includes/class-seo-dataset.php`
- Create: `wp-content/plugins/promokodiki-seo-import/includes/class-seo-command.php`
- Create: `wp-content/plugins/promokodiki-seo-import/includes/class-indexability.php`
- Create: `wp-content/plugins/promokodiki-seo-import/data/seo-pages.csv`
- Create: `wp-content/plugins/promokodiki-seo-import/tests/test-seo-import.php`

**Interfaces:**
- Produces `wp promokodiki seo dry-run`, `apply --backup-file=<path>`, and `rollback --file=<path>`.
- Writes `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `wpseo_title`, and `wpseo_desc`; changes names only, never slugs.

- [ ] Write contract tests for normalization, exact matching, stale seasonal rejection, dry-run immutability, backup enforcement, apply, rollback, Yoast templates, and robots/canonical rules.
- [ ] Run the new harness and confirm failures because the plugin classes do not exist.
- [ ] Implement CSV parsing, minimal typo corrections in the CSV, reports, five-backup retention, Yoast reindex/cache commands, and indexability filters.
- [ ] Run the focused PHP tests and existing SEO/theme tests.
- [ ] Commit the independently working importer.

### Task 2: Responsive card images and conditional assets

**Files:**
- Modify: `wp-content/themes/promokodiki/template-parts/promocode-card.php`
- Modify: `wp-content/themes/promokodiki/inc/top.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Create/modify: `wp-content/themes/promokodiki/tests/promocode-images.php`, `tests/theme-assets.php`

**Interfaces:**
- Produces attachment markup through `wp_get_attachment_image()` with dimensions/srcset and page predicates for modal/top scripts.

- [ ] Write failing markup and enqueue predicate tests.
- [ ] Confirm the tests fail against raw `<img>` and global scripts.
- [ ] Implement attachment rendering, sized external fallbacks, eager first/LCP image, and conditional script loading.
- [ ] Run theme PHP/JS tests and commit.

### Task 3: Import diagnostics

**Files:**
- Modify: `wp-content/plugins/promokodiki-telegram/includes/class-log.php`
- Modify: `wp-content/plugins/promokodiki-telegram/admin/class-admin.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-diagnostics.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/diagnostics.php`
- Modify: focused diagnostics tests in both plugins.

**Interfaces:**
- Produces a redacted 30-run history and health summary with two-interval stale thresholds.

- [ ] Write failing tests for 30-entry retention, last success/error, never-run, stale Telegram at six hours, and Admitad at twice its configured interval.
- [ ] Implement sanitized aggregates and the existing diagnostics-page presentation.
- [ ] Run focused suites and commit.

### Task 4: Executable CI and browser smoke

**Files:**
- Modify: `.github/workflows/ci.yml`
- Create: `.github/scripts/setup-test-wordpress.sh`
- Create: `tests/e2e/promokodiki.spec.js`
- Create: `playwright.config.js`
- Create: `package.json`

**Interfaces:**
- Produces PHP 8.1/8.4 lint, Node tests, Telegram unittest, disposable WordPress PHP harnesses, and Chromium smoke at 390×844 and 1440×900.

- [ ] Add fixture/CI contract tests or shell validation before changing the workflow.
- [ ] Implement deterministic WordPress setup with the Admitad test sentinel and seeded public data.
- [ ] Add the two agreed browser scenarios and viewport projects.
- [ ] Validate workflow syntax and run every locally available command.
- [ ] Commit CI support.

### Task 5: Production measurements, docs, and release

**Files:**
- Create: `docs/audits/2026-09-14-stage-3-production-baseline.md`
- Modify: `README.md`

- [ ] Record robots, sitemap, canonical, and PageSpeed evidence for the five agreed production templates on mobile and desktop; distinguish field and lab data.
- [ ] Document dry-run/apply/rollback and post-release verification.
- [ ] Run all available checks, inspect the diff, and verify no secrets or generated backups are tracked.
- [ ] Merge to `main`, push, and create a production archive containing working files only.
