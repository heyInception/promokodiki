# Telegram Promocodes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy local top snapshot with a reusable MTProto Telegram importer and responsive Telegram promocode slider.

**Architecture:** A dedicated WordPress plugin exposes HMAC-authenticated worker endpoints and persists accepted Telegram offers as ordinary `promocode` posts. A cron-invoked Telethon worker parses public channel messages and submits batches; the theme only queries the Telegram category and renders Swiper cards.

**Tech Stack:** WordPress 7.0+, PHP 8.1+, Python 3.10+, Telethon 1.x, vanilla JavaScript, Swiper 11, WP-CLI integration tests, Python `unittest`, Node `node:test`.

**Spec:** `docs/superpowers/specs/2026-08-24-telegram-promocodes-design.md`

## Global Constraints

- Work directly in the clean `main` branch as explicitly approved.
- Never commit Telegram or WordPress secrets, `.env` files, or `.session` files.
- Keep MTProto network access out of frontend WordPress requests.
- Validate and sanitize worker payloads again in WordPress.
- Preserve existing promocode modal and website reaction behavior.
- Do not expose Telegram source attribution on the frontend.
- Use tests before production code for every behavioral unit.

---

### Task 1: Plugin Bootstrap, Category, and Configuration

**Files:**
- Create: `wp-content/plugins/promokodiki-telegram/promokodiki-telegram.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-plugin.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-config.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-activator.php`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-activation.php`
- Test support: `wp-content/plugins/promokodiki-telegram/tests/harness.php`

**Interfaces:**
- Produces `Promokodiki_Telegram_Config::get(string $key)`, `channels()`, `save_channels(array $channels)`, `category_term_id()`, and plugin activation/deactivation hooks.
- Seeds enabled channel `tranzhiraru`, card count `4`, generated REST secret, and term slug `promokody-iz-telegram`.

- [ ] Write an activation test asserting the category, seeded channel, bounded card count, and non-empty secret.
- [ ] Run the test and verify it fails because the plugin does not exist.
- [ ] Implement the minimal bootstrap/config/activator classes and lifecycle hooks.
- [ ] Activate the plugin in the test, rerun, and verify the test passes.

### Task 2: Authenticated REST Import and Post Persistence

**Files:**
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-request-auth.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-promocode-repository.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-rest-controller.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-media-service.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-log.php`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-request-auth.php`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-import.php`

**Interfaces:**
- Consumes signed `GET /promokodiki-telegram/v1/config` and `POST /promokodiki-telegram/v1/import` requests.
- Produces idempotent `promocode` posts keyed by `_telegram_source_key`, category assignment, standard `_promocode_*` metadata, Telegram metadata, media attachment, batch result, and bounded import log.

- [ ] Write failing tests for valid/invalid/stale/replayed HMAC requests.
- [ ] Run them and verify expected authentication failures.
- [ ] Implement timestamp/nonce/signature verification and rerun to green.
- [ ] Write failing tests for accepted import, duplicate update, multiple-code rejection, direct-link fallback, manual lock, and source deletion.
- [ ] Implement minimal REST validation and repository persistence.
- [ ] Rerun import tests and verify every scenario passes.

### Task 3: Admitad Conversion, Ranking, Expiry, and Visibility

**Files:**
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-link-service.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-ranking.php`
- Create: `wp-content/plugins/promokodiki-telegram/includes/class-query.php`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-link-service.php`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-query.php`

**Interfaces:**
- Produces `resolve(string $destination_url): array{url:string,status:string,campaign_id:int}`, numeric rank score, hourly expiry hook, `top_ids(int $limit)`, and frontend query exclusion unless Telegram inclusion is explicit.

- [ ] Write failing tests for domain campaign matching, Admitad deeplink success, API fallback, and no-campaign fallback.
- [ ] Implement the link service using existing Admitad tables/client and rerun to green.
- [ ] Write failing tests for pin/rank ordering, exact timestamp expiry, 4–20 limits, search/category visibility, and general-query exclusion.
- [ ] Implement ranking/query/expiry hooks and rerun to green.

### Task 4: Administration and Editorial Locks

**Files:**
- Create: `wp-content/plugins/promokodiki-telegram/admin/class-admin.php`
- Create: `wp-content/plugins/promokodiki-telegram/admin/class-metabox.php`
- Create: `wp-content/plugins/promokodiki-telegram/assets/admin.css`
- Test: `wp-content/plugins/promokodiki-telegram/tests/php/test-admin.php`

**Interfaces:**
- Produces a Promocodes submenu with card-count setting, masked worker secret, channel CRUD/status/log table, sync-request flag, and a Telegram post metabox for lock/pin/source diagnostics.

- [ ] Write failing capability, nonce, sanitization, card-limit, channel CRUD, and metabox-save tests.
- [ ] Implement the minimal Settings API/admin-post actions and metabox.
- [ ] Rerun the administration test and verify it passes.

### Task 5: Telethon Parser and Worker

**Files:**
- Create: `wp-content/plugins/promokodiki-telegram/worker/requirements.txt`
- Create: `wp-content/plugins/promokodiki-telegram/worker/.env.example`
- Create: `wp-content/plugins/promokodiki-telegram/worker/run.py`
- Create: `wp-content/plugins/promokodiki-telegram/worker/promokodiki_telegram_worker/parser.py`
- Create: `wp-content/plugins/promokodiki-telegram/worker/promokodiki_telegram_worker/client.py`
- Create: `wp-content/plugins/promokodiki-telegram/worker/promokodiki_telegram_worker/telegram.py`
- Test: `wp-content/plugins/promokodiki-telegram/worker/tests/test_parser.py`
- Test: `wp-content/plugins/promokodiki-telegram/worker/tests/test_client.py`
- Test: `wp-content/plugins/promokodiki-telegram/worker/tests/test_sync.py`

**Interfaces:**
- Produces `parse_message(message, now) -> ParseResult`, `WordPressClient.signed_request()`, initial 200/seven-day scan, incremental/tracked-message revalidation, bounded media encoding, and one-shot exit semantics.

- [ ] Write failing parser tests for one code, repeated same code, multiple distinct codes, missing link, forwarded/reply messages, `erid` rejection, today/tomorrow/explicit/default expiry, and deterministic title generation.
- [ ] Implement the pure parser and rerun to green.
- [ ] Write failing client tests for exact HMAC headers and import payloads; implement the standard-library HTTP client and rerun.
- [ ] Write failing orchestration tests with fake Telegram/WordPress adapters; implement scan/revalidation/report behavior and rerun.
- [ ] Add the Telethon adapter, environment validation, interactive-login support, and deployment command examples without committing secrets.

### Task 6: Theme Slider and Legacy Removal

**Files:**
- Replace: `wp-content/themes/promokodiki/inc/top.php`
- Modify: `wp-content/themes/promokodiki/template-parts/partials/top.php`
- Replace: `wp-content/themes/promokodiki/js/top-promocodes.js`
- Modify: `wp-content/themes/promokodiki/js/main.js`
- Modify: `wp-content/themes/promokodiki/style.css`
- Modify: `wp-content/themes/promokodiki/assets/css/main.css`
- Replace: `wp-content/themes/promokodiki/tests/top-promocodes.php`
- Replace: `wp-content/themes/promokodiki/tests/top-promocodes.js`

**Interfaces:**
- Consumes `Promokodiki_Telegram_Query::top_ids()` and existing interaction metadata.
- Produces source-free Telegram cards, Swiper markup/configuration, 4/2/1 breakpoints, arrows/swipe/no autoplay, and a server-derived three-hour countdown without legacy snapshot AJAX.

- [ ] Write failing PHP assertions for category-only records, no source/nick markup, interaction data attributes, configurable limit, and empty state.
- [ ] Write failing Node assertions for countdown behavior and absence of legacy AJAX.
- [ ] Replace the old snapshot renderer and script; add Swiper initialization and CSS overrides.
- [ ] Rerun theme PHP/JS tests and verify they pass.

### Task 7: Documentation and Full Verification

**Files:**
- Create: `docs/telegram-promocodes-operations.md`
- Modify only files implicated by failing checks.

**Interfaces:**
- Produces Sprinthost SSH/virtualenv/first-login/Cron instructions, secret-copy instructions, recovery steps, and complete verification evidence.

- [ ] Document installation, first authorization, Cron command, channel administration, logs, fallback behavior, and secret/session placement outside `public_html`.
- [ ] Run Python unit tests and compile checks.
- [ ] Run every new WP-CLI integration test plus affected theme/plugin regression tests.
- [ ] Run Node tests, PHP syntax checks, `git diff --check`, and focused PHPCS when available.
- [ ] Review requirements line by line against the design and record any environment-only deployment steps.
