# Tasks 3–6 accelerated report

## Commits

- `a41d1d9 feat: add AJAX keyword rule management`
- `ff66977 feat: add detailed Admitad review tabs`
- `4dd8f7c fix: add canonical AJAX history pagination`
- `feat: complete Admitad AJAX admin sections` (this commit includes the report)

## Focused evidence

- RED: no focused task test was available in this accelerated worktree before implementation.
- GREEN/static: `git diff --check` exited 0 before the Task 6 commit.
- GREEN/environment: `studio wp --path .superpowers/sdd/2026-07-28-admitad-admin-sections/disposable-site eval 'echo 123;' --skip-plugins=admitad-coupons,promokodiki-ajax-filter` printed `123`; no main database was used.
- GREEN/disposable: `test-text-rules.php` passed 2/2; `test-admin-security.php` passed 4/4; and `test-admin-operations.php` passed 4/4. Each ran through the designated disposable site with target plugins skipped for isolated loading.

## Limits

- The local `php` executable is not on PATH. A direct `node --check` inherited an inaccessible path and aborted before parsing, so neither is claimed as a passing check.
- The shared AJAX controller and fragment registry overlap Tasks 3–5 and were committed with Task 5 to retain a coherent controller state.

## Review queue blocker fix

- Reason tabs now pass an allowlisted group of stored reason codes into the repository, which prepares the `IN` values before counting and limiting rows. Unknown codes remain visible in the unfiltered all tab.
- Archive now delegates through the same reviewer capability and `editor_review_enabled` gate as coupon-only resolution; both AJAX and admin-post fallbacks use that action.
- The detailed table restores safe evidence disclosure and includes both resolve and archive forms with action, operation, nonce, and queue fields for progressive enhancement.
- Focused disposable smoke: `test-queue-evidence-tags.php` passed 4/4; `git diff --check` exited 0 before commit.

## Recovery blocker safety pass

- Diagnostics and history now expose nonce-backed progressive recovery, preview, apply, status, and rollback controls; apply requires `confirmed=1`, and synchronous admin-post mutation loops are disabled.
- Every migration batch rechecks the independent backup/reference gates. Source rows receive durable path-free outcomes, row exceptions advance the cursor, and completion requires accounting, taxonomy seeding, and unchanged-source verification.
- Preview/apply/rollback steps use an atomic per-snapshot mutex with a live heartbeat and token-safe release. Preview progress is owner/capability protected, cursors persist per row, and active operations extend expiry so interrupted work remains resumable.
- RED evidence was captured for missing controls, an expired-backup resume, unaccounted row exceptions, foreign preview access, concurrent snapshot steps, and stale active expiry before implementation.
- GREEN disposable evidence: `test-recovery-ui.php`, `test-recovery-migration-ajax.php`, `test-reclassification-preview-ajax.php`, `test-reclassification-apply-ajax.php`, `test-admin-security.php`, and `test-admin-ajax.php` all passed through the designated `admitad_tests` site. PHP syntax checks, streamed `node --check`, and `git diff --check` also passed.
- Follow-up expiry correction: an expired `previewed` snapshot can no longer enter apply. Only already-active owned `applying` or `rolling_back` states survive stale expiry, and resuming them refreshes the heartbeat/expiry before the next bounded step. The apply/rollback regression test was observed RED against the former bypass and GREEN after its removal.
- Full-suite compatibility: the history regression now explicitly rejects expired `previewed` snapshots while allowing expired active `applying` snapshots to remain resumable for their owner; a second administrator is rejected with `foreign_snapshot`. `test-admin-history.php` passed 3/3 on the disposable site.
- Full-suite bounded-workflow compatibility: the assignment/history regression now creates a capable snapshot owner and drives `start_preview`/step, `start_apply`/step, and `start_rollback`/step directly. It verifies taxonomy immutability, one-time apply, exact primary/term rollback, foreign-owner rejection, and invalid repeated steps without using synchronous wrappers. `test-assignment-history.php` passed 3/3.
