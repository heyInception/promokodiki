# Admitad Synchronization and Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace blocking full imports with resumable API synchronization, change detection, safe reconciliation, visibility control, and monitored WP-Cron jobs.

**Architecture:** The API client only returns validated pages. Coordinators persist run/cursor state and schedule bounded continuations. Coupon persistence and reconciliation are separate so incomplete traversals can never deactivate content.

**Tech Stack:** WordPress HTTP API, WP-Cron single events, `$wpdb`, posts/meta/taxonomies, WP-CLI/Studio test harness.

## Global Constraints

- Requires completion of `2026-07-27-admitad-foundation.md`.
- Do not classify categories in this phase; preserve existing assignments.
- Never block with `sleep()`.
- Never deactivate after an incomplete traversal.
- Preserve manual content changes once content lock is set.
- Keep imported records; never auto-delete.
- Use TDD and frequent commits.

---

## File Structure

- `class-api-client.php` — OAuth and validated endpoint pages.
- `class-coupon-normalizer.php`, `class-campaign-normalizer.php` — canonical arrays and hashes.
- `class-sync-run-repository.php` — run/cursor/counter persistence.
- `class-reference-repository.php` — synchronized external category/campaign snapshots.
- `class-job-lock.php` — atomic owner/heartbeat/expiry lock.
- `class-coupon-repository.php` — hash-aware upsert and source-owned fields.
- `class-sync-coordinator.php` — coupon/reference batch state machines.
- `class-reconciler.php` — completed-run miss counts and activation.
- `class-visibility.php` — one active predicate for listings.
- `class-notifier.php` — throttled critical alerts.

### Task 1: Mockable API Client and Normalizers

**Files:**
- Create: `includes/class-api-client.php`
- Create: `includes/class-coupon-normalizer.php`
- Create: `includes/class-campaign-normalizer.php`
- Create: `tests/php/test-api-normalizers.php`
- Modify: `includes/api.php`
- Modify: `admitad-coupons.php`

**Interfaces:**
- `Promokodiki_Admitad_Api_Client::coupon_page(int $limit, int $offset): array|WP_Error`
- `::campaign_page(int $limit, int $offset): array|WP_Error`
- `::coupon_category_page(int $limit, int $offset): array|WP_Error`
- `Promokodiki_Admitad_Coupon_Normalizer::normalize(array $raw): array`
- Normalized keys: `external_id`, `source_status`, `title`, `description`, `campaign`, `categories`, `types`, `payload_hash`.

- [ ] Write a failing test using `pre_http_request` fixtures for success, `401` refresh, `429` retry scheduling data, invalid JSON, and the two supplied coupon shapes.

```php
add_filter(
	'pre_http_request',
	static function ( $preempt, array $args, string $url ) use ( $fixture ) {
		return str_contains( $url, '/coupons/website/' )
			? array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $fixture ), 'headers' => array() )
			: $preempt;
	},
	10,
	3
);
$page = $client->coupon_page( 1, 0 );
$coupon = Promokodiki_Admitad_Coupon_Normalizer::normalize( $page['results'][0] );
Promokodiki_Admitad_Test_Harness::assert_same( '330714', $coupon['external_id'] );
Promokodiki_Admitad_Test_Harness::assert_same( array( 4, 5 ), array_column( $coupon['categories'], 'id' ) );
```
- [ ] Run `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-api-normalizers.php`; verify missing classes/methods fail.
- [ ] Implement URL/query construction with RU constraints, one token refresh on `401`, and return `WP_Error` carrying `retry_after` for `429/5xx` instead of sleeping.

```php
if ( 429 === $status || $status >= 500 ) {
	return new WP_Error(
		'admitad_retryable',
		'Admitad request must be retried.',
		array(
			'status'      => $status,
			'retry_after' => max( 1, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ),
		)
	);
}
```
- [ ] Implement deterministic normalization:

```php
$canonical['payload_hash'] = hash(
	'sha256',
	wp_json_encode( $canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);
```

- [ ] Re-run the test; expected PASS. Commit with `feat: add validated Admitad API pages`.

### Task 2: Runs, Cursors, and Heartbeat Locks

**Files:**
- Create: `includes/class-sync-run-repository.php`
- Create: `includes/class-job-lock.php`
- Create: `tests/php/test-sync-state.php`

**Interfaces:**
- `start(string $type): int`
- `heartbeat(int $run_id, int $cursor, array $counters): void`
- `complete(int $run_id, array $counters): void`
- `fail(int $run_id, WP_Error $error): void`
- `acquire(string $job, string $owner, int $ttl): bool`
- `refresh(string $job, string $owner): bool`
- `release(string $job, string $owner): bool`

- [ ] Test atomic overlap rejection, owner-only release, heartbeat refresh, stale recovery, and sanitized stored errors.

```php
Promokodiki_Admitad_Test_Harness::assert_true( $lock->acquire( 'coupon', 'owner-a', 300 ) );
Promokodiki_Admitad_Test_Harness::assert_true( ! $lock->acquire( 'coupon', 'owner-b', 300 ) );
Promokodiki_Admitad_Test_Harness::assert_true( ! $lock->release( 'coupon', 'owner-b' ) );
Promokodiki_Admitad_Test_Harness::assert_true( $lock->release( 'coupon', 'owner-a' ) );
```
- [ ] Run `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-sync-state.php`; verify RED.
- [ ] Implement repositories using the `sync_run` table and atomic `add_option()` lock records containing owner, acquired time, and heartbeat.

```php
public function acquire( string $job, string $owner, int $ttl ): bool {
	$key = 'promokodiki_admitad_lock_' . sanitize_key( $job );
	$now = time();
	$current = get_option( $key, array() );
	if ( $current && (int) $current['heartbeat'] + $ttl >= $now ) {
		return false;
	}
	delete_option( $key );
	return add_option( $key, array( 'owner' => $owner, 'heartbeat' => $now ), '', false );
}
```
- [ ] Re-run test; expected PASS. Commit with `feat: add resumable Admitad sync state`.

### Task 3: Hash-Aware Coupon Persistence and Content Locks

**Files:**
- Create: `includes/class-coupon-repository.php`
- Create: `includes/class-editorial-locks.php`
- Create: `includes/class-import-context.php`
- Create: `tests/php/test-coupon-repository.php`
- Modify: `includes/importer.php`

**Interfaces:**
- `upsert(array $coupon, int $run_id): array{post_id:int,state:string}`
- States: `created`, `updated`, `unchanged`, `failed`.
- `Promokodiki_Admitad_Editorial_Locks::content_locked(int $post_id): bool`
- `Promokodiki_Admitad_Import_Context::active(): bool`
- `Promokodiki_Admitad_Import_Context::run(callable $callback): mixed`

- [ ] Test create, unchanged hash, source update, action without code, future start date, source-inactive storage, missing affiliate-link ineligibility, and a manually locked title/description.

```php
$created = $repository->upsert( $coupon, 10 );
$unchanged = $repository->upsert( $coupon, 11 );
Promokodiki_Admitad_Test_Harness::assert_same( 'created', $created['state'] );
Promokodiki_Admitad_Test_Harness::assert_same( 'unchanged', $unchanged['state'] );
update_post_meta( $created['post_id'], '_admitad_content_locked', 'yes' );
wp_update_post( array( 'ID' => $created['post_id'], 'post_title' => 'Редакторский заголовок' ) );
$repository->upsert( array_merge( $coupon, array( 'title' => 'API title', 'payload_hash' => 'changed' ) ), 12 );
Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторский заголовок', get_the_title( $created['post_id'] ) );
```
- [ ] Verify RED with `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-coupon-repository.php`.
- [ ] Implement canonical meta, shop lookup by campaign ID, and hash skip. When content is locked, retain title/content but update dates, links, discounts, campaign, status, last-seen run, and hash.

```php
if ( $coupon['payload_hash'] === get_post_meta( $post_id, '_admitad_payload_hash', true ) ) {
	update_post_meta( $post_id, '_admitad_last_seen_run_id', $run_id );
	return array( 'post_id' => $post_id, 'state' => 'unchanged' );
}

if ( ! Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) ) {
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_title'   => $coupon['title'],
			'post_content' => $coupon['description'],
		)
	);
}
```
- [ ] Register `save_post_promocode` lock detection with an importer-context guard so automated saves do not create locks.
- [ ] Re-run test; expected PASS. Commit with `feat: add hash-aware Admitad coupon upserts`.

### Task 4: Resumable Coupon and Reference Coordinators

**Files:**
- Create: `includes/class-sync-coordinator.php`
- Create: `includes/class-reference-repository.php`
- Create: `tests/php/test-sync-coordinator.php`
- Modify: `includes/importer.php`
- Modify: `includes/cli.php`
- Modify: `includes/class-plugin.php`

**Interfaces:**
- `start_coupon_sync(): int|WP_Error`
- `run_coupon_batch(int $run_id, int $offset): array|WP_Error`
- `start_reference_sync(): int|WP_Error`
- `Promokodiki_Admitad_Reference_Repository::sync_coupon_categories(array $items): int`
- `Promokodiki_Admitad_Reference_Repository::sync_campaigns(array $items): int`
- Hooks: `promokodiki_admitad_coupon_batch`, `promokodiki_admitad_reference_batch`.

- [ ] Test three API pages, unchanged counters, delayed retry, incomplete page failure, cursor resume, and completed traversal.

```php
$run_id = $coordinator->start_coupon_sync();
$first = $coordinator->run_coupon_batch( $run_id, 0 );
Promokodiki_Admitad_Test_Harness::assert_same( 200, $first['next_offset'] );
$second = $coordinator->run_coupon_batch( $run_id, 200 );
Promokodiki_Admitad_Test_Harness::assert_same( 400, $second['next_offset'] );
$last = $coordinator->run_coupon_batch( $run_id, 400 );
Promokodiki_Admitad_Test_Harness::assert_same( true, $last['complete'] );
```
- [ ] Verify RED.
- [ ] Implement one bounded page per event, persist cursor/counters, and schedule the next single event. `update_admitad_coupons_data()` becomes a compatibility facade that starts/resumes the coordinator.

```php
$page = $this->api->coupon_page( $batch_size, $offset );
if ( is_wp_error( $page ) ) {
	$this->schedule_retry_or_fail( $run_id, $offset, $page );
	return $page;
}
foreach ( $page['results'] as $raw_coupon ) {
	$this->coupons->upsert( $this->normalizer->normalize( $raw_coupon ), $run_id );
}
$next_offset = $offset + count( $page['results'] );
$this->runs->heartbeat( $run_id, $next_offset, $counters );
```

Reference batches upsert coupon category IDs/names/parents into `category_map` with no site term and campaign IDs/names/category snapshots into `company_profile`; they never create taxonomy terms.
- [ ] Update `studio wp admitad import` to drive the same coordinator to a terminal state without duplicating business logic.
- [ ] Re-run coordinator and repository tests; commit with `refactor: make Admitad sync resumable`.

### Task 5: Reconciliation, Visibility, CRON, and Alerts

**Files:**
- Create: `includes/class-reconciler.php`
- Create: `includes/class-visibility.php`
- Create: `includes/class-notifier.php`
- Create: `tests/php/test-reconciliation-visibility.php`
- Create: `tests/php/test-cron-notifications.php`
- Modify: `includes/importer.php`
- Modify: `includes/class-plugin.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-click-stats.php`

**Interfaces:**
- `Promokodiki_Admitad_Reconciler::after_completed_run(int $run_id): array`
- `Promokodiki_Admitad_Visibility::active_meta_clause(): array`
- `Promokodiki_Admitad_Plugin::schedule(): void`
- Hourly coupon, daily reference, daily reconcile hooks.

- [ ] Test: incomplete run changes nothing; first completed miss increments to one; second marks inactive; later sighting reactivates; inactive posts disappear from archive/filter/popular SQL but singular remains available.

```php
$reconciler->after_completed_run( 20 );
Promokodiki_Admitad_Test_Harness::assert_same( '1', get_post_meta( $post_id, '_admitad_miss_count', true ) );
$reconciler->after_completed_run( 21 );
Promokodiki_Admitad_Test_Harness::assert_same( 'no', get_post_meta( $post_id, '_promocode_is_active', true ) );
update_post_meta( $post_id, '_admitad_last_seen_run_id', 22 );
$reconciler->after_completed_run( 22 );
Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_promocode_is_active', true ) );
```
- [ ] Test CRON schedules are idempotent, delayed beyond two expected intervals triggers an admin notice, two consecutive failures trigger one throttled email, and OAuth failure alerts immediately.

```php
Promokodiki_Admitad_Plugin::schedule();
$first_event = wp_get_scheduled_event( 'promokodiki_admitad_coupon_sync' );
Promokodiki_Admitad_Plugin::schedule();
$second_event = wp_get_scheduled_event( 'promokodiki_admitad_coupon_sync' );
Promokodiki_Admitad_Test_Harness::assert_same( $first_event->timestamp, $second_event->timestamp );
```
- [ ] Verify both tests fail.
- [ ] Implement reconciliation only from completed runs, `_promocode_is_active` state, `pre_get_posts` visibility for non-admin non-singular promocode listings, an inactive notice prepended through `the_content` on singular imported coupons, and the matching inactive exclusion in click-stat SQL.

```php
if ( $miss_count >= Promokodiki_Admitad_Config::get( 'missing_threshold' ) ) {
	update_post_meta( $post_id, '_promocode_is_active', 'no' );
} elseif ( (int) get_post_meta( $post_id, '_admitad_last_seen_run_id', true ) === $run_id ) {
	update_post_meta( $post_id, '_promocode_is_active', 'yes' );
	delete_post_meta( $post_id, '_admitad_miss_count' );
}
```
- [ ] Register schedules from configured intervals; remove legacy duplicate schedule registration and blocking retry code.
- [ ] Re-run all phase tests; commit with `feat: reconcile inactive Admitad coupons safely`.

## Phase Gate

Run all Admitad and AJAX-filter PHP tests, both PHPCS rulesets, and a manual double sync:

```powershell
Get-ChildItem wp-content/plugins/admitad-coupons/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
Get-ChildItem wp-content/plugins/promokodiki-ajax-filter/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
studio wp admitad import
studio wp admitad import
phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist wp-content/plugins/admitad-coupons
```

Expected: all tests PASS; second sync reports unchanged records; no duplicate posts; no incomplete-run deactivation.
