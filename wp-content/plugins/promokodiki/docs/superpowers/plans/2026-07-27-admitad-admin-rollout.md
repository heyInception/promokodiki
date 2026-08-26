# Admitad Admin, Migration, and Rollout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the complete secure WordPress administration experience, migrate legacy rules safely, validate classification quality, and prepare controlled production rollout.

**Architecture:** One admin router registers nine sections. Page classes consume services/repositories and render escaped templates; mutations go through capability- and nonce-protected action handlers. Migration is versioned, resumable, previewable, and non-destructive.

**Tech Stack:** WordPress Settings/Admin APIs, admin-post actions, WP-CLI, existing repositories/services, WPCS.

## Global Constraints

- Requires all three earlier Admitad plans.
- Administrators manage configuration and bulk actions; editors review individual cases and locks.
- Never render stored secrets.
- Never perform a bulk apply without a stored preview/snapshot.
- Do not remove legacy tables during rollout.
- Preserve the user-owned `style.css` change.

---

## File Structure

- `admin/class-admin-menu.php` — nine-section navigation and capability routing.
- `admin/class-admin-actions.php` — all mutations and redirects.
- `admin/pages/class-*-page.php` — read models for overview, sync, maps, companies, rules, queue, history, settings, diagnostics.
- `admin/views/*.php` — escaped markup only.
- `includes/class-legacy-migration.php` — old-table analysis/copy/conflict status.
- `includes/class-diagnostics.php` — sanitized health snapshot.
- `tests/php/test-admin-*.php`, `test-legacy-migration.php`, `test-end-to-end.php`.

### Task 1: Admin Router, Settings, and Credential Safety

**Files:**
- Create: `admin/class-admin-menu.php`
- Create: `admin/class-admin-actions.php`
- Create: `admin/class-promocode-lock-metabox.php`
- Create: `admin/pages/class-settings-page.php`
- Create: `admin/views/settings.php`
- Create: `tests/php/test-admin-security.php`
- Modify: `admin/token-manager.php`
- Modify: `admitad-coupons.php`

**Interfaces:**
- Slugs: `admitad-overview`, `admitad-sync`, `admitad-category-map`, `admitad-companies`, `admitad-rules`, `admitad-review`, `admitad-history`, `admitad-settings`, `admitad-diagnostics`.
- All admin mutations use `admin-post.php` actions.

- [ ] Test menu capabilities, editor restrictions, nonce rejection, sanitization bounds, constant-mode fields, blank-secret preservation, and absence of secrets/tokens in rendered HTML.

```php
wp_set_current_user( $editor_id );
Promokodiki_Admitad_Test_Harness::assert_true( ! current_user_can( 'manage_admitad_automation' ) );
wp_set_current_user( $administrator_id );
update_option( 'promokodiki_admitad_client_secret', 'test-secret-not-for-output', false );
update_option( 'admitad_access_token', 'test-token-not-for-output', false );
ob_start();
$page->render();
$html = (string) ob_get_clean();
Promokodiki_Admitad_Test_Harness::assert_not_contains( get_option( 'promokodiki_admitad_client_secret' ), $html );
Promokodiki_Admitad_Test_Harness::assert_not_contains( get_option( 'admitad_access_token' ), $html );
```
- [ ] Verify RED.
- [ ] Implement the router and Settings API fields for every configurable setting in the spec; display fixed invariants read-only.

```php
register_setting(
	'promokodiki_admitad',
	Promokodiki_Admitad_Config::OPTION_NAME,
	array(
		'type'              => 'array',
		'sanitize_callback' => array( Promokodiki_Admitad_Config::class, 'sanitize' ),
		'capability'        => 'manage_admitad_automation',
		'default'           => Promokodiki_Admitad_Config::defaults(),
	)
);
```
- [ ] Replace procedural token-manager posts with `admin_post_` handlers. Keep the secret input empty with `autocomplete="new-password"`.

```php
check_admin_referer( 'promokodiki_admitad_save_settings' );
if ( ! current_user_can( 'manage_admitad_automation' ) ) {
	wp_die( esc_html__( 'You are not allowed to manage Admitad automation.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
}
```

- [ ] Add a Promocode editor metabox that shows category/content lock state and nonce-protected “Return categories to automation” / “Return content to automation” actions. Test that editors may unlock one coupon but cannot change global settings.
- [ ] Re-run test; expected PASS. Commit with `feat: secure Admitad automation settings`.

### Task 2: Overview, Synchronization, and Diagnostics

**Files:**
- Create: `admin/pages/class-overview-page.php`
- Create: `admin/pages/class-sync-page.php`
- Create: `admin/pages/class-diagnostics-page.php`
- Create: `admin/views/overview.php`
- Create: `admin/views/sync.php`
- Create: `admin/views/diagnostics.php`
- Create: `includes/class-diagnostics.php`
- Create: `tests/php/test-admin-operations.php`

**Interfaces:**
- `Promokodiki_Admitad_Diagnostics::snapshot(): array`
- Manual actions: start coupon sync, reference sync, reconciliation, recover stale lock, send test email.

- [ ] Test status counters, sanitized errors, delayed CRON, lock state, schema version, safe manual actions, and no destructive links.

```php
$snapshot = Promokodiki_Admitad_Diagnostics::snapshot();
Promokodiki_Admitad_Test_Harness::assert_same( '4', $snapshot['schema_version'] );
Promokodiki_Admitad_Test_Harness::assert_true( isset( $snapshot['cron']['coupon_sync'] ) );
Promokodiki_Admitad_Test_Harness::assert_not_contains( 'Bearer ', wp_json_encode( $snapshot ) );
```
- [ ] Verify RED.
- [ ] Implement read models and nonce/capability-protected handlers. Diagnostics export must redact fields matching token/secret/authorization patterns.

```php
private static function redact( array $data ): array {
	foreach ( $data as $key => &$value ) {
		if ( preg_match( '/token|secret|authorization/i', (string) $key ) ) {
			$value = '[redacted]';
		} elseif ( is_array( $value ) ) {
			$value = self::redact( $value );
		}
	}
	return $data;
}
```
- [ ] Re-run test; expected PASS. Commit with `feat: add Admitad operations dashboard`.

### Task 3: Mapping, Company, Rule, and Review Screens

**Files:**
- Create: `admin/pages/class-category-map-page.php`
- Create: `admin/pages/class-company-page.php`
- Create: `admin/pages/class-rule-page.php`
- Create: `admin/pages/class-review-page.php`
- Create: matching `admin/views/*.php`
- Create: `tests/php/test-admin-mapping.php`
- Replace: `admin/mapping-dashboard.php`
- Replace: `admin/companies-mapping-page.php`

**Interfaces:**
- Category maps always select existing `promocode_category` terms.
- Queue resolution modes: coupon-only lock, external category map, company profile, approve/suspend/correct rule.
- `Promokodiki_Admitad_Admin_Actions::resolve_coupon_only(int $queue_id, array $term_ids): bool|WP_Error`
- `Promokodiki_Admitad_Admin_Actions::create_global_category_map(string $namespace, int $external_id, int $term_id): bool|WP_Error`

- [ ] Test prepared repository calls, term validation, pagination/search, editor coupon-only correction, admin-only global rules, queue dedupe/resolution, and escaped evidence.

```php
wp_set_current_user( $editor_id );
$editor_result = $actions->resolve_coupon_only( $queue_id, array( $term_id ) );
Promokodiki_Admitad_Test_Harness::assert_true( $editor_result );
$global_result = $actions->create_global_category_map( 'coupon', 4, $term_id );
Promokodiki_Admitad_Test_Harness::assert_true( is_wp_error( $global_result ) );
```
- [ ] Verify RED.
- [ ] Implement list tables/forms through service methods; no page/view may issue SQL.

```php
$term = get_term( absint( $_POST['site_term_id'] ?? 0 ), 'promocode_category' );
if ( ! $term || is_wp_error( $term ) ) {
	wp_safe_redirect( add_query_arg( 'admitad_error', 'invalid_term', wp_get_referer() ) );
	exit;
}
$this->category_maps->save( $namespace, $external_id, (int) $term->term_id, $weight );
```
- [ ] Remove legacy procedural callbacks only after equivalent routes are tested and registered.
- [ ] Re-run test; expected PASS. Commit with `refactor: replace legacy Admitad mapping screens`.

### Task 4: History, Preview, Apply, and Rollback UI

**Files:**
- Create: `admin/pages/class-history-page.php`
- Create: `admin/views/history.php`
- Create: `includes/class-validation-service.php`
- Create: `tests/php/test-admin-history.php`
- Modify: `admin/class-admin-actions.php`

**Interfaces:**
- Preview displays affected count and old/new primary/category sets.
- Apply/rollback accept stored snapshot IDs only.
- `Promokodiki_Admitad_Validation_Service::create_sample(int $size): string`
- `::record_review(string $sample_id, int $post_id, array $expected_terms): void`
- `::report(string $sample_id): array`

- [ ] Test dry-run immutability, preview ownership/expiry, locked-record exclusion, resumable apply, exact rollback, and repeated-submit idempotency.

```php
$before = wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) );
$snapshot = $service->preview( array( $post_id, $locked_post_id ) );
$after_preview = wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) );
Promokodiki_Admitad_Test_Harness::assert_same( $before, $after_preview );
Promokodiki_Admitad_Test_Harness::assert_true( ! in_array( $locked_post_id, $snapshot['post_ids'], true ) );
```
- [ ] Verify RED.
- [ ] Implement history filters and explicit confirmation forms for apply/rollback.

```php
$snapshot_id = sanitize_key( wp_unslash( $_POST['snapshot_id'] ?? '' ) );
$snapshot = $this->reclassification->get_snapshot( $snapshot_id );
if ( ! $snapshot || 'previewed' !== $snapshot['status'] ) {
	wp_die( esc_html__( 'Invalid or expired classification snapshot.', 'promokodiki-admitad' ), '', array( 'response' => 409 ) );
}
$this->reclassification->schedule_apply( $snapshot_id );
```

- [ ] Add a validation-sample tab that creates a 150-coupon stratified sample across confidence levels and campaigns, records reviewer-expected terms, and reports high-confidence accuracy, non-`other` coverage, lock preservation, and out-of-profile assignments. Test the exact 95% / 85% / 100% / 0 acceptance calculations.
- [ ] Re-run test; expected PASS. Commit with `feat: add Admitad classification preview and rollback`.

### Task 5: Non-Destructive Legacy Migration and Taxonomy Seeding

**Files:**
- Create: `includes/class-legacy-migration.php`
- Create: `tests/php/test-legacy-migration.php`
- Modify: `includes/class-activator.php`
- Modify: `includes/cli.php`

**Interfaces:**
- `analyze(): array`
- `migrate_batch(int $offset, int $limit): array`
- `verify(): array`
- CLI: `studio wp admitad automation-migrate --dry-run|--execute --backup=<path> --yes`

- [ ] Test copies 1,350 keyword rows and 59 company rows without deleting originals, preserves zero or more category maps, suspends normalized conflicts/unsafe fragments, and seeds every current taxonomy term.

```php
$before = $migration->analyze();
$migration->migrate_batch( 0, 2000 );
$after = $migration->verify();
Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_keywords'], $after['migrated_keywords'] );
Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_companies'], $after['migrated_companies'] );
Promokodiki_Admitad_Test_Harness::assert_same( $before['legacy_keywords'], $after['legacy_keywords_remaining'] );
Promokodiki_Admitad_Test_Harness::assert_same( 0, $after['orphan_term_references'] );
```
- [ ] Verify RED.
- [ ] Require an existing non-empty backup path and `--yes` for execution. Store migration version/cursor/report; make reruns idempotent.

```php
$backup = (string) ( $assoc_args['backup'] ?? '' );
if ( ! is_file( $backup ) || 0 === filesize( $backup ) ) {
	return new WP_Error( 'backup_required', 'A non-empty existing backup is required.' );
}
if ( empty( $assoc_args['yes'] ) ) {
	return new WP_Error( 'confirmation_required', '--yes is required.' );
}
```
- [ ] Implement verification counts and orphan checks; never mutate taxonomy terms.
- [ ] Re-run test; expected PASS. Commit with `feat: migrate legacy Admitad mapping safely`.

### Task 6: End-to-End Acceptance, CI, and Operator Documentation

**Files:**
- Create: `tests/php/test-end-to-end.php`
- Create: `tests/fixtures/class-fixtures.php`
- Create: `includes/class-import-pipeline.php`
- Create: `includes/class-retention.php`
- Create: `docs/admitad-automation-operations.md`
- Modify: `.github/workflows/ci.yml`
- Modify: `README.md`

**Interfaces:**
- One command runs all plugin tests.
- `Promokodiki_Admitad_Import_Pipeline::process(array $raw_coupon, int $run_id): array|WP_Error`
- Operations doc covers backup, dry-run, sample review, apply, rollback, CRON, and alerts.

- [ ] Add an end-to-end fixture set covering: empty-description Lacoste with two categories; described travel coupon; low-confidence fallback; conflicting rule; content/category locks; inactive/reactivated coupon; action without code.

```php
$lacoste = $fixtures->coupon( 'lacoste-empty-description' );
$result = $pipeline->process( $lacoste, $run_id );
Promokodiki_Admitad_Test_Harness::assert_same( '', $lacoste['description'] );
$expected_terms = array( $shoes_term_id, $clothing_term_id );
sort( $expected_terms );
$actual_terms = wp_get_object_terms( $result['post_id'], 'promocode_category', array( 'fields' => 'ids' ) );
sort( $actual_terms );
Promokodiki_Admitad_Test_Harness::assert_same(
	$expected_terms,
	$actual_terms
);
```
- [ ] Verify the test fails before final wiring.
- [ ] Wire the complete import → normalize → classify → assign → reconcile path and make the fixture test pass.
- [ ] Add a daily retention service that deletes detailed sync/history rows older than the configured number of days while preserving unresolved queue entries and stored rollback snapshots:

```php
$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$sync_table} WHERE completed_at < %s AND status IN ('completed','failed')",
		$cutoff
	)
);
```

Add a test proving 91-day completed details are removed with the 90-day default while unresolved queue items and active snapshots remain.
- [ ] Extend CI to run syntax, security scan, both WPCS rulesets, and test files supported in CI; document Studio-only integration commands separately.
- [ ] Document optional system CRON using `studio wp cron event run promokodiki_admitad_coupon_sync` locally and the equivalent host `wp cron event run` command in production; WP-Cron remains the supported baseline.
- [ ] Run the 150-coupon validation workflow. Record only aggregate accuracy/coverage in the migration report; do not commit production payloads.
- [ ] Verify acceptance: ≥95% high-confidence accuracy, ≥85% more specific than `other`, 100% lock preservation, zero out-of-profile assignments.
- [ ] Commit with `test: verify Admitad automation rollout`.

## Final Verification

```powershell
Get-ChildItem wp-content/plugins/admitad-coupons/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
Get-ChildItem wp-content/plugins/promokodiki-ajax-filter/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
Get-ChildItem wp-content/plugins/admitad-coupons,wp-content/plugins/promokodiki-ajax-filter -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist wp-content/plugins/admitad-coupons
phpcs --standard=wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist wp-content/plugins/promokodiki-ajax-filter
studio wp admitad automation-migrate --dry-run
studio wp admitad classify --dry-run
studio wp cron event list
```

Expected: all tests PASS; no syntax/WPCS errors; migration and classification dry-runs report without mutations; scheduled jobs are present once each.

Do not execute migration on production until a backup exists, the 150-coupon acceptance report passes, and an administrator approves the stored preview.
