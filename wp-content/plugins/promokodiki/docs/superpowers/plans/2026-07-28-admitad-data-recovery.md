# Admitad Data Recovery and Reclassification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Safely restore legacy keywords and company mappings, preview reclassification, apply it through durable AJAX batches, and retain a verified rollback path.

**Architecture:** Extend the existing legacy migration and reclassification services with explicit preflight, durable cursor/progress state, and testable batch methods. Studio CLI creates and registers a verified backup; the WordPress admin performs reference sync, migration, preview, apply, and rollback through AJAX polling.

**Tech Stack:** WordPress Studio CLI, WP-CLI, WordPress AJAX API, existing Admitad repositories, WP-Cron-compatible durable options/tables.

## Global Constraints

- Complete the foundation and admin-section plans first.
- Never delete legacy source tables or imported coupons.
- Require a real, existing, non-empty backup before migration writes.
- Preserve manual category/content locks.
- Do not guess ambiguous companies or conflicting rules.
- Use preview before application and keep rollback data.
- All panel-side long operations use bounded AJAX batches and live-lock protection.
- Do not expose backup paths, credentials, tokens, or raw payloads in HTML/JSON.

---

### Task 1: Verifiable Backup Gate

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-backup-gate.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/cli.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-backup-gate.php`
- Modify: `docs/admitad-automation-operations.md`

**Interfaces:**
- `Promokodiki_Admitad_Backup_Gate::register(string $path): array{created_at:int,size:int,sha256:string}`
- `Promokodiki_Admitad_Backup_Gate::verify(): true|WP_Error`
- New CLI: `studio wp admitad backup-register --path=<absolute-path>`

- [ ] **Step 1: Write failing backup-gate tests**

Use a temporary file outside uploads and assert missing, empty, changed, and
expired files fail. Assert a non-empty unchanged file succeeds. Store only a
normalized path, size, SHA-256, and registration time in a non-autoloaded
option.

- [ ] **Step 2: Run the test and verify missing class/command**

- [ ] **Step 3: Implement the gate**

Verification requires:

```php
is_file( $path )
&& filesize( $path ) === $state['size']
&& hash_file( 'sha256', $path ) === $state['sha256']
&& time() - $state['created_at'] <= DAY_IN_SECONDS
```

Return sanitized error codes, not the path, to the browser layer.

- [ ] **Step 4: Implement and document CLI registration**

Operational command sequence:

```powershell
studio export backups/admitad-before-recovery-2026-07-28.sql --mode db
studio wp admitad backup-register --path="C:\Users\Inception\Studio\promokodiki\backups\admitad-before-recovery-2026-07-28.sql"
```

- [ ] **Step 5: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-backup-gate.php wp-content/plugins/admitad-coupons/includes/cli.php wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/tests/php/test-backup-gate.php docs/admitad-automation-operations.md
git commit -m "feat: require verified Admitad recovery backups"
```

### Task 2: Reference-Sync and Migration Preflight

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-legacy-migration.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/diagnostics.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/partials/recovery-status.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-recovery-preflight.php`

**Interfaces:**
- `Promokodiki_Admitad_Recovery_Coordinator::preflight(): array`
- `start_reference_sync(): int|WP_Error`
- `reference_ready(): true|WP_Error`
- AJAX operations: `recovery_status`, `recovery_reference_start`

- [ ] **Step 1: Write failing preflight tests**

Assert the audited state reports:

```php
array(
	'legacy_keywords'  => 1350,
	'legacy_companies' => 59,
	'new_rules'        => 0,
	'new_profiles'     => 0,
	'backup_ready'     => true,
	'reference_ready'  => false,
)
```

using isolated fixture tables/options, not production counts.

- [ ] **Step 2: Implement preflight**

Return counts, latest reference run, migration cursor, backup readiness, open
recovery issues, and explicit blockers. Do not mutate.

- [ ] **Step 3: Implement reference-start AJAX**

Reuse `Promokodiki_Admitad_Sync_Coordinator::start_reference_sync()`. Progress
polling reads the run repository. Migration remains disabled until a completed
reference run produces campaign/category reference rows.

- [ ] **Step 4: Render recovery status**

Show Russian steps, counts, blockers, latest run, progress, and the next safe
action. Do not display the registered backup path.

- [ ] **Step 5: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php wp-content/plugins/admitad-coupons/includes/class-legacy-migration.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/admin/views/diagnostics.php wp-content/plugins/admitad-coupons/admin/views/partials/recovery-status.php wp-content/plugins/admitad-coupons/tests/php/test-recovery-preflight.php
git commit -m "feat: add Admitad recovery preflight"
```

### Task 3: Durable AJAX Legacy Migration

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-legacy-migration.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-recovery-migration-ajax.php`

**Interfaces:**
- `start_migration(): array|WP_Error`
- `migrate_next_batch(string $owner): array|WP_Error`
- `migration_progress(): array`
- AJAX operations: `recovery_migration_start`, `recovery_migration_step`, `recovery_migration_status`

- [ ] **Step 1: Write failing lock/idempotency tests**

Assert migration cannot start without a verified backup or reference data, a
second owner is rejected while the lock is live, rerunning a completed batch
does not duplicate destination rows, and source counts remain unchanged.

- [ ] **Step 2: Implement durable state**

Store:

```php
array(
	'status'     => 'running',
	'owner'      => $owner,
	'cursor'     => 0,
	'total'      => $analysis['total'],
	'processed'  => 0,
	'created'    => 0,
	'skipped'    => 0,
	'failed'     => 0,
	'started_at' => time(),
	'heartbeat'  => time(),
)
```

in a non-autoloaded option. Each step processes at most 200 rows and refreshes
heartbeat.

- [ ] **Step 3: Preserve and report every outcome**

Keywords become active, suspended, or conflict according to existing migration
rules. Company rows matched to exactly one campaign are migrated; ambiguous or
missing matches are queued. Orphan terms are counted and skipped.

- [ ] **Step 4: Implement AJAX step loop**

The browser requests one batch at a time and renders server progress. On network
failure, it offers Resume; resume reads the durable cursor.

- [ ] **Step 5: Verify migration completion**

Call `Promokodiki_Admitad_Legacy_Migration::verify()` and require:

- cursor equals total;
- source counts unchanged;
- every source row is migrated or accounted for as conflict, suspended, orphan,
  ambiguous, or skipped duplicate;
- taxonomy seed coverage is complete.

- [ ] **Step 6: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php wp-content/plugins/admitad-coupons/includes/class-legacy-migration.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/assets/js/admin.js wp-content/plugins/admitad-coupons/tests/php/test-recovery-migration-ajax.php
git commit -m "feat: migrate legacy Admitad data through AJAX"
```

### Task 4: Bounded Full Reclassification Preview

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-reclassification-service.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/history.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/partials/history-snapshot.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-reclassification-preview-ajax.php`

**Interfaces:**
- `start_preview(array $post_ids = array()): array|WP_Error`
- `preview_next_batch(string $snapshot_id): array|WP_Error`
- `preview_progress(string $snapshot_id): array|WP_Error`
- AJAX operations: `preview_start`, `preview_step`, `preview_status`

- [ ] **Step 1: Write failing preview-resume tests**

Create more than 50 coupon fixtures, including locked and unchanged coupons.
Assert batches are bounded, locked rows are skipped, affected rows are immutable,
and retrying a batch does not duplicate snapshot history.

- [ ] **Step 2: Add durable preview state**

Store cursor and source post IDs separately from immutable history rows. When no
IDs are supplied, query all imported non-trashed coupons in stable post-ID order.

- [ ] **Step 3: Make preview row creation idempotent**

Before recording, check for an existing `snapshot_id + post_id + trigger=preview`
row. Do not change taxonomy during preview.

- [ ] **Step 4: Render a useful preview**

Show affected/unchanged/locked/failed counts, coupon titles and links, full
before/after term paths, confidence, and Russian explanations.

- [ ] **Step 5: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-reclassification-service.php wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php wp-content/plugins/admitad-coupons/admin/class-admin-actions.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/admin/views/history.php wp-content/plugins/admitad-coupons/admin/views/partials/history-snapshot.php wp-content/plugins/admitad-coupons/tests/php/test-reclassification-preview-ajax.php
git commit -m "feat: add resumable Admitad classification preview"
```

### Task 5: AJAX Apply, Progress, and Rollback

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-reclassification-service.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-reclassification-apply-ajax.php`

**Interfaces:**
- `apply_next_batch(string $snapshot_id): array|WP_Error`
- `rollback_next_batch(string $snapshot_id): array|WP_Error`
- `snapshot_progress(string $snapshot_id): array|WP_Error`
- AJAX operations: `snapshot_apply_start`, `snapshot_apply_step`, `snapshot_rollback_start`, `snapshot_rollback_step`, `snapshot_status`

- [ ] **Step 1: Write failing apply/rollback tests**

Assert only the snapshot owner with `manage_admitad_automation` can act, apply
requires `previewed`, rollback requires `applied`, a lock added after preview is
still preserved, and rollback restores exact previous term and primary IDs.

- [ ] **Step 2: Separate batch methods from scheduled wrappers**

Keep WP-Cron compatibility, but move one bounded unit into reusable instance
methods returning counts. Scheduled hooks and AJAX call the same methods.

- [ ] **Step 3: Add explicit confirmation metadata**

AJAX start requests require `confirmed=1` plus the normal nonce. The browser
shows the exact snapshot ID and affected count immediately before sending the
confirmed request.

- [ ] **Step 4: Implement durable progress and partial failures**

Record cursor, processed, changed, skipped, failed, heartbeat, and sanitized
failure summaries. A failed post does not stop later rows.

- [ ] **Step 5: Implement rollback batches**

Use the immutable previous-term fields. Do not rollback a coupon that acquired a
manual category lock after application; report it as skipped.

- [ ] **Step 6: Run tests and commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-reclassification-service.php wp-content/plugins/admitad-coupons/includes/class-recovery-coordinator.php wp-content/plugins/admitad-coupons/admin/class-admin-actions.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/assets/js/admin.js wp-content/plugins/admitad-coupons/tests/php/test-reclassification-apply-ajax.php
git commit -m "feat: add AJAX classification apply and rollback"
```

### Task 6: Execute Recovery on the Local Site

**Files:**
- Create operational artifact outside Git: `backups/admitad-before-recovery-2026-07-28.sql`
- Do not modify production data until Steps 1–6 pass.

- [ ] **Step 1: Run the complete automated suite**

```powershell
powershell -ExecutionPolicy Bypass -File wp-content/plugins/admitad-coupons/tests/run-all.ps1 -SitePath .
```

- [ ] **Step 2: Export and register the backup**

```powershell
studio export backups/admitad-before-recovery-2026-07-28.sql --mode db
studio wp admitad backup-register --path="C:\Users\Inception\Studio\promokodiki\backups\admitad-before-recovery-2026-07-28.sql"
```

Verify the file is non-empty and the admin preflight reports `Резервная копия
проверена`.

- [ ] **Step 3: Start and finish reference synchronization**

Use the Synchronization/Recovery UI. Wait for `completed`; if it fails, stop and
diagnose before migration. Confirm non-zero campaign reference rows and relevant
external category rows.

- [ ] **Step 4: Run AJAX legacy migration**

Monitor until complete. Record exact totals for migrated rules, active,
suspended, conflict, orphan, migrated company, and queued company outcomes.
Verify the legacy source counts remain 1,350 and 59.

- [ ] **Step 5: Create full preview**

Inspect distribution changes and a representative sample. Confirm all manually
locked coupons are in the skipped count and no taxonomy terms were created.

- [ ] **Step 6: Apply the approved snapshot**

Confirm the snapshot ID/count in the browser dialog and monitor to completion.
Do not begin another sync/reclassification concurrently.

- [ ] **Step 7: Verify final data**

Run read-only reports for:

- total coupons;
- category distribution;
- remaining `Прочее`;
- fallback reasons;
- migrated rule statuses;
- company profiles/categories;
- open review reasons;
- locked coupon preservation;
- duplicate coupon IDs.

- [ ] **Step 8: Prove rollback on a controlled fixture**

Create and apply a small test snapshot, rollback it, and verify exact restoration.
Do not rollback the full production snapshot unless the final validation fails.

- [ ] **Step 9: Browser regression**

Verify all nine tabs, AJAX actions, 20/50/100 pagination, canonical URLs,
Back/Forward, loaders, notices, title/edit/public links, and no console errors.

- [ ] **Step 10: Record the recovery report**

Add final counts, snapshot ID, run IDs, unresolved cases, and backup filename to
`docs/admitad-automation-operations.md` without including secrets or the
administrator password.
