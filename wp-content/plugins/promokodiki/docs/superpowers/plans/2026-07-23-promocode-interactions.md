# Интерактивные промокоды Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Централизовать статусы, применения, реакции и модальное получение промокодов в AJAX-плагине, оставив теме единый вывод карточки.

**Architecture:** Плагин добавляет отдельные сервисы для статусов, применений и реакций, а также две таблицы с анонимными хэшами. Единый template part предоставляет data-атрибуты; отдельный vanilla-JS клиент обслуживает модалку, голосование и учёт перехода.

**Tech Stack:** WordPress 6.4+, PHP 8.1, WPDB, admin-ajax, vanilla JavaScript, WP-CLI test harness, Node `node:test`.

## Global Constraints

- Не удалять и не сбрасывать `_promocode_used_count` или `promokodiki_click_stats`.
- Проверять nonce, опубликованный `promocode` и все входные данные AJAX.
- Использовать URL магазина только из серверных метаданных.
- `new_days=14`, `usage_cooldown_hours=24`, `0` для cooldown означает учёт каждого клика.
- Badge: `expired`, затем `new`, затем `popular`.

---

### Task 1: Настройки, таблицы и статусы

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-promo-status.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-settings.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-activator.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-status.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-settings.php`

**Interfaces:**
- Produces `Promokodiki_Filter_Promo_Status::for_post( int $post_id ): string` returning `expired`, `new`, `popular` or `''`.
- Adds sanitized settings `new_days`, `usage_cooldown_hours`, `popular_min_clicks`, `expired_actions_enabled`.

- [ ] **Step 1: Write failing setting and status tests**

```php
Promokodiki_Filter_Test_Harness::assert_same( 14, Promokodiki_Filter_Settings::defaults()['new_days'] );
Promokodiki_Filter_Test_Harness::assert_same( 0, Promokodiki_Filter_Settings::sanitize( array( 'usage_cooldown_hours' => -1 ) )['usage_cooldown_hours'] );
Promokodiki_Filter_Test_Harness::assert_same( 'expired', Promokodiki_Filter_Promo_Status::for_post( $expired_id ) );
```

- [ ] **Step 2: Verify tests fail**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-settings.php` and `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-status.php` from the Studio project root.

- [ ] **Step 3: Implement minimal settings, schema and status service**

Add fields to `defaults()`, constrain days to `1..365`, cooldown to `0..8760`, popularity minimum to `1..1000000`, and render them in a dedicated settings section. Activator creates `{$wpdb->prefix}promokodiki_promo_usage` (`promocode_id`, `visitor_hash`, `used_at`, unique composite index) and `{$wpdb->prefix}promokodiki_promo_votes` (`promocode_id`, `visitor_hash`, `reaction`, `updated_at`, unique composite index). `for_post()` compares WordPress time, publication timestamp and `Promokodiki_Filter_Click_Stats::count_for_post()`.

- [ ] **Step 4: Verify tests pass and commit**

Run the two tests. Commit: `feat: add promocode status settings`.

### Task 2: Применения и реакции

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-promo-interactions.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-click-stats.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php`

**Interfaces:**
- Produces `record_usage( int $post_id, string $visitor_id ): array|WP_Error` and `vote( int $post_id, string $visitor_id, string $reaction ): array|WP_Error`.
- Produces AJAX actions `promokodiki_promo_use` and `promokodiki_promo_vote` returning both aggregate counts.

- [ ] **Step 1: Write failing interaction tests**

```php
$first = Promokodiki_Filter_Promo_Interactions::record_usage( $post_id, 'visitor-a' );
$second = Promokodiki_Filter_Promo_Interactions::record_usage( $post_id, 'visitor-a' );
Promokodiki_Filter_Test_Harness::assert_true( $first['counted'] );
Promokodiki_Filter_Test_Harness::assert_same( false, $second['counted'] );
Promokodiki_Filter_Test_Harness::assert_same( array( 'likes' => 0, 'dislikes' => 1 ), Promokodiki_Filter_Promo_Interactions::vote( $post_id, 'visitor-a', 'dislike' ) );
```

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-promo-interactions.php`.

- [ ] **Step 3: Implement and register endpoints**

Hash the cookie ID with `wp_hash()`. Insert usage only when cooldown permits, then call `Click_Stats::increment()`; otherwise return the current total unchanged. Upsert votes and atomically adjust post meta when reaction changes. AJAX reads/sets a secure, HttpOnly-independent first-party `promokodiki_visitor` cookie with `wp_generate_uuid4()` and never accepts a target URL from request data.

- [ ] **Step 4: Verify and commit**

Run the interaction and existing click-stat tests. Commit: `feat: add deduplicated usage and votes`.

### Task 3: Единый шаблон карточки и ссылки брендов

**Files:**
- Create: `wp-content/themes/promokodiki/template-parts/promocode-card-data.php`
- Modify: `wp-content/themes/promokodiki/template-parts/promocode-card.php`
- Modify: `wp-content/themes/promokodiki/template-parts/content-search.php`
- Modify: `wp-content/themes/promokodiki/inc/layout.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-interaction-integration.php`

**Interfaces:**
- Produces `promokodiki_get_promocode_card_data( int $post_id ): array` with URL, code presence, brand links, counters and status.
- Every visible brand name/logo is wrapped in `get_term_link( $brand )`.

- [ ] **Step 1: Write failing rendered-card tests**

```php
Promokodiki_Filter_Test_Harness::assert_contains( 'data-promo-action="reveal"', $html );
Promokodiki_Filter_Test_Harness::assert_contains( esc_url( get_term_link( $brand_id ) ), $html );
Promokodiki_Filter_Test_Harness::assert_not_contains( 'handle_promocode_feedback', file_get_contents( $theme_dir . '/functions.php' ) );
```

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-interaction-integration.php`.

- [ ] **Step 3: Extract data and replace duplicate output**

Use `get_template_part( 'template-parts/promocode-card', null, array( 'post_id' => $post_id ) )` in every context. Output accessible buttons with `data-promo-id`, no external href, and disabled attributes for expired cards when setting dictates. Preserve responsive CSS hooks and use the plugin’s status service only when it exists.

- [ ] **Step 4: Verify and commit**

Run the integration test and theme PHP lint. Commit: `refactor: unify promocode card output`.

### Task 4: Модалка и фронтенд-клиент

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/js/promo-interactions.js`
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/css/promo-interactions.css`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Modify: `wp-content/themes/promokodiki/footer.php`
- Delete: `wp-content/themes/promokodiki/js/promocodes-like.js`
- Modify: `wp-content/themes/promokodiki/js/promocodes-ajax.js`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/js/promo-interactions.test.js`

**Interfaces:**
- Exposes no globals; delegated click handlers use `[data-promo-action]`.
- Modal supports Escape, backdrop close, focus restoration and clipboard fallback.

- [ ] **Step 1: Write failing JS tests**

```js
test('reveal copies code and fills the modal', () => {
  const state = modalState({ id: 4, code: 'SAVE10', action: 'reveal' });
  assert.equal(state.code, 'SAVE10');
  assert.equal(state.shouldCopy, true);
});
test('store confirmation posts usage before opening the server URL', async () => { /* assert fetch before window.open */ });
```

- [ ] **Step 2: Verify failure**

Run: `node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/promo-interactions.test.js`.

- [ ] **Step 3: Implement progressive enhancement**

Render one hidden dialog in footer. Localize AJAX endpoint, nonce and labels from plugin. Use `navigator.clipboard.writeText` with `document.execCommand('copy')` fallback. On confirm, POST only action, nonce and ID, update matching card counters, then `window.open(response.data.storeUrl, '_blank', 'noopener')`. Implement optimistic vote disabling only while request is pending; accept changed reaction response.

- [ ] **Step 4: Verify and commit**

Run the JS test and theme JS lint. Commit: `feat: add promocode interaction modal`.

### Task 5: Регрессия, качество и документация

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/README.md`
- Modify: `README.md`
- Test: all plugin PHP and JS tests

- [ ] **Step 1: Document admin controls and cookie purpose**

Describe the four controls, `0` cooldown behavior, badge priority, reaction semantics and that the functional cookie prevents duplicated counting.

- [ ] **Step 2: Run full verification**

Run from canonical Studio root after safely copying/pointing tests to the branch: all `tests/php/*.php`, both Node tests, `php -l` for theme/plugin and `phpcs --standard=wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist wp-content/plugins/promokodiki-ajax-filter`.

- [ ] **Step 3: Inspect the diff and commit**

Run `git diff --check` and `git status --short`. Commit: `docs: document promocode interactions`.
