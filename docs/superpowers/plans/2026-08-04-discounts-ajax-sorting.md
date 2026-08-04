# Discounts AJAX Sorting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Discounts page's three pre-rendered panels with one server-rendered, AJAX-sortable and paginated promocode feed, while removing confirmed inline JavaScript duplication from Footer.

**Architecture:** Extend `promokodiki-ajax-filter` with a `discounts` context, discount-specific sort policy, and sort-link rendering while reusing its existing results endpoint and client request lifecycle. Keep a six-card GET fallback in the theme when the plugin is unavailable, and move only still-required Footer behavior into enqueued files before deleting all inline scripts.

**Tech Stack:** WordPress 6.4+, PHP 8.1, `WP_Query`, WordPress AJAX/nonces, vanilla JavaScript, Node's built-in test runner, WP-CLI test harness, WPCS/PHPCS.

## Global Constraints

- Do not use the `wordpress-pro` skill.
- The initial result and each load-more page contain exactly 6 cards.
- Supported Discounts sorts are `popular`, `newest`, and `discussed`; default is `popular`.
- `popular` uses the rolling 7-day click table and falls back to lifetime `_promocode_used_count` only when the rolling window is empty.
- `discussed` uses all-time likes plus dislikes and lazily persists `_promocode_votes_total` on future votes.
- Exclude `_promocode_is_active = no` and expired dated offers; retain missing/empty expiry dates.
- Preserve the user's existing unstaged `wp-content/themes/promokodiki/style.css` change and do not overwrite unrelated work.
- Do not remove legacy handlers outside Footer without a separate audit.

---

### Task 1: Discounts request state and context

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-state.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php`

**Interfaces:**
- Consumes: `Promokodiki_Filter_Settings::defaults()` and the existing `from_request(array $request, array $settings, string $context_type): array` contract.
- Produces: `Promokodiki_Filter_Context::resolve('discounts', 0)` and normalized Discounts state with one of `popular|newest|discussed`.

- [ ] **Step 1: Add failing state tests**

Add assertions equivalent to:

```php
$discounts = Promokodiki_Filter_State::from_request( array(), $settings, 'discounts' );
Promokodiki_Filter_Test_Harness::assert_same( 'popular', $discounts['sort'] );

$invalid = Promokodiki_Filter_State::from_request(
	array( 'paf_sort' => 'oldest', 'paf_category' => '9', 'paf_brand' => '7' ),
	$settings,
	'discounts'
);
Promokodiki_Filter_Test_Harness::assert_same( 'popular', $invalid['sort'] );
Promokodiki_Filter_Test_Harness::assert_same( 0, $invalid['category_id'] );
Promokodiki_Filter_Test_Harness::assert_same( 0, $invalid['brand_id'] );
```

- [ ] **Step 2: Add a failing context test**

Assert that `resolve('discounts', 0)` returns a context with `type=discounts`, `object_id=0`, and empty category/brand option and allow lists.

- [ ] **Step 3: Run the focused tests and verify RED**

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php
```

Expected: failures because `discounts` is not accepted and inherits the configured default sort.

- [ ] **Step 4: Implement the minimal state and context policy**

In `Promokodiki_Filter_State::from_request()`, branch on `discounts`:

```php
if ( 'discounts' === $context_type ) {
	$category_id  = 0;
	$brand_id     = 0;
	$popular      = false;
	$allowed      = array( 'popular', 'newest', 'discussed' );
	$requested    = sanitize_key( (string) ( $request['paf_sort'] ?? 'popular' ) );
	$sort         = in_array( $requested, $allowed, true ) ? $requested : 'popular';
}
```

Add `discounts_context(): array` and route `resolve('discounts', 0)` to it without term queries.

- [ ] **Step 5: Run the focused tests and verify GREEN**

Run both commands from Step 3; expected: PASS.

- [ ] **Step 6: Commit the state/context increment**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/includes/class-state.php wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php
git commit -m "feat: add discounts filter context"
```

---

### Task 2: Discounts eligibility and ranking queries

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-query-service.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php`

**Interfaces:**
- Consumes: normalized state from Task 1 and `Promokodiki_Filter_Click_Stats::ranked_ids()` / `ranked_count()`.
- Produces: `Promokodiki_Filter_Query_Service::run()` results honoring Discounts eligibility, sort, offsets, and cold-start fallback.

- [ ] **Step 1: Add failing eligibility and pagination fixtures**

Create published promocodes covering active dated, undated, expired, and `_promocode_is_active=no` cases. Run Discounts/Newest with six-card settings and assert:

```php
Promokodiki_Filter_Test_Harness::assert_true( in_array( $undated_id, $ids, true ) );
Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $expired_id, $ids, true ) );
Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $inactive_id, $ids, true ) );
Promokodiki_Filter_Test_Harness::assert_same( 6, count( $page_one['posts'] ) );
Promokodiki_Filter_Test_Harness::assert_same( true, $page_one['has_more'] );
```

Also assert page two starts after the first six with no duplicate IDs.

- [ ] **Step 2: Add failing Discussed ordering tests**

Create one post with `_promocode_votes_total`, one legacy post with only likes/dislikes, and ties with different dates/IDs. Assert order is total reactions descending, then date descending, then ID descending.

- [ ] **Step 3: Add failing Popular cold-start tests**

Assert that tracked seven-day clicks win when any tracked IDs exist, and that lifetime `_promocode_used_count` order is used only when `ranked_count(7, false)` is zero.

- [ ] **Step 4: Run focused query tests and verify RED**

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php
```

Expected: Discounts-specific eligibility, Discussed, and cold-start assertions fail.

- [ ] **Step 5: Refactor `run()` into explicit standard and weekly paths**

Introduce private methods with exact contracts:

```php
private static function run_standard( array $state, array $context, array $settings, int $page, int $limit, int $offset ): array;
private static function run_weekly( array $state, array $settings, int $page, int $limit, int $offset ): array;
private static function clause_filter( string $sort, bool $show_expired, bool $active_only ): Closure;
```

For `discounts + popular`, call `run_weekly`; if its `total` is zero, call `run_standard` with sort `popular`. Pass `active_only=true` for Discounts so the SQL excludes an explicit `no` activity flag.

- [ ] **Step 6: Add the Discussed SQL expression**

Use correlated postmeta expressions that preserve legacy data:

```php
$votes = "COALESCE(
	(SELECT MAX(paf_votes.meta_value + 0) FROM {$wpdb->postmeta} paf_votes
	 WHERE paf_votes.post_id = {$wpdb->posts}.ID AND paf_votes.meta_key = '_promocode_votes_total'),
	COALESCE((SELECT MAX(paf_likes.meta_value + 0) FROM {$wpdb->postmeta} paf_likes
	 WHERE paf_likes.post_id = {$wpdb->posts}.ID AND paf_likes.meta_key = '_promocode_likes'), 0)
	+ COALESCE((SELECT MAX(paf_dislikes.meta_value + 0) FROM {$wpdb->postmeta} paf_dislikes
	 WHERE paf_dislikes.post_id = {$wpdb->posts}.ID AND paf_dislikes.meta_key = '_promocode_dislikes'), 0)
)";
```

Order by `$votes DESC`, `post_date DESC`, and `ID DESC`.

- [ ] **Step 7: Run focused tests and verify GREEN**

Run both commands from Step 4; expected: PASS.

- [ ] **Step 8: Commit ranking and eligibility**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/includes/class-query-service.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php
git commit -m "feat: rank active discounts"
```

---

### Task 3: Lazy reaction-total persistence

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-promo-interactions.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php`

**Interfaces:**
- Consumes: current vote switching behavior and `_promocode_likes` / `_promocode_dislikes`.
- Produces: `_promocode_votes_total` and a response field `total` after every accepted vote.

- [ ] **Step 1: Add failing tests for first vote and changed vote**

After a like, assert `likes=1`, `dislikes=0`, `total=1`, and total meta is `1`. After switching the same visitor to dislike, assert `likes=0`, `dislikes=1`, `total=1`, and total meta remains `1`.

- [ ] **Step 2: Add a failing legacy-counter test**

Seed likes `4`, dislikes `2`, omit total meta, vote with a new visitor, and assert total is recomputed from resulting counters rather than initialized at zero.

- [ ] **Step 3: Run and verify RED**

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php
```

Expected: missing response key/meta assertions fail.

- [ ] **Step 4: Persist the total after counter updates**

At the end of `vote()` calculate and save:

```php
$likes    = max( 0, (int) get_post_meta( $post_id, '_promocode_likes', true ) );
$dislikes = max( 0, (int) get_post_meta( $post_id, '_promocode_dislikes', true ) );
$total    = $likes + $dislikes;
update_post_meta( $post_id, '_promocode_votes_total', $total );
return compact( 'likes', 'dislikes', 'total', 'reaction' );
```

- [ ] **Step 5: Run and verify GREEN**

Run Step 3; expected: PASS.

- [ ] **Step 6: Commit reaction totals**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/includes/class-promo-interactions.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php
git commit -m "feat: persist promocode reaction totals"
```

---

### Task 4: Server-rendered Discounts feed, GET fallback, and canonical URL

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/templates/discounts-sort.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php`
- Create: `wp-content/themes/promokodiki/inc/discounts.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Modify: `wp-content/themes/promokodiki/template-parts/partials/promocodes-discounts.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: Task 1 context/state and Task 2 query results.
- Produces: `Promokodiki_Filter_Renderer::render('discounts', 0)`, `promokodiki_discounts_fallback_query(string $sort): WP_Query`, sort links, one result region, load-more button, and base-page canonical filtering.

- [ ] **Step 1: Add failing renderer assertions**

Assert Discounts HTML contains one `data-filter-results`, one `data-filter-more`, three `data-filter-sort` links with `paf_sort=popular|newest|discussed`, a `Сортировать:` label, selected-state semantics, and exactly six `.promocodes__item` cards when more fixtures exist.

- [ ] **Step 2: Add failing AJAX contract assertions**

Call `Promokodiki_Filter_Ajax_Controller::build_results_payload()` with a verified `discounts` context nonce and assert sanitized sort, six-card page one, correct page two, and `has_more` behavior.

- [ ] **Step 3: Add failing fallback and canonical behavior assertions**

Create seven active fallback fixtures, call `promokodiki_discounts_fallback_query('newest')`, and assert it returns exactly six newest posts while excluding inactive/expired posts and retaining an undated post. Repeat with hand-derived lifetime-usage and reaction totals to verify `popular` and `discussed` ordering.

Create a page using `_wp_page_template=page-discounts.php` and assert the canonical filter removes `paf_sort` by returning `get_permalink($post_id)`. Render the partial with the plugin loaded and assert its observable output has exactly one filter root and no duplicate tab panels.

- [ ] **Step 4: Run renderer, AJAX, and theme tests; verify RED**

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
```

- [ ] **Step 5: Implement the focused Discounts renderer**

Add a private renderer branch that creates the same root data attributes as the current generic filter, renders `templates/discounts-sort.php`, then renders loader, one cards region, one load-more button, and one status region. Sort URLs use `add_query_arg('paf_sort', $key, get_permalink())` and carry `aria-current="true"` only for the selected sort.

- [ ] **Step 6: Implement the theme fallback**

Replace three duplicated `WP_Query` panels with:

```php
if ( function_exists( 'promokodiki_filter_render' ) ) {
	promokodiki_filter_render( array( 'context' => 'discounts' ) );
} else {
	$allowed_sort = array( 'popular', 'newest', 'discussed' );
	$sort = isset( $_GET['paf_sort'] ) ? sanitize_key( wp_unslash( $_GET['paf_sort'] ) ) : 'popular'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public sorting.
	$sort = in_array( $sort, $allowed_sort, true ) ? $sort : 'popular';
	$query = promokodiki_discounts_fallback_query( $sort );
}
```

In `inc/discounts.php`, register a temporary `posts_clauses` callback around one `WP_Query` with `posts_per_page => 6`. The callback must use `current_time('Y-m-d')`, exclude an explicit `_promocode_is_active=no`, retain missing/empty expiry dates, and select one deterministic order expression:

```php
switch ( $sort ) {
	case 'newest':
		$order = "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		break;
	case 'discussed':
		$order = "{$votes_expression} DESC, {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
		break;
	case 'popular':
	default:
		$order = "COALESCE({$usage_expression}, 0) DESC, {$wpdb->posts}.ID DESC";
}
```

`$votes_expression` must be the lazy-total expression from Task 2 and `$usage_expression` must select numeric `_promocode_used_count`. Require the helper once from `functions.php`. The partial must render escaped GET links for all three sorts, render each result via `template-parts/promocode-card.php`, show an empty-state paragraph, and call `wp_reset_postdata()`.

- [ ] **Step 7: Add explicit canonical filtering**

Register `wp_get_canonical_url` and implement:

```php
public static function canonical_url( string $url, int $post_id ): string {
	return 'page-discounts.php' === get_post_meta( $post_id, '_wp_page_template', true )
		? get_permalink( $post_id )
		: $url;
}
```

- [ ] **Step 8: Run the focused tests and verify GREEN**

Run the three commands from Step 4; expected: PASS.

- [ ] **Step 9: Commit server rendering**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/templates/discounts-sort.php wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php wp-content/themes/promokodiki/inc/discounts.php wp-content/themes/promokodiki/functions.php wp-content/themes/promokodiki/template-parts/partials/promocodes-discounts.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
git commit -m "feat: render discounts ajax feed"
```

---

### Task 5: Sort-link AJAX behavior and accessible styling

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-state.js`
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-view.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/templates/discounts-sort.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-view.test.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

**Interfaces:**
- Consumes: `data-filter-sort` links and the root request lifecycle rendered in Task 4.
- Produces: `PromokodikiFilterView.syncSortLinks(links, sort)` and `setSortLinksDisabled(links, disabled)`, plus intercepted AJAX sorting with replacement, URL history, stale-request cancellation, selected-link synchronization, retry, and responsive segmented controls.

- [ ] **Step 1: Read `test-driven-development/writing-good-tests.md` before editing tests**

Record the production behavior each new assertion would catch: invalid sort URL parsing, selected-link state, and preserving page one on a sort change.

- [ ] **Step 2: Add failing pure-state tests**

Extend `filter-state.js` with a wished-for API and test it first:

```js
assert.equal(
  state.sortFromUrl('https://example.test/discounts/?paf_sort=discussed', ['popular', 'newest', 'discussed'], 'popular'),
  'discussed'
);
assert.equal(
  state.sortFromUrl('https://example.test/discounts/?paf_sort=oldest', ['popular', 'newest', 'discussed'], 'popular'),
  'popular'
);
```

- [ ] **Step 3: Run Node tests and verify RED**

```powershell
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js
```

Expected: `sortFromUrl is not a function`.

- [ ] **Step 4: Implement `sortFromUrl()` minimally and verify GREEN**

Export:

```js
function sortFromUrl(url, allowed, fallback) {
  const requested = new URL(url).searchParams.get('paf_sort') || fallback;
  return allowed.includes(requested) ? requested : fallback;
}
```

Run Step 3; expected: PASS.

- [ ] **Step 5: Add failing DOM-state tests before client changes**

Create small fake link objects implementing `dataset`, `setAttribute`, `removeAttribute`, and `classList.toggle`. Test the real `filter-view.js` functions:

```js
view.syncSortLinks(links, 'discussed');
assert.equal(links[2].attributes['aria-current'], 'true');
assert.equal(links[2].classes.has('tabs__nav-btn--active'), true);
assert.equal('aria-current' in links[0].attributes, false);

view.setSortLinksDisabled(links, true);
assert.equal(links.every((link) => link.attributes['aria-disabled'] === 'true'), true);
view.setSortLinksDisabled(links, false);
assert.equal(links.every((link) => !('aria-disabled' in link.attributes)), true);
```

Add a renderer behavior assertion that Discounts output contains one `[data-filter-form]` with a hidden `paf_sort` value matching the selected link. This is the compatibility boundary the existing client requires before it initializes sort and load-more behavior.

- [ ] **Step 6: Run the view test and verify RED**

```powershell
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-view.test.js
studio wp --path "C:\Users\Inception\Studio\promokodiki" eval-file "C:\Users\Inception\Studio\promokodiki\.worktrees\discounts-ajax-sorting\wp-content\plugins\promokodiki-ajax-filter\tests\php\test-renderer.php" --skip-plugins=promokodiki-ajax-filter
```

Expected: the module/functions do not exist.

- [ ] **Step 7: Extend the existing request lifecycle**

Implement `filter-view.js` as a UMD module, enqueue it between `filter-state.js` and `filter.js`, and consume it from `filter.js`. Inside each filter root:

```js
const sortLinks = Array.from(root.querySelectorAll('[data-filter-sort]'));

function syncSortLinks(sort) {
  viewApi.syncSortLinks(sortLinks, sort);
}
```

Wrap the Discounts sort navigation in a GET form carrying `data-filter-form` and a hidden `paf_sort` input initialized to the selected sort. On sort-link click, prevent navigation, derive normalized state from the link URL, reset page to one, and call the existing `request(1, false, 'push', state)`. Call `syncSortLinks()` after successful replace and after `popstate`. Keep cards untouched in `catch`, and rely on the existing retry closure and controller identity check.

Extend `setLoading()` so every sort link receives `aria-disabled="true"` while loading and has it removed afterward; ignore clicks whose link is currently disabled.

- [ ] **Step 8: Add segmented-control styles**

Style the visible label, scrolling nav, active/focus/loading/disabled states, and load-more button in plugin CSS. Do not alter card layout or the user's `style.css` change.

- [ ] **Step 9: Run all focused JS tests; verify GREEN**

Run Steps 3 and 6; expected: both Node suites and the renderer suite PASS.

- [ ] **Step 10: Commit client behavior and styles**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-state.js wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-view.js wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php wp-content/plugins/promokodiki-ajax-filter/templates/discounts-sort.php wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-view.test.js wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
git commit -m "feat: add accessible discounts sorting"
```

---

### Task 6: Remove Footer inline scripts without losing required behavior

**Files:**
- Modify: `wp-content/themes/promokodiki/footer.php`
- Create: `wp-content/themes/promokodiki/js/footer-ui.js`
- Create: `wp-content/themes/promokodiki/js/search-load-more.js`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Modify: `wp-content/themes/promokodiki/inc/ajax-search.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: existing Footer DOM, `promocode-modal.js`, search AJAX action, and localized WordPress AJAX URL/nonce.
- Produces: a markup-only Footer plus conditionally enqueued focused behavior files.

- [ ] **Step 1: Add failing Footer cleanup tests**

Attach a test callback to the `wp_footer` hook that prints a unique marker, render `footer.php`, and assert the output contains that marker, `id="promocodeModal"`, and `.footer__button_up`, but contains none of `<script`, `load_more_promocodes`, `load_more_search_results`, `DOMContentLoaded`, or an inline `openPromoModal` implementation.

Set the global query to search/non-search states, call `promokodiki_scripts()`, and inspect `wp_scripts()` to assert `search-load-more.js` is enqueued only for search while `footer-ui.js`, `navigation.js`, and `promocode-modal.js` remain enqueued globally.

Install a temporary `wp_die_handler` that throws a test exception, call `load_more_search_results()` with a missing/invalid nonce, and assert execution dies before any result markup is produced. Then create a valid `promokodiki_search` nonce and assert the valid request reaches the normal empty-query termination path.

- [ ] **Step 2: Run the integration test and verify RED**

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
```

- [ ] **Step 3: Move only required global UI behavior**

Implement `footer-ui.js` with null-safe delegated/menu behavior and the scroll-to-top button:

```js
document.addEventListener('click', (event) => {
  if (event.target.closest('.footer__button_up')) {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
});
```

Enqueue the existing `navigation.js` for accessible menu interaction; do not recreate the old hover-only jQuery submenu logic.

- [ ] **Step 4: Preserve search load-more in a focused file**

Move the search-only request lifecycle into `search-load-more.js`, localize an object named `PromokodikiSearchConfig` with `ajaxUrl` and a `promokodiki_search` nonce, retain the existing `load_more_search_results` action, send the nonce as `nonce`, guard missing containers, prevent concurrent requests, and preserve the existing results on error. Add `check_ajax_referer( 'promokodiki_search', 'nonce' );` as the first statement in `load_more_search_results()`.

- [ ] **Step 5: Delete all inline scripts from Footer**

Keep Footer and modal markup, the closing page wrapper, `wp_footer()`, and closing body/html tags. Delete every inline `<script>` block and the duplicate modal implementation because `promocode-modal.js` remains authoritative.

- [ ] **Step 6: Run the integration test and verify GREEN**

Run Step 2; expected: PASS.

- [ ] **Step 7: Commit Footer cleanup**

```powershell
git add wp-content/themes/promokodiki/footer.php wp-content/themes/promokodiki/js/footer-ui.js wp-content/themes/promokodiki/js/search-load-more.js wp-content/themes/promokodiki/functions.php wp-content/themes/promokodiki/inc/ajax-search.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
git commit -m "refactor: remove footer inline scripts"
```

---

### Task 7: Full regression and acceptance verification

**Files:**
- Modify only if a new failing regression requires a test-first fix in an already scoped file.

**Interfaces:**
- Consumes: completed Tasks 1-6.
- Produces: evidence that the feature, fallback, cleanup, and existing plugin behavior pass together.

- [ ] **Step 1: Run every plugin PHP integration test**

```powershell
Get-ChildItem wp-content/plugins/promokodiki-ajax-filter/tests/php/test-*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
```

Expected: every named test logs PASS and no harness throws.

- [ ] **Step 2: Run all JavaScript tests**

```powershell
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/*.test.js
```

Expected: all tests pass, zero failures.

- [ ] **Step 3: Run PHP syntax checks on changed PHP files**

```powershell
git diff --name-only HEAD~6 -- '*.php' | ForEach-Object { php -l $_ }
```

Expected: `No syntax errors detected` for every file.

- [ ] **Step 4: Run PHPCS**

```powershell
phpcs --standard=wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist wp-content/plugins/promokodiki-ajax-filter
```

Expected: zero errors. If PHPCS is unavailable, record that fact and run the repository CI-equivalent checks that are available locally.

- [ ] **Step 5: Inspect the live page when the local Studio site is available**

Verify `/discounts/` initial `popular` output, all three sorts, six-card append, Back/Forward, narrow viewport scrolling, keyboard focus, an intentionally failed/retried request, and a direct `?paf_sort=discussed` visit. View source to confirm one result region, base canonical, and no Footer inline script.

- [ ] **Step 6: Verify working-tree scope**

```powershell
git status --short
git diff --check
git diff --stat
```

Confirm the user's pre-existing `style.css` whitespace edit remains intact and uncommitted unless the user separately asks to include it.

- [ ] **Step 7: Apply `superpowers:verification-before-completion`**

Review fresh command output before making any completion claim. Report tests, any unavailable tooling, files changed, Footer cleanup, and whether live browser verification ran.
