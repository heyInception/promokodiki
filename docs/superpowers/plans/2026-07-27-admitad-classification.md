# Admitad Classification and Rule Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement explainable category mapping from stable Admitad IDs, campaign profiles, and safe phrase rules, including locks, queueing, history, dry-run, rollback, and controlled tags.

**Architecture:** Repositories supply normalized signals; the classifier is deterministic and side-effect free. A separate assignment service applies results, writes history, and respects locks. Rule evidence and review queueing are explicit.

**Tech Stack:** PHP 8.1 classes, WordPress taxonomy/meta APIs, custom mapping/rule/history tables, WP-CLI tests.

## Global Constraints

- Requires the foundation and synchronization plans.
- Never mutate the site's category hierarchy.
- Manual locks are absolute.
- Maximum three thematic categories: one primary and up to two secondary.
- No external AI and no naive arbitrary stemming.
- One weak word cannot produce high confidence.
- Candidate activation requires 5 observations, 2 campaigns, 0 contradictions.

---

### Task 1: Unicode Normalizer and Rule Repository

**Files:**
- Create: `includes/class-text-normalizer.php`
- Create: `includes/class-rule-repository.php`
- Create: `tests/php/test-text-rules.php`
- Modify: `admitad-coupons.php`

**Interfaces:**
- `normalize(string $text): string`
- `tokens(string $text): array`
- `match(string $normalized_text): array`
- `set_status(int $rule_id, string $status): bool`
- `find_status(string $phrase, int $term_id): string`
- Rule statuses: `active`, `candidate`, `suspended`, `conflict`.

- [ ] Test lowercase, `ё/е`, punctuation/hyphens, Unicode boundaries, full phrases, whole tokens, explicit prefix mode, and rejection of `тур` inside unrelated words.

```php
Promokodiki_Admitad_Test_Harness::assert_same(
	'скидка на телефоны',
	Promokodiki_Admitad_Text_Normalizer::normalize( '  СКИДКА—на телефоны! ' )
);
Promokodiki_Admitad_Test_Harness::assert_same( array(), $rules->match( 'культурное событие' ) );
```
- [ ] Verify RED.
- [ ] Implement deterministic normalization and repository matching; load only active indexed rules, cache per request, and invalidate cache on rule changes.

```php
public static function normalize( string $text ): string {
	$text = mb_strtolower( str_replace( 'ё', 'е', $text ), 'UTF-8' );
	$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
	return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
}
```
- [ ] Re-run test; expected PASS. Commit with `feat: add safe Admitad phrase rules`.

### Task 2: Stable Category Maps and Company Profiles

**Files:**
- Create: `includes/class-category-map-repository.php`
- Create: `includes/class-company-profile-repository.php`
- Create: `tests/php/test-structured-signals.php`

**Interfaces:**
- `terms_for_external(string $namespace, int $external_id): array`
- `save(string $namespace, int $external_id, int $term_id, int $weight): int`
- `profile_for_campaign(int $campaign_id): array{default_term_id:int,allowed_term_ids:array,weight:int}|null`

- [ ] Test coupon/campaign ID namespace separation, multiple mapped terms, missing term rejection, marketplace profile without default, and allowed-set enforcement.

```php
$maps->save( 'coupon', 4, $shoe_term_id, 100 );
$maps->save( 'campaign', 4, $other_term_id, 60 );
Promokodiki_Admitad_Test_Harness::assert_same( array( $shoe_term_id ), $maps->terms_for_external( 'coupon', 4 ) );
Promokodiki_Admitad_Test_Harness::assert_same( array( $other_term_id ), $maps->terms_for_external( 'campaign', 4 ) );
```
- [ ] Verify RED.
- [ ] Implement prepared repository queries and validate every returned term against `promocode_category`.

```php
$rows = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT site_term_id FROM {$table} WHERE source_namespace = %s AND external_category_id = %d AND status = 'active'",
		$namespace,
		$external_id
	)
);
return array_values( array_filter( array_map( 'absint', $rows ), array( $this, 'is_valid_term' ) ) );
```
- [ ] Re-run test; expected PASS. Commit with `feat: add structured Admitad category signals`.

### Task 3: Deterministic Classifier

**Files:**
- Create: `includes/class-classification-result.php`
- Create: `includes/class-classifier.php`
- Create: `tests/php/test-classifier.php`

**Interfaces:**
- `classify(array $coupon, array $context): Promokodiki_Admitad_Classification_Result`
- `Promokodiki_Admitad_Classification_Result::locked(array $term_ids, int $primary_term_id): self`
- Result getters: `primary_term_id()`, `term_ids()`, `confidence()`, `explanation()`.

- [ ] Write table-driven tests for manual lock, coupon ID mapping, campaign/profile agreement, title versus description weights, conflicting strong signals, parent fallback, `other`, deterministic ties, and max-three cap.

```php
$cases = array(
	'manual lock' => array( 'context' => array( 'locked_term_ids' => array( $locked ) ), 'primary' => $locked, 'confidence' => 'locked' ),
	'structured coupon' => array( 'coupon_categories' => array( 4, 5 ), 'primary' => $shoe, 'confidence' => 'high' ),
	'no signals' => array( 'primary' => $other, 'confidence' => 'low' ),
);
foreach ( $cases as $name => $case ) {
	$result = $classifier->classify( $fixture_factory( $case ), $case['context'] ?? array() );
	Promokodiki_Admitad_Test_Harness::assert_same( $case['primary'], $result->primary_term_id(), $name );
	Promokodiki_Admitad_Test_Harness::assert_same( $case['confidence'], $result->confidence(), $name );
}
```
- [ ] Verify RED.
- [ ] Implement signal classes in fixed order. Primary tie-break: signal strength, taxonomy depth, stable term ID. Do not redundantly add a parent when its child is selected.

```php
if ( ! empty( $context['locked_term_ids'] ) ) {
	return Promokodiki_Admitad_Classification_Result::locked( $context['locked_term_ids'], $context['locked_primary_id'] );
}
$signals = array_merge(
	$this->coupon_category_signals( $coupon ),
	$this->campaign_signals( $coupon, $context ),
	$this->text_signals( $coupon )
);
return $this->resolve( $signals, (int) Promokodiki_Admitad_Config::get( 'max_categories' ) );
```
- [ ] Record rejected conflicts and classifier/rule versions in the result explanation.
- [ ] Re-run test; expected PASS. Commit with `feat: classify Admitad coupons deterministically`.

### Task 4: Assignment, Locks, History, Dry-Run, and Rollback

**Files:**
- Create: `includes/class-classification-history-repository.php`
- Create: `includes/class-assignment-service.php`
- Create: `includes/class-reclassification-service.php`
- Create: `tests/php/test-assignment-history.php`
- Modify: `includes/class-coupon-repository.php`
- Modify: `includes/cli.php`

**Interfaces:**
- `assign(int $post_id, Promokodiki_Admitad_Classification_Result $result, string $trigger): bool`
- `preview(array $post_ids): array`
- `apply_preview(string $snapshot_id): int`
- `rollback(string $snapshot_id): int`
- `get_snapshot(string $snapshot_id): array|null`
- `schedule_apply(string $snapshot_id): void`

- [ ] Test category lock preservation, primary meta, complete explanation, history before/after terms, affected-only preview, background-sized apply batches, and exact rollback.

```php
$snapshot = $service->preview( array( $post_id ) );
Promokodiki_Admitad_Test_Harness::assert_same( $old_terms, wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
$service->apply_preview( $snapshot['id'] );
$service->rollback( $snapshot['id'] );
Promokodiki_Admitad_Test_Harness::assert_same( $old_terms, wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
```
- [ ] Verify RED.
- [ ] Implement category-lock detection on editorial term changes using the same importer-context guard as content locks.

```php
if ( ! Promokodiki_Admitad_Import_Context::active() ) {
	update_post_meta( $post_id, '_admitad_category_locked', 'yes' );
	update_post_meta( $post_id, '_admitad_locked_term_ids', array_map( 'absint', $term_ids ) );
}
```
- [ ] Add CLI commands `studio wp admitad classify --dry-run`, `studio wp admitad classify --apply=sample-snapshot-id`, and `studio wp admitad rollback sample-snapshot-id`.
- [ ] Re-run test; expected PASS. Commit with `feat: add safe Admitad reclassification history`.

### Task 5: Review Queue, Evidence, Taxonomy Seeds, and Tags

**Files:**
- Create: `includes/class-review-queue-repository.php`
- Create: `includes/class-rule-evidence-service.php`
- Create: `includes/class-taxonomy-rule-seeder.php`
- Create: `includes/class-tag-manager.php`
- Create: `tests/php/test-queue-evidence-tags.php`
- Modify: `includes/class-assignment-service.php`

**Interfaces:**
- `enqueue(string $type, string $entity_id, string $reason, array $evidence): int`
- `count_unresolved(string $reason): int`
- `observe(string $phrase, int $term_id, int $campaign_id, bool $contradiction): void`
- `seed_all_terms(): array`
- `sync(int $post_id, array $coupon): void`

- [ ] Test unresolved deduplication, conflict reasons, low-confidence queueing, exact-name seed for every taxonomy term, migrated ambiguous rule suspension, 5/2/0 auto-activation, and controlled tag toggle behavior.

```php
$first = $queue->enqueue( 'coupon', '330714', 'low_confidence', $evidence );
$second = $queue->enqueue( 'coupon', '330714', 'low_confidence', $evidence );
Promokodiki_Admitad_Test_Harness::assert_same( $first, $second );
foreach ( array( 1001, 1002, 1001, 1002, 1001 ) as $campaign_id ) {
	$evidence_service->observe( 'беговые кроссовки', $shoe_term_id, $campaign_id, false );
}
Promokodiki_Admitad_Test_Harness::assert_same( 'active', $rules->find_status( 'беговые кроссовки', $shoe_term_id ) );
```
- [ ] Verify RED.
- [ ] Implement sanitized compact evidence, exact taxonomy-name active seeds, ambiguous synonyms as candidates, and structured tags for discount/free delivery/gift/customer/exclusive/personal.

```php
if ( $evidence_count >= 5 && $campaign_count >= 2 && 0 === $contradiction_count ) {
	$this->rules->set_status( $rule_id, 'active' );
}
```
- [ ] Disabling auto-tags must stop future changes without deleting existing relationships.
- [ ] Re-run test; expected PASS. Commit with `feat: add Admitad review learning and tags`.

### Task 6: Suspected Duplicate Detection

**Files:**
- Create: `includes/class-duplicate-detector.php`
- Create: `tests/php/test-duplicate-detector.php`
- Modify: `includes/class-coupon-repository.php`
- Modify: `includes/class-review-queue-repository.php`

**Interfaces:**
- `Promokodiki_Admitad_Duplicate_Detector::find(array $coupon): array`
- Queue reason: `suspected_duplicate`

- [ ] Test that the same Admitad coupon ID is an update, while a different ID with matching campaign, promo code, overlapping dates, and normalized title is queued but remains a separate post.

```php
$matches = $detector->find( $new_coupon );
Promokodiki_Admitad_Test_Harness::assert_same( array( $existing_post_id ), $matches );
$created = $coupons->upsert( $new_coupon, 30 );
Promokodiki_Admitad_Test_Harness::assert_true( $created['post_id'] !== $existing_post_id );
Promokodiki_Admitad_Test_Harness::assert_same( 1, $queue->count_unresolved( 'suspected_duplicate' ) );
```
- [ ] Verify RED with `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-duplicate-detector.php`.
- [ ] Implement bounded candidate lookup by campaign ID and promo code:

```php
$candidates = get_posts(
	array(
		'post_type'      => 'promocode',
		'post_status'    => 'any',
		'posts_per_page' => 20,
		'meta_query'     => array(
			array( 'key' => 'campaign_id', 'value' => (string) $coupon['campaign']['id'] ),
			array( 'key' => '_promocode_code', 'value' => $coupon['promocode'] ),
		),
	)
);
```

Score normalized title and date overlap, enqueue evidence, and never merge/delete/update the candidate.

- [ ] Re-run the duplicate and coupon repository tests; expected PASS.
- [ ] Commit with `feat: flag suspected Admitad coupon duplicates`.

## Phase Gate

Run every Admitad test, then produce a real dry-run report:

```powershell
Get-ChildItem wp-content/plugins/admitad-coupons/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
studio wp admitad classify --dry-run
```

Expected: tests PASS; dry-run changes no taxonomy relationships; every proposal contains confidence and explanation; locked coupons remain unchanged.
