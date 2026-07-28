# Admitad Admin Sections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade all nine Admitad administration sections with Russian copy, labeled forms, hierarchical terms, detailed tables, and shared AJAX navigation/actions.

**Architecture:** Page controllers consume the shared request-state and presenter services from the foundation plan. Each list page has a page view plus a replaceable table partial; repositories accept bounded filters and return deterministic totals.

**Tech Stack:** WordPress admin, PHP 8.4, shared Admitad AJAX foundation, existing repositories and services.

## Global Constraints

- Complete `2026-07-28-admitad-admin-ajax-foundation.md` first.
- Follow the approved design specification.
- Preserve all existing admin-post fallbacks.
- Translate labels, never persisted internal status values.
- Use full category paths everywhere.
- Page sizes are 20, 50, and 100.
- Keyword deletion means archival, never hard deletion.
- Do not modify the user's theme stylesheet.

---

### Task 1: Filterable Repository Contracts

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-category-map-repository.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-company-profile-repository.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-rule-repository.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-review-queue-repository.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-classification-history-repository.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-list-repositories.php`

**Interfaces:**
- `list_rows(string $search, int $page, int $per_page, array $filters = array()): array`
- `Promokodiki_Admitad_Company_Profile_Repository::search_campaigns(string $search, int $limit = 20): array`
- `Promokodiki_Admitad_Rule_Repository::set_status(int $rule_id, string $status): bool` accepts `archived`.
- `Promokodiki_Admitad_Review_Queue_Repository::list_rows()` accepts status/reason filters.

- [ ] **Step 1: Write failing repository tests**

Create fixtures and assert:

```php
$rules = ( new Promokodiki_Admitad_Rule_Repository() )->list_rows(
	'fixture',
	1,
	50,
	array( 'status' => 'archived' )
);
Promokodiki_Admitad_Test_Harness::assert_same( 50, $rules['per_page'] );
Promokodiki_Admitad_Test_Harness::assert_true(
	( new Promokodiki_Admitad_Rule_Repository() )->set_status( $rule_id, 'archived' )
);
```

Assert invalid page sizes clamp to 20, autocomplete returns stable
`campaign_id`/`display_name` pairs only, and review reason filtering affects both
items and total.

- [ ] **Step 2: Run the test and verify signature/status failures**

- [ ] **Step 3: Implement prepared filter clauses**

Build allowlisted SQL fragments only. Prepare every dynamic value. Keep ordering
stable with an ID tie-breaker.

- [ ] **Step 4: Preserve campaign reference data when saving profiles**

Replace destructive `$wpdb->replace()` behavior with an upsert that preserves
`created_at` and `category_snapshot` while updating editable profile fields.

- [ ] **Step 5: Run repository tests and existing mapping/rule/history tests**

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-category-map-repository.php wp-content/plugins/admitad-coupons/includes/class-company-profile-repository.php wp-content/plugins/admitad-coupons/includes/class-rule-repository.php wp-content/plugins/admitad-coupons/includes/class-review-queue-repository.php wp-content/plugins/admitad-coupons/includes/class-classification-history-repository.php wp-content/plugins/admitad-coupons/tests/php/test-admin-list-repositories.php
git commit -m "feat: add filtered Admitad admin repositories"
```

### Task 2: Category Mapping and Company Profiles

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-category-map-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-company-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/category-map.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/companies.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/category-map-table.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/company-table.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-mapping-ajax.php`

**Interfaces:**
- AJAX operations: `category_map_list`, `category_map_save`, `company_list`, `company_search`, `company_save`
- Company autocomplete output: `array<int,array{id:int,text:string}>`

- [ ] **Step 1: Write failing rendered-form tests**

Assert every named control has a matching label, copy contains Russian
explanations for namespace, allowed categories, default category, and signal
weight, and option text contains `Parent → Child`.

- [ ] **Step 2: Write failing AJAX tests**

Assert company search requires `manage_admitad_automation`, returns at most 20
results, and profile save rejects a default outside `allowed_term_ids`.

- [ ] **Step 3: Update page controllers**

Parse `Promokodiki_Admitad_Admin_Request`, pass `per_page`, use presenter term
options, and expose only view data.

- [ ] **Step 4: Split replaceable table partials**

Each partial includes the table, empty state, page-size selector, and canonical
pagination links with `data-admitad-ajax`.

- [ ] **Step 5: Implement company autocomplete**

Use a debounced search field and hidden `campaign_id`. Selecting a result sets
both the visible company label and stable ID. Clearing/changing text invalidates
the hidden ID until a result is selected.

- [ ] **Step 6: Route AJAX mutations through existing action methods**

Do not duplicate repository writes in the AJAX controller. Call
`create_global_category_map()` and `save_company_profile()`, then render the
updated partial.

- [ ] **Step 7: Run tests and manually verify keyboard selection**

- [ ] **Step 8: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/pages/class-category-map-page.php wp-content/plugins/admitad-coupons/admin/pages/class-company-page.php wp-content/plugins/admitad-coupons/admin/views/category-map.php wp-content/plugins/admitad-coupons/admin/views/companies.php wp-content/plugins/admitad-coupons/admin/views/partials/category-map-table.php wp-content/plugins/admitad-coupons/admin/views/partials/company-table.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/admin/class-admin-actions.php wp-content/plugins/admitad-coupons/tests/php/test-admin-mapping-ajax.php
git commit -m "feat: improve Admitad mapping and company UI"
```

### Task 3: Keyword Search, Russian Choices, Archive, and Restore

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-rule-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/rules.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/rules-table.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-rules-ajax.php`

**Interfaces:**
- AJAX operations: `rule_list`, `rule_save`, `rule_archive`, `rule_restore`, `rule_status`
- `Promokodiki_Admitad_Admin_Actions::archive_rule(int $rule_id): true|WP_Error`
- `Promokodiki_Admitad_Admin_Actions::restore_rule(int $rule_id): true|WP_Error`

- [ ] **Step 1: Write failing action tests**

```php
$archived = $actions->archive_rule( $rule_id );
Promokodiki_Admitad_Test_Harness::assert_true( true === $archived );
Promokodiki_Admitad_Test_Harness::assert_same(
	'archived',
	$repository->find_status( 'fixture phrase', $term_id )
);
```

Assert editors are forbidden and restoration returns the rule to `suspended`
rather than activating it silently.

- [ ] **Step 2: Write failing view tests**

Assert labels exist for phrase, category, match mode, status, and weight; all
select choices are Russian; every row has an archive or restore action.

- [ ] **Step 3: Implement actions and AJAX routes**

Archive is explicit. Restore to `suspended` so an administrator must deliberately
activate a recovered rule.

- [ ] **Step 4: Implement AJAX search and list state**

Submit search after 300 ms debounce or immediate form submit, reset `paged` to
1, update URL, support status filter including archive, and avoid full reload.

- [ ] **Step 5: Run tests**

Run new test plus `test-text-rules.php`.

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/pages/class-rule-page.php wp-content/plugins/admitad-coupons/admin/views/rules.php wp-content/plugins/admitad-coupons/admin/views/partials/rules-table.php wp-content/plugins/admitad-coupons/admin/class-admin-actions.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/assets/js/admin.js wp-content/plugins/admitad-coupons/tests/php/test-admin-rules-ajax.php
git commit -m "feat: add AJAX keyword rule management"
```

### Task 4: Detailed Review Queue Tabs

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-review-queue-repository.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-review-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/review.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/review-table.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-review-ajax.php`

**Interfaces:**
- AJAX operations: `review_list`, `review_resolve_coupon`, `review_archive`
- Presenter rows include `post_id`, `title`, `company`, `term_paths`, `reason`, `confidence`, `created_at`, `view_url`, `edit_url`.

- [ ] **Step 1: Write failing repository/presenter tests**

Create low-confidence and duplicate fixtures linked to real promocode posts.
Assert tab filters return correct totals and rows include title and safe URLs.

- [ ] **Step 2: Implement reason groups**

Map stored reasons into:

```php
array(
	'low_confidence'   => array( 'low_confidence' ),
	'conflicts'        => array( 'conflict', 'rule_conflict' ),
	'unknown_category' => array( 'unmapped_category', 'missing_mapping' ),
	'missing_company'  => array( 'missing_campaign_id', 'missing_company_profile' ),
	'duplicates'       => array( 'suspected_duplicate' ),
)
```

Unknown reasons remain visible in `all`.

- [ ] **Step 3: Build detailed rows and tabs**

Resolve coupon entities by stable Admitad coupon ID, limit lookup to two posts,
and mark non-unique/missing posts without inventing a link.

- [ ] **Step 4: Add AJAX list and resolution routes**

Reuse `resolve_coupon_only()`. Archive changes queue status to `archived` and
preserves evidence.

- [ ] **Step 5: Verify editor/admin capabilities**

Editors follow the existing `editor_review_enabled` gate; global mapping actions
remain administrator-only.

- [ ] **Step 6: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-review-queue-repository.php wp-content/plugins/admitad-coupons/admin/pages/class-review-page.php wp-content/plugins/admitad-coupons/admin/views/review.php wp-content/plugins/admitad-coupons/admin/views/partials/review-table.php wp-content/plugins/admitad-coupons/admin/class-admin-actions.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/tests/php/test-admin-review-ajax.php
git commit -m "feat: add detailed Admitad review tabs"
```

### Task 5: History Details and Correct AJAX Pagination

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-history-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/history.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/history-table.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/history-snapshot.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-history-ajax.php`

**Interfaces:**
- AJAX operations: `history_list`, `history_snapshot`, `history_sample_review`
- Canonical pagination retains `post_type`, `page`, `paged`, `per_page`, `snapshot`, and `sample`.

- [ ] **Step 1: Write the pagination regression test**

Render page 1 with more than 20 fixtures and assert:

```php
Promokodiki_Admitad_Test_Harness::assert_true(
	str_contains(
		$html,
		'edit.php?post_type=promocode&amp;page=admitad-history&amp;paged=2'
	)
);
Promokodiki_Admitad_Test_Harness::assert_true(
	! str_contains( $html, '/edit.php/page/2/' )
);
```

- [ ] **Step 2: Write detailed-row tests**

Assert coupon title, ID, view/edit URLs, translated trigger/confidence, and full
before/after term paths appear.

- [ ] **Step 3: Implement explicit paginator base**

Use the request-state URL with `%_%` and `format => '&paged=%#%'`, or render
links from request-state query args. Do not rely on the current rewrite-aware
request path.

- [ ] **Step 4: Split list and snapshot partials**

Keep preview/sample forms in the page view; AJAX replaces only their result and
history list containers.

- [ ] **Step 5: Add AJAX routes**

List and sample-review are immediate. Preview/apply/rollback progress belongs to
the recovery plan.

- [ ] **Step 6: Run test and confirm the original URL bug is absent**

- [ ] **Step 7: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/pages/class-history-page.php wp-content/plugins/admitad-coupons/admin/views/history.php wp-content/plugins/admitad-coupons/admin/views/partials/history-table.php wp-content/plugins/admitad-coupons/admin/views/partials/history-snapshot.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/tests/php/test-admin-history-ajax.php
git commit -m "fix: add canonical AJAX history pagination"
```

### Task 6: Overview, Synchronization, Settings, and Diagnostics

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-overview-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-sync-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-settings-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-diagnostics-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/overview.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/sync.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/settings.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/diagnostics.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/overview-status.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/sync-runs.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/diagnostics-status.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-operations-ajax.php`

**Interfaces:**
- AJAX operations: `overview_refresh`, `sync_refresh`, `sync_operation`, `settings_save`, `diagnostics_refresh`

- [ ] **Step 1: Write failing copy/status/secret tests**

Assert expanded Russian descriptions exist, internal English statuses are not
rendered as primary labels, every settings control has a label, and secrets or
tokens never appear in HTML or JSON.

- [ ] **Step 2: Refactor views into cards and partials**

Use presenter badges and meaningful links between queue counts, failed runs, and
their destination sections.

- [ ] **Step 3: Add button tooltips**

Explain coupon sync, reference sync, reconcile, stale-lock recovery, token
refresh, and test email in visible help or accessible tooltips.

- [ ] **Step 4: Add AJAX routes using existing actions**

Call `run_operation()` and `save_settings()`; do not duplicate security logic.
Return refreshed partials and Russian notices.

- [ ] **Step 5: Run tests**

Run the new test plus existing admin security/operations tests.

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/pages/class-overview-page.php wp-content/plugins/admitad-coupons/admin/pages/class-sync-page.php wp-content/plugins/admitad-coupons/admin/pages/class-settings-page.php wp-content/plugins/admitad-coupons/admin/pages/class-diagnostics-page.php wp-content/plugins/admitad-coupons/admin/views/overview.php wp-content/plugins/admitad-coupons/admin/views/sync.php wp-content/plugins/admitad-coupons/admin/views/settings.php wp-content/plugins/admitad-coupons/admin/views/diagnostics.php wp-content/plugins/admitad-coupons/admin/views/partials/overview-status.php wp-content/plugins/admitad-coupons/admin/views/partials/sync-runs.php wp-content/plugins/admitad-coupons/admin/views/partials/diagnostics-status.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/tests/php/test-admin-operations-ajax.php
git commit -m "feat: complete Admitad AJAX admin sections"
```

### Task 7: Section-Level Browser Verification

**Files:**
- Modify if required: files from Tasks 1–6 only.

- [ ] **Step 1: Run the complete test suite**

```powershell
powershell -ExecutionPolicy Bypass -File wp-content/plugins/admitad-coupons/tests/run-all.ps1 -SitePath .
```

- [ ] **Step 2: Verify each admin URL directly**

Open all nine URLs under
`edit.php?post_type=promocode&page=admitad-*`. Check descriptions, labels,
tooltips, Russian badges, term paths, loaders, notices, and responsive table
wrappers.

- [ ] **Step 3: Verify AJAX navigation**

On mapping, companies, rules, review, and history:

- search without reload;
- change to 50 and 100 rows;
- paginate;
- use Back and Forward;
- copy the URL into a new tab and confirm equivalent state.

- [ ] **Step 4: Verify mutations**

Create a temporary rule, archive it, restore it to suspended, then remove the
fixture through test cleanup or an explicit development-only cleanup command.
Save a temporary company profile and confirm the hidden stable campaign ID.

- [ ] **Step 5: Inspect browser console**

Confirm no JavaScript errors and no credential/token values in logged network
responses.
