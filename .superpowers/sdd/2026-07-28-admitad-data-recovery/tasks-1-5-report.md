# Admitad data recovery Tasks 1–5 report

Date: 2026-07-29  
Branch: `codex/admitad-admin-ajax`

No production recovery, export, reference synchronization, legacy migration, preview apply, or rollback was executed. All mutating integration fixtures ran only against:

`C:\Users\Inception\Studio\promokodiki\.worktrees\admitad-admin-ajax\.superpowers\sdd\2026-07-28-admitad-admin-sections\disposable-site`

The disposable site's ignored `wp-config.php` had its template `DB_NAME` aligned from `wordpress` to the existing `PROMOKODIKI_ADMITAD_TEST_DATABASE` value `admitad_tests`. The shared test guard was not changed or weakened.

## Commits

- Task 1: `904f9ac feat: require verified Admitad recovery backups`
- Task 2: `6432159 feat: add Admitad recovery preflight`
- Task 3: `405a8f4 feat: migrate legacy Admitad data through AJAX`
- Paused WIP preservation: `363b013 wip: preserve Admitad recovery preflight progress`
- Task 4 verified follow-up: `49b47f5 feat: add resumable Admitad classification preview`
- Task 5: this report is included in `feat: add AJAX classification apply and rollback`

## Minimal RED/GREEN evidence

- Task 1 RED: backup gate class absent. GREEN: missing, empty, changed, expired, and valid backup cases passed.
- Task 2 RED: recovery coordinator absent. GREEN: fixture counts, backup readiness, reference blocker, and read-only legacy counts passed.
- Task 3 RED: durable migration methods absent. GREEN: focused durable method contract passed. This was intentionally a minimal contract test, not a production migration.
- Task 4 RED: resumable preview methods absent before the paused WIP. GREEN on 2026-07-29: 53 stable imported non-trashed IDs, first batch exactly 50, locked and unchanged accounting, no taxonomy mutation, durable resume, and immutable/idempotent snapshot history passed.
- Task 5 RED: `snapshot_progress()` absent. GREEN on 2026-07-29: 52-row apply/rollback fixture used two bounded batches; owner, capability, state, and `confirmed=1` gates passed; one injected row exception was sanitized and did not stop later rows; locks acquired before apply and after apply were preserved; rollback restored exact previous terms and primary ID.

## Fresh focused verification

- `test-reclassification-preview-ajax.php`: PASS (1 test)
- `test-reclassification-apply-ajax.php`: PASS (1 test)
- `test-admin-security.php`: PASS (4 tests)
- `node --check wp-content/plugins/admitad-coupons/assets/js/admin.js`: exit 0
- `git diff --check`: exit 0

The full suite, PHPCS, browser matrix, production database, and main site were deliberately not run or touched. The recovery ledger and `style.css` were not modified.
