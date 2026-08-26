# Admitad Automation and Category Mapping Design

Date: 2026-07-27
Status: Approved design

## 1. Purpose

Refactor `wp-content/plugins/admitad-coupons` into a modular, autonomous Admitad synchronization plugin that:

- imports current coupons and actions reliably;
- maps Admitad data to the site's existing Russian category hierarchy;
- preserves explicit editorial decisions;
- explains every automatic classification;
- exposes configuration, review queues, health, and recovery controls in WordPress admin;
- remains safe when Admitad, WP-Cron, or individual records fail.

The public content model remains compatible with the existing theme and AJAX filter.

## 2. Current-State Findings

The current plugin already provides paginated coupon import, idempotency by `admitad_coupon_id`, OAuth token caching, a coarse import lock, and twice-daily WP-Cron scheduling.

The main classification defects are:

- coupon `categories` are stored in `_admitad_original_categories` but ignored by the mapper;
- the empty manual Admitad category table is searched against coupon text rather than matched against category IDs;
- company mapping has absolute first priority and can force every coupon from a broad retailer into one category;
- all 1,350 keyword rows are loaded and regex-tested for every coupon;
- at least dozens of normalized keywords map to conflicting terms;
- short fragments such as `тур`, `бан`, and `дух` can cause false positives;
- imported title, description, category, and active state are overwritten without independent editorial locks;
- `_promocode_is_active` is always written as `yes`;
- coupons missing from the API are never reconciled;
- API retries use blocking `sleep()`;
- unmatched logs are maintained as one JSON file under uploads;
- admin rendering, persistence, and actions are mixed in large procedural files.

The supplied API examples demonstrate that a coupon with an empty description can still contain useful stable category IDs. For example, a Lacoste coupon without a description contains the Admitad categories `Обувь` and `Одежда`.

## 3. Scope and Non-Goals

### In scope

- Coupon and action synchronization for the configured website.
- Daily synchronization of coupon categories and website campaigns.
- Stable ID-based category mapping.
- Campaign profiles and a deterministic keyword rule engine.
- Controlled automatic promotion tags.
- Classification confidence, explanations, review queues, and rule evidence.
- Manual category and content locks.
- Full reconciliation, deactivation, reactivation, and suspected-duplicate reporting.
- WP-Cron jobs, optional system-CRON support, health monitoring, and alerts.
- Admin configuration, mapping, diagnostics, dry-run, history, and rollback.
- Non-destructive migration of current mapping data.
- Required integration changes so inactive imported coupons are excluded from ordinary listings.

### Out of scope

- External AI/LLM classification.
- Automatic creation, renaming, re-parenting, or deletion of site categories.
- Automatic deletion of imported coupons.
- Automatic merging of suspected duplicates.
- Automatic conversion of a single editorial correction into a global rule.
- Replacing the existing `promocode` post type or public URL structure.

## 4. Invariants

These guarantees are enforced in code and cannot be disabled in settings:

1. The site's `promocode_category` hierarchy is editorial and immutable to the plugin.
2. Manual category locks and manual content locks always override automation.
3. Imported coupons are never automatically deleted.
4. Suspected duplicates are never automatically merged.
5. An incomplete API traversal never increments missing counters or deactivates coupons.
6. Credentials and tokens never appear in HTML, logs, email, reports, or exports.

## 5. Architecture

The plugin remains one WordPress plugin but is divided into focused components:

- **Configuration** — validated settings and environment/constant overrides.
- **API Client** — OAuth, request construction, pagination, response validation, and retry scheduling.
- **Sync Coordinator** — job state, cursors, locks, heartbeat, batching, and completion.
- **Normalizers** — convert coupon, campaign, and category responses into internal value objects.
- **Repositories** — isolate WordPress posts/meta, taxonomy assignments, and custom-table persistence.
- **Classifier** — deterministic signal evaluation and confidence calculation.
- **Rule Manager** — ID mappings, company profiles, keyword rules, evidence, conflicts, and candidates.
- **Reconciler** — last-seen tracking, missing counters, deactivation, and reactivation.
- **Review Queue** — unresolved, low-confidence, conflicting, and suspected-duplicate cases.
- **Audit and Rollback** — classification history and pre-change snapshots.
- **Admin Controllers and Views** — capability-checked actions separated from rendering.
- **Diagnostics and Notifications** — CRON health, run reports, admin notices, and critical email.
- **CLI Commands** — import, dry-run, reconcile, migration, diagnostics, and recovery.

Components communicate through explicit interfaces. The API layer does not write posts, the classifier does not query remote APIs, and admin views do not issue direct SQL.

## 6. Data Sources and Schedule

### Hourly coupon synchronization

Use:

`GET /coupons/website/{website_id}/`

with RU region/language constraints supported by the endpoint. Import both:

- `species=promocode`;
- `species=action`.

Only active offers valid for the configured website and RU audience with an affiliate link are eligible for normal publication.

### Daily reference synchronization

Use:

- `GET /coupons/categories/?language=ru`;
- `GET /advcampaigns/website/{website_id}/` with Russian category data when supported.

Coupon-category IDs and campaign-category IDs are separate namespaces even if their numeric values overlap.

### Daily reconciliation

Only a fully completed coupon traversal produces a reconciliation run. Coupons not seen in that completed run increment their missing counter. At two consecutive completed misses they become inactive. A later appearance resets the counter and reactivates the coupon.

WP-Cron is the supported baseline because it is already executing on the site. A real system cron invoking WP-CLI may be configured later without changing business logic.

## 7. WordPress Content Model

### Existing native entities

- `promocode` — public coupon/action record.
- `promocode_category` — editorial hierarchical topic taxonomy.
- `shops_category` — stores keyed by Admitad campaign ID.
- `promocode_tag` — promotion-mechanic tags.

### Coupon state meta

The implementation will maintain namespaced meta for:

- external coupon ID and raw source status;
- normalized source payload hash;
- last completed run in which the coupon was seen;
- consecutive completed miss count;
- active/inactive state;
- primary category term ID;
- classification explanation, confidence, and algorithm version;
- category lock and locked category set;
- content lock;
- current source-data version.

Imported source fields are updated only when the normalized payload hash changes.

### Active-state behavior

Expired coupons remain governed by the existing `date_end` behavior and the existing “show expired” setting.

API-inactive or reconciled-missing coupons:

- remain stored and retain statistics and history;
- are excluded from ordinary archives, filters, search, and promotional lists;
- may remain accessible on their singular URL with an inactive notice;
- reactivate automatically if they return.

The integration supplies one reusable active-query policy, and existing theme/filter consumers are updated to use it consistently.

## 8. Custom Tables

All tables use `$wpdb->prefix`, `dbDelta`, schema versioning, appropriate unique keys, and indexes.

### `admitad_category_map`

Maps a stable external category to an existing site term.

Key fields:

- source namespace: `coupon` or `campaign`;
- external category ID;
- external name and parent ID for display;
- site term ID;
- weight;
- status;
- timestamps.

Unique key: source namespace + external category ID + site term ID.

### `admitad_company_profile`

Stores one profile per stable campaign ID:

- campaign ID and current display name;
- default term ID when defined;
- signal weight;
- profile status;
- synchronized campaign-category snapshot;
- timestamps.

### `admitad_company_category`

Stores the allowed existing site terms for each campaign profile and whether a term is the default.

Unique key: campaign ID + site term ID.

### `admitad_rule`

Stores keyword/phrase rules and candidates:

- normalized phrase;
- match mode;
- target term ID;
- weight;
- status: active, candidate, suspended, or conflict;
- source: migrated, taxonomy seed, API evidence, or editorial;
- evidence count;
- distinct campaign count;
- contradiction count;
- timestamps and version.

The same normalized phrase cannot silently be active for conflicting categories.

### `admitad_review_queue`

Stores actionable cases:

- entity type and stable entity ID;
- reason code;
- severity;
- proposed categories and explanation;
- compact sanitized evidence;
- status, assignee, and resolution;
- timestamps.

Equivalent unresolved cases are deduplicated.

### `admitad_sync_run`

Stores operational runs:

- job type;
- status and cursor;
- start, heartbeat, and completion timestamps;
- processed, created, updated, unchanged, failed, deactivated, and reactivated counts;
- sanitized error summary.

### `admitad_classification_history`

Stores category changes and rollback data:

- post ID;
- algorithm/rule version;
- prior and resulting terms;
- prior and resulting primary term;
- confidence and explanation;
- trigger;
- actor;
- timestamp.

Detailed run and classification records default to 90-day retention. Necessary rollback snapshots and aggregates may be retained longer.

## 9. Classification Model

### Normalization

Text normalization is Unicode-safe and deterministic:

- lowercase;
- normalize `ё`/`е` for matching while retaining original text;
- normalize whitespace, punctuation, and common hyphen variants;
- tokenize with Unicode-aware word boundaries;
- match full phrases or whole tokens by default.

Naive arbitrary stemming is not used. Prefix/fragment rules require explicit reviewed match modes.

### Signal order

1. **Manual category lock** — return the locked category set unchanged.
2. **Mapped coupon category IDs** — strongest automatic evidence.
3. **Mapped campaign category hierarchy and company profile** — allowed set, default, and supporting evidence.
4. **Title phrase rules** — stronger text evidence.
5. **Description phrase rules** — weaker text evidence.
6. **Fallback** — parent category or `other`.

Company profiles no longer force one category unconditionally. Marketplaces may have broad allowed sets and no fixed default.

### Confidence

Exact numeric thresholds and signal weights are configurable and calibrated against the approved validation sample. The semantic behavior is fixed:

- **High confidence** — a mapped structured coupon category, or multiple independent signals that agree; assign the supported subcategory/categories.
- **Medium confidence** — sufficient evidence for a thematic parent but not a safe leaf; assign the parent and enqueue for optional review.
- **Low confidence** — weak, absent, or conflicting evidence; assign `other`, publish if otherwise eligible, and enqueue.
- **Conflict** — incompatible strong signals; use the safe fallback and enqueue.

### Multiple categories

A coupon may receive up to three confirmed thematic terms:

- one primary term;
- up to two secondary terms.

The primary term is the highest-scoring supported term. Ties are resolved by:

1. stronger signal class;
2. deeper valid taxonomy term;
3. stable term ID for deterministic output.

A parent is not redundantly assigned when its child is assigned. Parent archive inclusion relies on the hierarchical taxonomy query behavior. A parent may be assigned alone for medium-confidence broad classification.

### Explanation

Every result records:

- input signals used;
- matched external IDs and rules;
- rejected conflicts;
- score/confidence;
- primary and secondary terms;
- fallback reason when applicable;
- classifier and rule-set versions;
- classification timestamp.

## 10. Keyword Strategy

The current 1,350 rows are preserved through migration, not discarded.

Migration will:

- normalize phrases;
- merge exact safe duplicates;
- suspend conflicts;
- suspend unsafe short fragments and ambiguous match modes;
- retain original provenance and weight;
- enqueue conflicts for review.

Every existing site category and subcategory receives at least one taxonomy-seed rule based on its normalized exact name. Additional safe phrase variants may be active when they remain unambiguous.

Synonyms, brands, short forms, and vocabulary mined from real coupons/campaigns begin as candidates unless explicitly approved.

A candidate may auto-activate only when all conditions hold:

- at least five supporting coupon observations;
- observations span at least two distinct campaigns;
- structured Admitad mappings consistently support the same site term;
- contradiction count is zero.

One weak word match is never enough for high confidence.

When an editor resolves a queue item, the available actions are:

- correct only this coupon and lock its categories;
- create/update an external category mapping;
- create/update a company profile;
- approve, suspend, or correct a keyword rule.

No global rule is inferred silently from a one-off correction.

## 11. Promotion Tags

When enabled, the plugin maps structured Admitad fields to a controlled set of service tags:

- discount;
- free delivery;
- gift;
- new customer;
- all customers;
- exclusive;
- personal.

Arbitrary API strings do not create tags.

Automatic tag management is configurable. Disabling it stops future automatic creation and updates but does not remove existing tag relationships. Cleanup requires a separate confirmed operation.

## 12. Editorial Locks

### Category lock

An administrator or editor may set categories manually. A manual save stores:

- the locked term set;
- the primary term;
- actor and timestamp.

Automated classification may still compute a shadow proposal for diagnostics, but it cannot change the locked assignment. “Return to automation” explicitly clears the lock.

### Content lock

Title and description normally track Admitad. A manual editorial change can lock content independently. While locked:

- title and description are preserved;
- status, dates, discount, affiliate URLs, campaign data, and other source-owned fields continue to update.

The post editor clearly shows lock state and provides an explicit unlock action.

## 13. Duplicate Policy

Admitad coupon ID remains the canonical import key.

Potential duplicates are detected using supporting evidence such as:

- campaign ID;
- promo code;
- overlapping validity range;
- normalized title;
- target link characteristics.

Suspected duplicates are queued but never automatically merged or deleted because similar records can represent different regions or conditions.

## 14. Synchronization and Failure Handling

### Incremental writes

The normalized source payload is hashed. Unchanged records avoid post, meta, term, cache, and classification writes unless the rule/classifier version requires reclassification.

### Batching

Long work is split into resumable page/batch jobs. Each batch updates run heartbeat and cursor. WordPress requests do not block on long retry sleeps.

### Retry policy

- `401` — refresh the token once, then fail the run if authorization still fails.
- `429` — honor `Retry-After` and schedule a delayed continuation.
- `5xx` and transport failures — exponential delayed retries with jitter and a bounded attempt count.
- invalid JSON/schema — fail the affected page safely and mark the traversal incomplete.

### Completion safety

Only a traversal that reaches the API's declared end is complete. An incomplete run:

- does not increment missing counters;
- does not deactivate records;
- retains the last known good content;
- records diagnostics and contributes to consecutive-failure alerts.

An individual malformed coupon is logged and queued while the rest of the valid page continues.

### Locking

Jobs use an atomic lock with owner token, heartbeat, and expiry. A live lock prevents overlap. A stale lock is recoverable and visible in diagnostics. Lock release occurs on success and failure paths.

### Notifications

- Admin dashboard always shows last runs and health.
- Admin notices report delayed CRON and material queue growth.
- Email is sent after two consecutive import failures or an authorization failure.
- Repeated equivalent alerts are throttled.

## 15. Admin Information Architecture

The plugin appears under the existing Promocodes administration area with these sections:

1. **Overview** — health, latest runs, queue counts, accuracy sample, and safe manual actions.
2. **Synchronization** — run history, schedules, current job, manual sync, reconcile, and cancellation/recovery controls.
3. **Category Mapping** — coupon and campaign category IDs mapped to existing terms.
4. **Company Profiles** — campaign defaults, allowed terms, weights, and marketplace behavior.
5. **Keywords** — active rules, candidates, conflicts, evidence, bulk review, and taxonomy seeding.
6. **Review Queue** — low confidence, conflicts, unmapped IDs, missing mappings, and suspected duplicates.
7. **History and Rollback** — change previews, classification history, snapshots, and rollback.
8. **Settings** — operational configuration.
9. **Diagnostics** — credentials status without secrets, API scopes, WP-Cron health, schema version, locks, and sanitized exports.

### Configurable settings

- coupon sync interval, default hourly;
- reference sync interval, default daily;
- reconciliation interval, default daily;
- batch size and bounded retry settings;
- completed-miss threshold, default two;
- high/medium confidence thresholds;
- signal weights;
- maximum category count, default three;
- candidate auto-activation requirements, default 5 observations / 2 campaigns / 0 contradictions;
- auto-tag enable/disable;
- email recipient and alert enablement;
- detailed log retention, default 90 days;
- queue-growth warning threshold;
- role access within the agreed administrator/editor limits.

Settings changes are sanitized, capability checked, nonce protected, and validated against safe bounds.

### Fixed guarantees

The invariants in section 4 are displayed but are not configurable.

## 16. Roles and Security

- **Administrator** — credentials, schedules, settings, mappings, rules, bulk reclassification, migration, rollback, and diagnostics.
- **Editor** — review queue, per-coupon corrections, and content/category locks.
- **Other roles** — no automation-management access.

Sensitive admin actions require nonces and explicit capabilities. Inputs are sanitized and outputs escaped. Direct SQL uses `$wpdb->prepare()` where values are dynamic.

Credential precedence:

1. constants or environment-backed configuration;
2. WordPress options as a fallback.

Stored secrets are masked and never re-rendered into form values. Replacing a secret is explicit; a blank field does not erase it accidentally.

## 17. Migration and Rollout

The first rollout is non-destructive:

1. Create a database/content backup and classification snapshot.
2. Install the new schema alongside existing tables.
3. Copy and normalize existing category, keyword, and company mappings.
4. Suspend detected conflicts and unsafe fragments.
5. Seed exact taxonomy-name rules for every current category and subcategory.
6. Synchronize Admitad reference data.
7. Run the classifier in shadow dry-run mode.
8. Produce an old-versus-proposed report.
9. Validate at least 150 representative coupons.
10. Require administrator confirmation.
11. Apply classifications in resumable batches.
12. Enable the new schedules.
13. Monitor health and queue behavior.

Legacy mapping data remains available during stabilization. Its removal is a separate future confirmed operation.

Rule changes after rollout:

- identify only affected unlocked coupons;
- generate a preview;
- reclassify in background batches after confirmation;
- write classification history for rollback.

## 18. Acceptance Criteria

### Classification quality

On a representative manually reviewed sample of at least 150 coupons:

- at least 95% accuracy among high-confidence assignments;
- at least 85% of coupons receive a more specific result than `other`;
- 100% preservation of manual category and content locks;
- no assignment outside the campaign's configured allowed profile;
- no more than three thematic terms per coupon.

### Synchronization

- Re-running an unchanged completed sync causes no duplicate posts and no unnecessary content writes.
- A partial traversal cannot deactivate coupons.
- Two completed misses deactivate a coupon.
- A later appearance reactivates it.
- Inactive imported coupons are absent from ordinary listings.
- Expired-coupon behavior remains controlled by the existing frontend setting.

### Operations

- WP-Cron tasks and their health are visible in admin.
- Manual sync, dry-run, reconciliation, and recovery actions are capability and nonce protected.
- Two consecutive failures or an authorization failure produce a throttled critical alert.
- No diagnostic output exposes credentials, tokens, or full sensitive payloads.

## 19. Test Strategy

### Unit tests

- Unicode normalization and word boundaries;
- exact phrase and explicit fragment modes;
- signal priority and score aggregation;
- high, medium, low, and conflict outcomes;
- primary/secondary category selection;
- company allowed-set enforcement;
- candidate evidence thresholds;
- manual locks;
- payload hashing and unchanged detection.

### API and synchronization integration tests

- OAuth refresh success and failure;
- pagination to declared completion;
- `429`, `5xx`, transport, and invalid JSON behavior;
- resumable cursors and bounded retries;
- per-item failure isolation;
- completed versus incomplete reconciliation;
- deactivation and reactivation;
- atomic lock, heartbeat, stale recovery, and overlap prevention.

### WordPress integration tests

- schema installation and migration;
- post/meta/taxonomy upsert idempotency;
- active-state filtering across theme, search, archive, and AJAX filter consumers;
- expired-coupon compatibility;
- admin roles, capabilities, nonces, sanitization, and escaping;
- manual category/content lock detection and release;
- tag toggle behavior;
- dry-run, preview, apply, and rollback.

### Regression and performance

- existing cards, archives, shops, categories, filters, and singular pages;
- full current dataset within safe memory and execution limits;
- unchanged hourly sync substantially cheaper than a full write pass;
- no changes to unrelated theme presentation.

## 20. Documentation References

- Admitad coupon API: `https://support.admitad.ru/article/ru/320-kupony.html`
- Admitad campaign API: `https://support.admitad.ru/article/ru/312-partnerskie-programmy.html`
- Coupon endpoint: `https://api.admitad.com/coupons/website/{website_id}/`
- Campaign endpoint: `https://api.admitad.com/advcampaigns/website/{website_id}/`
- Coupon categories endpoint: `https://api.admitad.com/coupons/categories/?language=ru`
