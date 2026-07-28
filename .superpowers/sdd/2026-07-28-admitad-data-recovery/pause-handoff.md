# Admitad data recovery — paused handoff

Paused at the user's request on 2026-07-28. No production recovery, export, or reference synchronization was executed; all executed integration tests targeted the designated disposable site.

## Completed evidence

- `904f9ac feat: require verified Admitad recovery backups` — Task 1 backup gate. GREEN evidence: `test-backup-gate.php` passed on the disposable site for missing, empty, changed, expired, and valid backups.
- `6432159 feat: add Admitad recovery preflight` — preflight/status foundation. GREEN evidence: `test-recovery-preflight.php` passed on the disposable site and asserted read-only source counts plus the reference blocker.
- `405a8f4 feat: migrate legacy Admitad data through AJAX` — early durable migration contract foundation. GREEN evidence: `test-recovery-migration-ajax.php` passed only for the exposed bounded/owner-protected method contract.

## WIP commit contents

The WIP commit following this handoff preserves unfinished Task 4 preview work only:

- `wp-content/plugins/admitad-coupons/includes/class-reclassification-service.php`
- `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- `wp-content/plugins/admitad-coupons/tests/php/test-reclassification-preview-ajax.php`

Current RED evidence: `test-reclassification-preview-ajax.php` failed before the WIP implementation because `start_preview`, `preview_next_batch`, and `preview_progress` were absent. The attempted GREEN run was deliberately aborted by the user before it returned a result. Therefore this WIP code is unverified and must not be treated as complete or deployed as the Task 4 feature.

## Next steps

1. Run the preview contract test on the designated disposable site and repair failures.
2. Expand it with the required >50 fixture, locked-row, taxonomy-no-write, immutable/idempotent retry assertions.
3. Review the WIP preview state transitions and history idempotency before creating the Task 4 feature commit.
4. Implement and test Task 5 apply/rollback only after Task 4 is green.

Tests not run after the WIP edits: preview GREEN test, security smoke, lint/diff verification, and all Task 5 tests. The full suite was intentionally not run.
