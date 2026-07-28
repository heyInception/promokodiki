# Admitad Admin AJAX and Data Recovery Design

Date: 2026-07-28
Status: Approved in conversation; awaiting written-spec review

## 1. Purpose

Improve every Admitad administration section so that it is understandable in
Russian, works without full-page reloads, and safely restores the legacy
classification data that was not migrated into the new automation tables.

The work covers:

- Russian labels, descriptions, statuses, tooltips, and field help;
- a shared AJAX experience for forms, searches, filters, tabs, pagination, and
  bulk operations;
- hierarchical category labels;
- local AJAX company autocomplete;
- more useful review and history tables;
- archival and restoration of keyword rules;
- non-destructive recovery of legacy keywords and company mappings;
- previewed, reversible reclassification of coupons.

## 2. Confirmed Current State

The audit on 2026-07-28 established:

- 902 non-trashed `promocode` posts exist;
- 684 coupon relationships point to term `665`, `Прочее`;
- `wp_subcategory_keywords` still contains 1,350 legacy keyword rows;
- `wp_admitad_companies_mapping` still contains 59 legacy company mappings;
- `wp_admitad_category_mapping` contains no rows;
- the new category-map, company-profile, company-category, and rule tables
  contain no rows;
- the durable legacy-migration option is empty, so the migration has not run;
- the only recorded reference synchronization failed before credentials were
  configured;
- recent coupon synchronizations complete, but 651 open queue items are marked
  `low_confidence`;
- the history paginator generates `/edit.php/page/2/` instead of preserving the
  WordPress admin route with `&paged=2`.

The primary cause of the mass `Прочее` assignment is therefore absent structured
reference data and absent migrated rules/profiles, not deletion of the legacy
source rows.

## 3. Scope and Non-Goals

### In scope

- All nine Admitad administration sections.
- Shared admin-only JavaScript and CSS for asynchronous interactions.
- Shared PHP helpers/components for statuses, term paths, table responses, and
  pagination state.
- AJAX endpoints for read and write operations.
- Safe legacy migration and reclassification workflow.
- Automated tests and browser verification in the local WordPress admin.

### Non-goals

- Replacing the existing WordPress admin with a standalone React application.
- Deleting legacy tables after migration.
- Automatically modifying the editorial taxonomy hierarchy.
- Silently resolving ambiguous company matches or conflicting keyword rules.
- Overwriting category or content editorial locks.
- Hard-deleting keyword rules through the ordinary administration interface.

## 4. Architecture

The existing repositories and services remain the authoritative data and
business-logic layer. A shared admin AJAX controller sits above them and returns
consistent JSON envelopes and rendered fragments.

The browser layer consists of:

- one enqueued admin JavaScript entry point;
- small modules for requests, table state, forms, notifications, tooltips, and
  batch progress;
- one admin stylesheet using WordPress-compatible visual patterns;
- page configuration supplied with `wp_add_inline_script()` or
  `wp_localize_script()` and containing only non-sensitive values.

Server-rendered pages remain usable as a progressive-enhancement fallback.
Without JavaScript, safe GET navigation and ordinary nonce-protected POST
actions continue to work.

All administration pages use the same state model:

- `page`: WordPress submenu slug;
- `paged`: one-based page number;
- `per_page`: one of `20`, `50`, or `100`, default `20`;
- `s`: search text where supported;
- page-specific filters such as status, reason, or tab.

Successful AJAX navigation updates the query string with `history.pushState()`.
Browser `popstate` restores the matching table state without a full reload.

## 5. AJAX Contract

Every endpoint:

- requires an authenticated user;
- verifies a purpose-specific nonce;
- checks the existing Admitad capability for the requested operation;
- sanitizes every scalar and validates every ID against its repository/domain;
- returns escaped HTML fragments or structured scalar data;
- uses a consistent success/error response shape;
- never returns credentials, tokens, secrets, raw SQL errors, or stack traces.

Read endpoints support table refresh, search, filters, tabs, pagination, page
size, company autocomplete, and progress polling.

Write endpoints support mapping/profile/rule persistence, rule archival and
restoration, review resolution, synchronization actions, preview creation,
batch application, and rollback.

Write responses include a human-readable Russian message and any fragments that
must be refreshed. Buttons remain disabled while their request is in flight.

Bulk operations use durable server-side state and bounded batches. A second
request cannot start the same operation while its lock is live. Progress
responses include processed, total, succeeded, skipped, and failed counts.
Individual row failures are retained in the final report and do not discard
successful rows.

## 6. Shared Interface Components

### Russian status registry

Internal values remain unchanged for compatibility. The interface maps them to
Russian labels and semantic badges. The registry covers at least:

- synchronization: scheduled, running, completed, failed, cancelled, delayed;
- mapping/profile: active, inactive, unmapped;
- rules: active, candidate, suspended, conflict, archived;
- review: open, resolved, archived;
- confidence: high, medium, low, conflict;
- snapshots: previewed, applied, rolled back, expired.

Unknown values are displayed safely as a neutral translated fallback, with the
technical value available only as supplementary text.

### Hierarchical taxonomy labels

Every category option and category name uses its complete ancestor path:

`Родитель → Дочерняя рубрика → Вложенная рубрика`

The helper handles arbitrary depth, missing ancestors, invalid terms, and term
cycles defensively. The taxonomy itself is never changed.

### Forms and help

Every visible `input`, `select`, and `textarea` receives:

- a programmatically associated `<label>`;
- a Russian field name;
- concise help text where the meaning is not obvious;
- an accessible tooltip for secondary explanations;
- validation feedback adjacent to the field.

Tooltips work with mouse, keyboard focus, and touch, and do not hide essential
instructions.

### Loading and notifications

Tables retain their current content while loading and show a lightweight
overlay/skeleton. Forms show button-level progress. Success, warning, and error
messages appear in accessible WordPress-style notices and are announced through
an ARIA live region.

## 7. Administration Sections

### 7.1 Admitad: Overview

- Translate all visible operational statuses.
- Expand the introduction to explain health, synchronization, review workload,
  classification coverage, and the purpose of safe manual actions.
- Turn summary values into readable cards with links to the relevant section.
- Refresh status cards and recent activity through AJAX.

### 7.2 Synchronization

- Translate job types, statuses, counters, and action labels.
- Add clear descriptions and accessible tooltips to every action button.
- Explain the difference between coupon sync, reference sync, reconciliation,
  recovery, and cancellation.
- Run actions through AJAX and show durable progress and final reports.
- Prevent duplicate launches while a matching lock is active.

### 7.3 Admitad Category Mapping

- Add a practical explanation of coupon and campaign namespaces and mapping
  weight.
- Label every field.
- Show taxonomy choices as full hierarchical paths.
- Search, save, filter, paginate, and change page size through AJAX.
- Display Russian mapping statuses.

### 7.4 Admitad Company Profiles

- Populate the searchable company control from the locally synchronized Admitad
  campaign reference list.
- Use debounced AJAX autocomplete; do not call the remote Admitad API for each
  keystroke.
- Store and submit the stable campaign ID while showing the company name.
- Refresh the local reference list through the dedicated reference
  synchronization workflow.
- Show categories as full hierarchical paths.
- Label and explain:
  - allowed categories (`allowed_term_ids`): the only categories automation may
    assign to coupons from the company;
  - default category (`default_term_id`): an optional fallback within the
    allowed set;
  - signal weight: the relative strength of the company profile during
    classification.
- Validate that a default category belongs to the allowed set.

### 7.5 Keyword Rules

- Make search, filtering, pagination, and page-size selection asynchronous.
- Translate match modes and statuses:
  - phrase: exact normalized phrase;
  - token: complete word;
  - prefix: reviewed word prefix;
  - active, candidate, suspended, conflict, archived.
- Label every field and show hierarchical category paths.
- Add an archive action to every active/listed rule.
- Archiving stops the rule from classification but preserves evidence,
  provenance, history, and the ability to restore it.
- Add an archived filter and restore action.
- Do not hard-delete through the ordinary screen.

### 7.6 Review Queue

Add AJAX tabs:

- All;
- Low confidence;
- Conflicts;
- Unknown categories;
- Companies without profiles;
- Possible duplicates;
- Archive.

Tabs with no relevant records may be hidden. Each row shows:

- coupon title and post ID;
- company;
- current hierarchical category path;
- reason for review;
- confidence;
- creation/update date;
- public preview link;
- edit link;
- relevant evidence and available resolution actions.

Equivalent open cases remain deduplicated.

### 7.7 Classification History and Rollback

- Add a plain-language explanation of immutable previews, application,
  validation samples, history, and rollback.
- Replace bare coupon IDs with coupon titles, post IDs, public links, and edit
  links.
- Replace bare category IDs with hierarchical category paths while retaining IDs
  as secondary detail when useful.
- Translate triggers, snapshot states, confidence, and report metrics.
- Run table navigation and page-size changes through AJAX.
- Generate canonical URLs such as:
  `edit.php?post_type=promocode&page=admitad-history&paged=2&per_page=20`.
- Run preview, apply, validation, and rollback in bounded AJAX batches with
  explicit confirmation for apply and rollback.

### 7.8 Settings

- Label all controls consistently and expand unclear descriptions.
- Keep credential masking and existing secret-handling guarantees.
- Save and validate through AJAX while preserving a non-JavaScript fallback.

### 7.9 Diagnostics

- Translate statuses and explain each diagnostic.
- Refresh diagnostics through AJAX.
- Keep sanitized exports free of credentials and tokens.

## 8. Data Recovery Workflow

Recovery is staged and non-destructive:

1. Create a database backup before any migration or reclassification write.
2. Run a reference synchronization to populate current Admitad categories and
   campaigns.
3. Verify reference counts and surface any API/schema errors.
4. Run the existing legacy migration in bounded, idempotent batches:
   - migrate the 1,350 keyword rows;
   - migrate category-name rows if any appear;
   - match each of the 59 company rows to exactly one synchronized campaign.
5. Preserve all legacy source tables.
6. Mark unsafe short/wildcard rules as suspended.
7. Mark phrases mapped to conflicting terms as conflict.
8. Enqueue ambiguous/unmatched companies instead of guessing.
9. Verify destination coverage, orphan terms, suspended rules, conflicts, and
   migration cursor completion.
10. Create an immutable reclassification preview for eligible coupons.
11. Review counts and a representative sample before application.
12. Apply the approved snapshot through bounded AJAX batches.
13. Preserve all editorial category/content locks.
14. Retain the snapshot and classification history for rollback.

Reclassification uses the recovered current rules and profiles. It does not
blindly restore historical category IDs.

## 9. Error Handling

- Network errors leave current table content in place and offer a retry.
- Validation errors remain attached to their fields.
- Expired nonces produce a Russian session-refresh message.
- Capability failures return HTTP 403 without leaking administrative data.
- A failed bulk item is recorded with a sanitized reason and included in the
  final report.
- Interrupted batch operations resume from durable cursor state.
- Live locks explain which operation is running and prevent duplicate starts.
- Stale locks are recoverable through the existing authorized recovery flow.

## 10. Accessibility and Visual Design

The visual language remains native to WordPress while improving hierarchy and
clarity:

- responsive summary cards;
- readable spacing and table density;
- semantic color badges that also include text;
- visible focus states;
- keyboard-accessible tooltips and controls;
- loaders that respect reduced-motion preferences;
- responsive table wrappers without breaking admin navigation.

No third-party UI framework is introduced.

## 11. Security

- Continue using the existing Admitad capabilities.
- Use purpose-specific nonces for every AJAX mutation.
- Sanitize request values with WordPress functions appropriate to their types.
- Escape all rendered output.
- Use repository methods and prepared statements for dynamic database values.
- Never expose credentials, OAuth tokens, secrets, or raw remote payloads.
- Require explicit confirmation immediately before AJAX apply and rollback.
- Preserve existing taxonomy and editorial-lock invariants.

## 12. Testing and Acceptance Criteria

Automated coverage must include:

- Russian status mapping and unknown-status fallback;
- arbitrary-depth hierarchical term paths;
- query-state parsing and canonical admin pagination URLs;
- valid `per_page` values and bounds;
- AJAX nonce and capability rejection;
- sanitized search/filter inputs;
- company autocomplete result limits and stable campaign IDs;
- company default/allowed validation;
- keyword archive and restore behavior;
- review tab filtering and coupon detail rendering;
- history titles, links, term paths, and pagination;
- idempotent legacy migration and preservation of source tables;
- conflict, unsafe-rule, orphan-term, and ambiguous-company handling;
- reclassification preview, manual-lock preservation, application, and rollback;
- batch locking, progress, partial failures, and resume behavior.

Acceptance requires:

- existing PHP test suite passes;
- PHP syntax checks pass;
- WordPress coding/security checks pass where tooling is available;
- every listed admin interaction works through AJAX without a full reload;
- stateful GET URLs remain directly loadable;
- browser Back/Forward restores AJAX state;
- page-size choices are exactly 20, 50, and 100;
- no ordinary keyword action hard-deletes a rule;
- the legacy 1,350 keywords and 59 company mappings are accounted for by
  migrated, suspended/conflict, or queued outcomes;
- reclassification is applied only after preview approval;
- manually locked coupons remain unchanged;
- rollback restores the pre-application assignments;
- the final category distribution and unresolved fallback reasons are reported.

## 13. Delivery Order

1. Shared status, term-path, pagination, response, asset, and UI foundations.
2. Read-only AJAX tables and URL state.
3. AJAX forms and safe single-record mutations.
4. Company autocomplete and rule archival.
5. Review queue and history detail upgrades.
6. Durable bulk-operation progress.
7. Backup, reference sync, legacy migration, and verification.
8. Previewed reclassification, application, and rollback.
9. Full automated and browser verification.
