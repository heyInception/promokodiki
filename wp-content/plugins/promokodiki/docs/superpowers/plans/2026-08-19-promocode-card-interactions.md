# Promocode Card Interactions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Исправить карточки, модалку, реакции и переходы, а также заменить старое обновление Telegram-блока единым трёхчасовым снимком.

**Architecture:** Плагин сохраняет единственные AJAX-контракты применения и голосования; тема локализует один конфигурационный объект и использует делегированный браузерный клиент для всех карточек. Канонический template part отдаёт данные через безопасные `data-*` атрибуты, а `inc/top.php` создаёт и рендерит серверный снимок Telegram-подборки.

**Tech Stack:** WordPress 6.4+, PHP 8.1+, vanilla JavaScript/jQuery только для существующей совместимости, `node:test`, WP-CLI test harness.

**Spec:** `docs/superpowers/specs/2026-08-19-promocode-card-interactions-design.md`

## Global Constraints

- Не менять WordPress core и не добавлять внешние зависимости.
- Все frontend AJAX-запросы используют `promokodiki_filter_frontend`.
- Переход в магазин выполняется синхронно с кликом и не зависит от AJAX.
- Истёкшие предложения не имеют активных действий.
- Сохранять заголовок «Топ промокодов из Telegram» без подписи.
- Не затрагивать уже опубликованную реализацию навигации.

---

### Task 1: Единый серверный контракт карточки и реакции

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-promo-interactions.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php`
- Modify: `wp-content/themes/promokodiki/template-parts/promocode-card.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

**Interfaces:**
- Consumes: cookie `promokodiki_visitor`, meta `_promocode_code`, `_promocode_link`, `_promocode_expiry_date`, counters.
- Produces: `Promokodiki_Filter_Promo_Interactions::reaction_for(int $post_id, string $visitor_id): string`; карточка с `data-post-id`, `data-store-url`, `data-code`, `data-expiry`, `data-expired`; AJAX vote payload with `reaction`.

- [ ] **Step 1: Write the failing PHP tests**

```php
$vote = Promokodiki_Filter_Promo_Interactions::vote( $post_id, 'visitor-a', 'like' );
Promokodiki_Filter_Test_Harness::assert_same( 'like', Promokodiki_Filter_Promo_Interactions::reaction_for( $post_id, 'visitor-a' ) );
Promokodiki_Filter_Test_Harness::assert_same( '', Promokodiki_Filter_Promo_Interactions::reaction_for( $post_id, 'visitor-b' ) );

$html = Promokodiki_Filter_Renderer::render_cards( array( get_post( $post_id ) ) );
Promokodiki_Filter_Test_Harness::assert_contains( 'data-store-url=', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'data-expiry="31.12.2026"', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'is-active', $html );
```

- [ ] **Step 2: Run tests to verify RED**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php`

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

Expected: FAIL because `reaction_for()` and the card data contract do not exist.

- [ ] **Step 3: Implement the minimal server/card contract**

```php
public static function reaction_for( int $post_id, string $visitor_id ): string {
    if ( '' === $visitor_id ) { return ''; }
    global $wpdb;
    return (string) $wpdb->get_var( $wpdb->prepare(
        "SELECT reaction FROM {$wpdb->prefix}promokodiki_promo_votes WHERE promocode_id=%d AND visitor_hash=%s",
        $post_id,
        hash( 'sha256', wp_salt( 'auth' ) . $visitor_id )
    ) );
}
```

Render semantic buttons/anchors once per desktop/mobile instance, escape every attribute, show `Промокод истёк` when expired, and add `is-active` to the stored reaction.

- [ ] **Step 4: Run tests to verify GREEN**

Run both PHP files above. Expected: PASS.

### Task 2: Единый браузерный клиент модалки, переходов и реакций

**Files:**
- Modify: `wp-content/themes/promokodiki/js/promocode-modal.js`
- Modify: `wp-content/themes/promokodiki/js/promocodes-like.js`
- Delete: `wp-content/themes/promokodiki/js/promocodes-ajax.js`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Modify: `wp-content/themes/promokodiki/footer.php`
- Create: `wp-content/themes/promokodiki/tests/promocode-interactions.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: `window.PromokodikiInteractions = { ajaxUrl, nonce }` and card `data-*` contract from Task 1.
- Produces: delegated `.promocodes__view`, `.promocodes__link`, `.promocodes__like` behavior and `window.openPromoModal(postId)` compatibility.

- [ ] **Step 1: Write failing Node and integration tests**

```js
test('direct store link never opens the modal and opens before tracking resolves', async () => {
  click(storeLink);
  assert.equal(openedUrl, 'https://shop.example/');
  assert.equal(modal.classList.contains('show'), false);
  assert.equal(fetchCalls[0].body.get('nonce'), 'valid-filter-nonce');
});

test('modal shows expiry and opens the store even when tracking rejects', async () => {
  click(viewButton);
  assert.equal(expiry.textContent, '31.12.2026');
  click(modalLink);
  assert.equal(openedUrl, 'https://shop.example/');
});
```

Add PHP assertions that `functions.php` no longer contains `add_ajaxurl`, `get_server_time_ajax`, or enqueues `promocodes-ajax.js`, and that the localized nonce is `promokodiki_filter_frontend`.

- [ ] **Step 2: Run tests to verify RED**

Run: `node --test wp-content/themes/promokodiki/tests/promocode-interactions.js`

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

Expected: FAIL because direct links are intercepted, the modal waits for AJAX, and legacy code remains.

- [ ] **Step 3: Implement minimal delegated behavior**

```js
function trackUsage(postId) {
  return fetch(config.ajaxUrl, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ action: 'promokodiki_promo_use', post_id: postId, nonce: config.nonce })
  }).then((response) => response.json()).then(syncUsage).catch(() => null);
}
```

Open the destination synchronously, call `trackUsage()` afterward, populate all modal fields from the card, preserve the modal, implement Clipboard API/fallback copy feedback, and synchronize counters/active reactions by `data-post-id`.

- [ ] **Step 4: Remove legacy theme paths and localize one config**

Remove the old global `<script>` from `wp_head`, obsolete timer endpoints, duplicate Telegram like/code handlers, and the `promocodes-ajax.js` enqueue. Keep assets versioned with `_S_VERSION` and exclude only the surviving interaction scripts from stale WP Rocket minification.

- [ ] **Step 5: Run tests to verify GREEN**

Run both commands above. Expected: PASS.

### Task 3: Серверный трёхчасовой Telegram-снимок

**Files:**
- Modify: `wp-content/themes/promokodiki/inc/top.php`
- Modify: `wp-content/themes/promokodiki/template-parts/partials/top.php`
- Modify: `wp-content/themes/promokodiki/js/promocode-modal.js`
- Create: `wp-content/themes/promokodiki/tests/top-promocodes.php`

**Interfaces:**
- Consumes: active published promocodes, click table `promokodiki_click_stats`, reaction counters, configured `popular_promocodes_count`.
- Produces: `promokodiki_top_snapshot(?int $now = null, bool $force = false): array{ids: int[], next_update: int}` and nonce-protected AJAX action `promokodiki_top_snapshot`.

- [ ] **Step 1: Write failing snapshot tests**

```php
$first  = promokodiki_top_snapshot( $window_start + 10, true );
$stable = promokodiki_top_snapshot( $window_start + 300 );
Promokodiki_Test::assert_same( $first['ids'], $stable['ids'] );
Promokodiki_Test::assert_true( in_array( $fresh_id, $first['ids'], true ) );
Promokodiki_Test::assert_true( ! in_array( $expired_id, $first['ids'], true ) );
Promokodiki_Test::assert_true( ! in_array( $inactive_id, $first['ids'], true ) );
```

- [ ] **Step 2: Run test to verify RED**

Run: `studio wp eval-file wp-content/themes/promokodiki/tests/top-promocodes.php`

Expected: FAIL because `promokodiki_top_snapshot()` does not exist.

- [ ] **Step 3: Implement deterministic weighted selection**

Compute `window_start = floor($now / (3 * HOUR_IN_SECONDS)) * (3 * HOUR_IN_SECONDS)`. Rank eligible posts by seven-day clicks, total reactions, freshness, and code bonus; reserve one fresh slot; deterministically rotate the ranked pool with a seed derived from the window. Cache `{window, ids, previous_ids}` in one option and never let a public request choose an arbitrary time or force refresh.

- [ ] **Step 4: Render Telegram cards with the shared interaction contract**

Use the same classes and `data-*` fields as canonical cards. Keep the exact heading and existing configured card count. The AJAX response contains rendered HTML plus authoritative `next_update`.

- [ ] **Step 5: Run test to verify GREEN**

Run the theme PHP test above and the Node interaction test. Expected: PASS.

### Task 4: Полная проверка и уборка регрессий

**Files:**
- Modify only files implicated by failing checks.

**Interfaces:**
- Consumes: completed Tasks 1–3.
- Produces: passing focused and regression suites with no legacy action names.

- [ ] **Step 1: Run syntax and static searches**

Run: `php -l` for every changed PHP file.

Run: `rg -n "handle_promocode_like|increment_promocode_used|get_server_time|promokodikiAjaxNonce|promocodes-ajax.js" wp-content/themes/promokodiki`

Expected: PHP syntax OK; no live legacy references.

- [ ] **Step 2: Run all plugin PHP tests**

Run each `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-*.php` through `studio wp eval-file`.

Expected: all PASS.

- [ ] **Step 3: Run all JavaScript tests**

Run: `node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/*.test.js wp-content/themes/promokodiki/tests/*.js`

Expected: all PASS.

- [ ] **Step 4: Run WordPress coding checks when available**

Run: `phpcs --standard=WordPress` against changed PHP files if `phpcs` is installed; otherwise record that the tool is unavailable and manually verify nonce, sanitization, escaping, and capability boundaries.

- [ ] **Step 5: Review the final diff**

Run: `git diff --check` and `git diff --stat`.

Expected: no whitespace errors and only interaction/Telegram/test files changed.
