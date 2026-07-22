# Promokodiki AJAX Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Создать отдельный WordPress-плагин, который фильтрует промокоды на главной, страницах категорий и магазинов без перезагрузки, хранит недельную статистику кликов и управляется из админки.

**Architecture:** Плагин владеет состоянием, контекстом, запросами, статистикой, AJAX и настройками; тема предоставляет существующую карточку `template-parts/promocode-card.php` и CSS-контейнер `.promocodes__filters`. Один сервис результатов используется как серверным GET-рендером, так и AJAX-контроллером, поэтому оба режима дают одинаковую выдачу.

**Tech Stack:** WordPress 7.0.1, PHP 8.1+, WordPress Settings/AJAX/Options/Transients APIs, `$wpdb` + `dbDelta`, vanilla JavaScript, существующая classic-тема `promokodiki`, WordPress Studio CLI.

## Global Constraints

- Все WP-CLI-команды выполняются только через `studio wp` из корня `C:\Users\Inception\Studio\promokodiki`.
- Перед первой WP-CLI-командой проверить `studio --version`; при недоступности CLI остановиться и попросить включить Studio CLI.
- Не изменять WordPress core, `style.css` или `assets/css/main.css`; новые стили добавлять в `wp-content/themes/promokodiki/assets/css/overrides.css` или в CSS плагина.
- Плагин должен работать с локальным SQLite WordPress Studio и production MySQL/MariaDB через WordPress API.
- Все AJAX-входы защищать nonce, очищать, проверять по белым спискам и экранировать при выводе.
- Административные изменения разрешать только с capability `manage_options`.
- Каждый запрос промокодов получает стабильную вторичную сортировку по ID.
- Filter Everything не удалять и не деактивировать до завершения локальной проверки.
- Текущий исполняемый файл `git.exe` отсутствует в окружении; commit-шаги выполнять после появления Git в `PATH`, не заявляя о коммитах раньше.

## File Map

Новые файлы плагина:

- `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php` — заголовок, константы, загрузка классов и bootstrap.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-activator.php` — таблица статистики и начальные настройки.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-settings.php` — defaults, нормализация и экран настроек.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-state.php` — нормализованное состояние фильтра.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php` — разрешённые элементы для главной, категории и магазина.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-click-stats.php` — дневные агрегаты и недельный рейтинг.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-query-service.php` — единый `WP_Query`, сортировки, сроки и пагинация.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php` — форма, карточки и начальный GET-рендер.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php` — выдача JSON и регистрация клика.
- `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php` — регистрация хуков и ресурсов.
- `wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php` — доступная GET-форма фильтра.
- `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-state.js` — чистые функции URL/state.
- `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js` — AJAX, History API, «Показать ещё», ошибки и клики.
- `wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css` — только состояния плагина, без копирования основного дизайна.
- `wp-content/plugins/promokodiki-ajax-filter/tests/harness.php` — минимальные assertions для `studio wp eval-file`.
- `wp-content/plugins/promokodiki-ajax-filter/tests/php/*.php` — интеграционные тесты WordPress.
- `wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js` — тесты URL/state через `node:test`.
- `wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist` — WordPress Coding Standards для плагина.
- `wp-content/plugins/promokodiki-ajax-filter/README.md` — установка, настройки, URL и особенности статистики.

Изменяемые файлы:

- `wp-content/themes/promokodiki/template-parts/partials/promocodes.php` — главная: заменить Filter Everything и локальный запрос вызовом плагина.
- `wp-content/themes/promokodiki/taxonomy-promocode_category.php` — категория: заменить сортировку и локальный запрос вызовом плагина.
- `wp-content/themes/promokodiki/taxonomy-shops_category.php` — магазин: заменить Filter Everything и локальный запрос вызовом плагина.
- `wp-content/themes/promokodiki/functions.php` — удалить старые endpoints счётчика и `load_more_promocodes` после переключения.
- `wp-content/themes/promokodiki/js/promocodes-ajax.js` — оставить открытие ссылок/модалки, убрать старый запрос счётчика.
- `.github/workflows/ci.yml` — включить новый плагин в PHP/security checks.

---

### Task 1: Каркас плагина, тестовый harness и схема статистики

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-activator.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/harness.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-activation.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Activator::activate(): void`, `Promokodiki_Filter_Plugin::boot(): void`, constants `PROMOKODIKI_FILTER_FILE`, `PROMOKODIKI_FILTER_VERSION`.
- Produces database table: `{$wpdb->prefix}promokodiki_click_stats(promocode_id, click_date, clicks)`.

- [ ] **Step 1: Write the failing activation test**

Создать harness с методами `run()`, `assert_same()`, `assert_true()` и итоговым исключением при ошибках. В `test-activation.php` загрузить main-файл, вызвать activation и проверить класс, option версии схемы и таблицу:

```php
<?php
require_once __DIR__ . '/../harness.php';
require_once WP_PLUGIN_DIR . '/promokodiki-ajax-filter/promokodiki-ajax-filter.php';

Promokodiki_Filter_Test_Harness::run(
	'activation creates schema',
	static function (): void {
		global $wpdb;
		Promokodiki_Filter_Activator::activate();
		$table = $wpdb->prefix . 'promokodiki_click_stats';
		Promokodiki_Filter_Test_Harness::assert_true( class_exists( 'Promokodiki_Filter_Plugin' ) );
		Promokodiki_Filter_Test_Harness::assert_same( '1', get_option( 'promokodiki_filter_db_version' ) );
		Promokodiki_Filter_Test_Harness::assert_same(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}
);
Promokodiki_Filter_Test_Harness::finish();
```

- [ ] **Step 2: Run the test and verify failure**

Run: `studio --version`

Expected: version `1.15.0` or newer.

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-activation.php`

Expected: FAIL because the plugin main file or `Promokodiki_Filter_Activator` does not exist.

- [ ] **Step 3: Implement the minimal plugin and schema**

Main file must require focused classes, register `register_activation_hook( PROMOKODIKI_FILTER_FILE, array( 'Promokodiki_Filter_Activator', 'activate' ) )`, and call `Promokodiki_Filter_Plugin::boot()` on `plugins_loaded`.

Activator SQL must be passed through `dbDelta()`:

```php
$sql = "CREATE TABLE {$table} (
	promocode_id BIGINT(20) UNSIGNED NOT NULL,
	click_date DATE NOT NULL,
	clicks BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY  (promocode_id, click_date),
	KEY click_date (click_date),
	KEY promocode_id (promocode_id)
) {$charset_collate};";
```

Store `promokodiki_filter_db_version = 1` only after `dbDelta()` completes. Activation must be idempotent.

- [ ] **Step 4: Activate and verify pass**

Run: `studio wp plugin activate promokodiki-ajax-filter`

Expected: `Plugin 'promokodiki-ajax-filter' activated.`

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-activation.php`

Expected: `PASS activation creates schema` and exit code 0.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: scaffold promocode filter plugin"
```

### Task 2: Настройки и нормализованное состояние

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-settings.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-state.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Settings::defaults(): array`, `get(): array`, `sanitize(array $input): array`.
- Produces: `Promokodiki_Filter_State::from_request(array $request, array $settings, string $context_type): array`.
- State keys: `category_id:int`, `brand_id:int`, `sort:string`, `popular:bool`, `page:int`.

- [ ] **Step 1: Write failing state/settings tests**

Test exact defaults and invalid input:

```php
$settings = Promokodiki_Filter_Settings::defaults();
Promokodiki_Filter_Test_Harness::assert_same( 8, $settings['initial_count'] );
Promokodiki_Filter_Test_Harness::assert_same( 8, $settings['load_more_count'] );
Promokodiki_Filter_Test_Harness::assert_same( 7, $settings['popular_days'] );
Promokodiki_Filter_Test_Harness::assert_same( 'newest', $settings['default_sort'] );

$state = Promokodiki_Filter_State::from_request(
	array(
		'paf_category' => '-2',
		'paf_brand'    => '17',
		'paf_sort'     => 'drop-table',
		'paf_popular'  => '1',
		'paf_page'     => '0',
	),
	$settings,
	'home'
);
Promokodiki_Filter_Test_Harness::assert_same(
	array( 'category_id' => 0, 'brand_id' => 0, 'sort' => '', 'popular' => true, 'page' => 1 ),
	$state
);
```

Also assert that `paf_popular=1` is ignored outside `home`, disabled sort options fall back to `default_sort`, counts clamp to `1..100`, and popularity days clamp to `1..31`.

- [ ] **Step 2: Run test and verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php`

Expected: FAIL with missing settings/state class.

- [ ] **Step 3: Implement settings defaults and state normalization**

Use these exact allowed sort keys:

```php
private const SORTS = array( 'newest', 'popular', 'expiring', 'oldest' );
```

`from_request()` must apply `absint()`, `sanitize_key()`, strict `in_array(..., true)`, force page to at least 1, and when weekly popular is active return zero category/brand and an empty normal sort.

Settings defaults must include labels `Категории`, `Бренды`, `Популярное за неделю`, `Без сортировки`, `Показать ещё`, `Повторить`, plus `show_expired=false` and all four sort keys.

- [ ] **Step 4: Run tests**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-state.php`

Expected: all state/settings assertions PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: normalize filter settings and state"
```

### Task 3: Контексты и разрешённые dropdown-значения

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Context::resolve(string $type, int $object_id = 0): array|WP_Error`.
- Produces context keys: `type`, `object_id`, `category_options`, `brand_options`, `allowed_category_ids`, `allowed_brand_ids`.
- Produces: `Promokodiki_Filter_Context::flush_cache(): void`.

- [ ] **Step 1: Write fixtures and failing context tests**

Create a parent category, child, grandchild, unrelated category, one shop, two brands, an active promo for brand A in the shop and an expired promo for brand B. Use `try/finally` to delete all posts and terms.

Assertions:

```php
$category_context = Promokodiki_Filter_Context::resolve( 'category', $parent_id );
Promokodiki_Filter_Test_Harness::assert_same(
	array( $parent_id, $child_id, $grandchild_id ),
	$category_context['allowed_category_ids']
);

$shop_context = Promokodiki_Filter_Context::resolve( 'shop', $shop_id );
Promokodiki_Filter_Test_Harness::assert_same( array( $brand_a_id ), $shop_context['allowed_brand_ids'] );
```

Also assert that an unrelated term is rejected and a non-existent object returns `WP_Error`.

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php`

Expected: FAIL with missing context class.

- [ ] **Step 3: Implement context resolution and cache invalidation**

For category options, prepend the current term and recursively fetch descendants with `get_term_children()`, then sort descendants by hierarchy/name and prefix labels with non-breaking indentation.

For shop brands, query published `promocode` IDs constrained by the current `shops_category` and the active-expiry rule, then call `wp_get_object_terms( $ids, 'promocode_brand' )`. Cache serializable option arrays with keys containing type/object ID and a cache-version option.

Register invalidation on:

```php
add_action( 'created_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
add_action( 'edited_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
add_action( 'delete_term', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
add_action( 'set_object_terms', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
add_action( 'save_post_promocode', array( 'Promokodiki_Filter_Context', 'flush_cache' ) );
```

- [ ] **Step 4: Run context test**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php`

Expected: category hierarchy and active shop-brand assertions PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: resolve contextual filter options"
```

### Task 4: Дневная статистика и безопасный click endpoint

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-click-stats.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Click_Stats::increment(int $post_id): int|WP_Error` returning the new lifetime total.
- Produces: `ranked_ids(int $days, int $limit, int $offset, bool $include_expired): array`.
- AJAX action: `promokodiki_filter_track_click` for authenticated and anonymous users.

- [ ] **Step 1: Write failing click tests**

Create one published and one draft `promocode`. Set published lifetime count to 12, call `increment()` twice, and assert:

```php
Promokodiki_Filter_Test_Harness::assert_same( 13, Promokodiki_Filter_Click_Stats::increment( $published_id ) );
Promokodiki_Filter_Test_Harness::assert_same( 14, Promokodiki_Filter_Click_Stats::increment( $published_id ) );
Promokodiki_Filter_Test_Harness::assert_same( '14', get_post_meta( $published_id, '_promocode_used_count', true ) );
Promokodiki_Filter_Test_Harness::assert_same( array( $published_id ), Promokodiki_Filter_Click_Stats::ranked_ids( 7, 8, 0, false ) );
Promokodiki_Filter_Test_Harness::assert_true( is_wp_error( Promokodiki_Filter_Click_Stats::increment( $draft_id ) ) );
```

Delete the test table row and posts in `finally`.

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php`

Expected: FAIL with missing click statistics class.

- [ ] **Step 3: Implement transactional increment**

On the first plugin-counted click store `_promokodiki_filter_click_baseline` from the existing lifetime total. Start a transaction, upsert the current WordPress-local date, sum plugin rows for the post, set `_promocode_used_count = baseline + plugin_sum`, then commit. On any `$wpdb->last_error`, roll back and return `WP_Error`.

Use `$wpdb->prepare()` for IDs/dates and `INSERT ... ON DUPLICATE KEY UPDATE clicks = clicks + 1`, which WordPress Studio's database integration translates for SQLite.

The endpoint must call `check_ajax_referer( 'promokodiki_filter_frontend', 'nonce' )`, apply `absint()` and return only `new_count`.

- [ ] **Step 4: Run click tests**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-click-stats.php`

Expected: PASS; lifetime total is 14 and one daily row contains 2 clicks.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: track daily promocode clicks"
```

### Task 5: Единый query service, сортировки, сроки и пагинация

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-query-service.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Query_Service::run(array $state, array $context, array $settings): array|WP_Error`.
- Result keys: `posts:WP_Post[]`, `page:int`, `has_more:bool`, `total:int`.

- [ ] **Step 1: Write failing matrix test**

Create deterministic promos with explicit dates, counts, expiry values, category/brand/shop terms and daily stats. Assert separately:

- home category + brand uses `AND` between taxonomy clauses;
- category state accepts only allowed descendants;
- shop state accepts only allowed brands while retaining current shop;
- newest, lifetime popular, expiring and oldest orders match exact ID arrays;
- default excludes expired;
- `show_expired=true` places expired IDs after all active IDs;
- page 2 contains no page 1 IDs;
- weekly state follows `ranked_ids()` and returns an empty result when no rows exist.

Example assertion:

```php
$result = Promokodiki_Filter_Query_Service::run( $state, $context, $settings );
Promokodiki_Filter_Test_Harness::assert_same( array( $new_id, $old_id ), wp_list_pluck( $result['posts'], 'ID' ) );
Promokodiki_Filter_Test_Harness::assert_true( $result['has_more'] );
```

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php`

Expected: FAIL with missing query service.

- [ ] **Step 3: Implement query service**

Build taxonomy clauses only from context-approved IDs. Use a scoped `posts_clauses` filter installed immediately before `new WP_Query()` and removed in `finally` to order expiry values with empty/missing dates last and, when configured, expired rows after active rows.

Stable order rules:

```php
$order_by = array(
	'date' => 'DESC',
	'ID'   => 'DESC',
);
```

Replace the primary field for each sort while retaining `ID` as the final key. For weekly mode, fetch ranked IDs and use `post__in` + `orderby => post__in`. Compute `has_more` by requesting one extra row, then remove it before returning.

Offset is not derived as `page * current_limit`, because the first and subsequent portion sizes are independently configurable. Use `0` for page 1 and `initial_count + ( page - 2 ) * load_more_count` for page 2 and later; use `initial_count` on page 1 and `load_more_count` thereafter.

- [ ] **Step 4: Run matrix test**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php`

Expected: all context, sort, expiry, weekly and pagination assertions PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: query filtered promocodes consistently"
```

### Task 6: Серверный GET-рендер и доступная форма

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`

**Interfaces:**
- Produces: `Promokodiki_Filter_Renderer::render(string $type, int $object_id = 0): string`.
- Produces: `Promokodiki_Filter_Renderer::render_cards(array $posts): string`.
- Public integration function: `promokodiki_filter_render(array $args = array()): void`.
- Shortcode: `[promokodiki_ajax_filter context="home|category|shop" object_id="0"]`.

- [ ] **Step 1: Write failing renderer test**

Render each context and assert exact structural markers, not fragile full HTML:

```php
$html = Promokodiki_Filter_Renderer::render( 'home', 0 );
Promokodiki_Filter_Test_Harness::assert_contains( 'class="promocodes__filters"', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_category"', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_brand"', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_popular"', $html );
Promokodiki_Filter_Test_Harness::assert_not_contains( 'Проверенные', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'aria-live="polite"', $html );
```

For category assert no brand/popular input; for shop assert no category/popular input. Add `assert_contains()` and `assert_not_contains()` to harness.

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

Expected: FAIL with missing renderer.

- [ ] **Step 3: Implement escaped GET form and card rendering**

Form action is the current canonical page URL. All controls use `paf_` names, explicit visually hidden labels, selected/checked helpers, a hidden context token, and a `<noscript><button type="submit">Применить</button></noscript>` fallback.

Results wrapper must be:

```html
<div class="promokodiki-filter" data-promokodiki-filter>
  <form class="promocodes__filters" data-filter-form><!-- controls --></form>
  <div class="promocodes__items" data-filter-results aria-live="polite" aria-busy="false"></div>
  <button type="button" class="promokodiki-filter__more" data-filter-more>Показать ещё</button>
  <div class="promokodiki-filter__status" data-filter-status role="status"></div>
</div>
```

Render each `WP_Post` by setting up post data and loading `locate_template( 'template-parts/promocode-card.php' )`; always restore global post data in `finally`. If the template is absent, return an escaped admin-visible diagnostic and a generic title link on the front end.

- [ ] **Step 4: Run renderer tests**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

Expected: context-specific controls and ARIA assertions PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: render progressive filter form"
```

### Task 7: AJAX results, URL/history state and frontend behavior

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter-state.js`
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js`
- Create: `wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`

**Interfaces:**
- AJAX action: `promokodiki_filter_results` for authenticated and anonymous users.
- Produces JSON data: `html:string`, `page:int`, `has_more:bool`, `total:int`, `message:string`.
- JS exports for tests: `normalizeState(object): object`, `stateToSearchParams(object): URLSearchParams`.

- [ ] **Step 1: Write failing PHP and JavaScript tests**

PHP service test calls a non-terminating controller method `build_results_payload()` with a valid context token and asserts all five response keys plus rejection of a brand not allowed by the shop context.

JavaScript test:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const state = require('../../assets/js/filter-state.js');

test('weekly popularity clears category brand and sort', () => {
  assert.deepEqual(
    state.normalizeState({ category: '4', brand: '8', sort: 'newest', popular: true, page: 3 }),
    { category: '', brand: '', sort: '', popular: true, page: 1 }
  );
});

test('query string contains only paf keys', () => {
  assert.equal(
    state.stateToSearchParams({ category: '4', brand: '', sort: 'oldest', popular: false, page: 1 }).toString(),
    'paf_category=4&paf_sort=oldest'
  );
});
```

- [ ] **Step 2: Verify failures**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php`

Expected: FAIL with missing controller.

Run: `node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`

Expected: FAIL because `filter-state.js` does not exist.

- [ ] **Step 3: Implement controller and assets**

Controller verifies `promokodiki_filter_frontend`, validates a context nonce bound to `type + object_id`, resolves allowed values, normalizes state, calls query service, renders cards and returns payload.

`filter.js` must:

- use one delegated initializer per `[data-promokodiki-filter]`;
- intercept form changes/submission;
- make `fetch()` POST requests with `AbortController`;
- replace results for page 1 and append for later pages;
- update URL with `history.pushState()` and respond to `popstate`;
- reset all other controls when weekly popularity is enabled;
- toggle `aria-busy`, disabled states and the load-more button;
- preserve cards on failure and expose a retry button;
- track `.promocodes__view`, `.promocodes__link`, `.top__button` clicks with a separate keepalive POST;
- update every visible lifetime count for the returned post ID.

Localize only `ajaxUrl`, `nonce`, `retryLabel`, `loadingLabel`, and `genericError`; do not expose database or capability details.

CSS may define `.is-loading`, `.promokodiki-filter__status`, `.promokodiki-filter__more`, retry state and a spinner. It must not redefine the base `.promocodes__filters` layout.

- [ ] **Step 4: Run automated tests**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php`

Expected: valid payload PASS; invalid context selection returns `WP_Error`.

Run: `node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`

Expected: 2 tests PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: add ajax filter interactions"
```

### Task 8: Админка и предупреждение о Filter Everything

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-settings.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-plugin.php`
- Create: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-admin.php`

**Interfaces:**
- Settings option: `promokodiki_filter_settings`.
- Submenu slug: `promokodiki-ajax-filter` under `edit.php?post_type=promocode`.
- Produces: `Promokodiki_Filter_Settings::register(): void`, `render_page(): void`, `render_conflict_notice(): void`.

- [ ] **Step 1: Write failing admin tests**

Assert that sanitize clamps counts/days, removes unknown sort keys, repairs a disabled default sort, sanitizes every label, and preserves booleans. Simulate active plugins with `active_plugins` option and assert notice HTML contains both detected plugin names but no deactivate/delete link.

- [ ] **Step 2: Verify failure**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-admin.php`

Expected: FAIL because registration/page/notice methods are absent.

- [ ] **Step 3: Implement Settings API page**

Register one option with `sanitize_callback`, sections for pagination/sorting/popularity/content/labels, and fields for every approved setting. Page callback must check `current_user_can( 'manage_options' )`, call `settings_errors()`, `settings_fields()` and `do_settings_sections()`.

Conflict notice checks these exact plugin basenames:

```php
array(
	'filter-everything/filter-everything.php',
	'filter-everything-pro/filter-everything.php',
)
```

If either is active, show a dismissible warning explaining that deactivation happens only after verification.

- [ ] **Step 4: Run admin tests**

Run: `studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-admin.php`

Expected: all sanitization, capability and conflict notice assertions PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter
git commit -m "feat: configure ajax filter in admin"
```

### Task 9: Интеграция темы, удаление старых обработчиков и полная проверка

**Files:**
- Modify: `wp-content/themes/promokodiki/template-parts/partials/promocodes.php`
- Modify: `wp-content/themes/promokodiki/taxonomy-promocode_category.php`
- Modify: `wp-content/themes/promokodiki/taxonomy-shops_category.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Modify: `wp-content/themes/promokodiki/js/promocodes-ajax.js`
- Modify: `.github/workflows/ci.yml`
- Create: `wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist`
- Create: `wp-content/plugins/promokodiki-ajax-filter/README.md`

**Interfaces:**
- Consumes: `promokodiki_filter_render(array $args): void` from Task 6.
- Preserves: card markup, modal `window.openPromoModal()`, likes/dislikes, ACF sections and sidebars.

- [ ] **Step 1: Capture the pre-integration failure**

Run:

```powershell
rg -n "fe_widget|fe_sort|increment_promocode_count|function load_more_promocodes" wp-content/themes/promokodiki
```

Expected: matches in all three target templates, `functions.php` and `promocodes-ajax.js`.

- [ ] **Step 2: Replace the three local filter/query blocks**

Use guarded calls so the theme remains diagnosable if the plugin is inactive:

```php
<?php
if ( function_exists( 'promokodiki_filter_render' ) ) {
	promokodiki_filter_render(
		array(
			'context'   => 'category',
			'object_id' => (int) get_queried_object_id(),
		)
	);
} elseif ( current_user_can( 'manage_options' ) ) {
	echo '<p>' . esc_html__( 'Активируйте Promokodiki AJAX Filter.', 'promokodiki' ) . '</p>';
}
?>
```

Use `context => home` in the ACF homepage partial and `context => shop` in `taxonomy-shops_category.php`. Remove only the replaced filter markup, local `WP_Query`, its loop and local pagination; retain surrounding column, description, ACF sections and aside.

- [ ] **Step 3: Remove legacy endpoints without breaking modal behavior**

Delete `increment_promocode_used_count()`, both `increment_promocode_count` hooks, `load_more_promocodes()` and both `load_more_promocodes` hooks from `functions.php`.

Replace the first handler in `promocodes-ajax.js` with UI-only behavior; tracking now belongs to plugin JS:

```js
jQuery(function ($) {
  $(document).on('click', '.promocodes__view, .top__button, .promocodes__link', function (event) {
    event.preventDefault();
    const $button = $(this);
    const postId = $button.data('post-id');

    if ($button.hasClass('promocodes__link')) {
      window.open($button.attr('href'), '_blank');
    } else if ($button.hasClass('promocodes__view') && typeof window.openPromoModal === 'function') {
      window.openPromoModal(postId);
    }
  });
});
```

- [ ] **Step 4: Extend CI and document operation**

Change both CI path lists to include `wp-content/plugins/promokodiki-ajax-filter`. Add a CI step that installs WPCS and runs the plugin ruleset:

```yaml
      - name: Install WordPress Coding Standards
        run: |
          composer global config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
          composer global require --dev wp-coding-standards/wpcs:^3.0
      - name: WordPress Coding Standards
        run: phpcs --standard=wp-content/plugins/promokodiki-ajax-filter/phpcs.xml.dist wp-content/plugins/promokodiki-ajax-filter
```

The plugin ruleset scans PHP files, excludes `tests/`, sets `minimum_supported_wp_version` to `7.0`, and uses `WordPress`, `WordPress-Docs`, and `WordPress-Extra` rule sets. README must document activation, admin path, all `paf_` URL parameters, 7-day cold start, GET fallback, manual Filter Everything rollback and the fact that every click is counted.

- [ ] **Step 5: Run automated regression suite**

Run each PHP file:

```powershell
Get-ChildItem wp-content/plugins/promokodiki-ajax-filter/tests/php/*.php | ForEach-Object { studio wp eval-file $_.FullName }
```

Expected: every test prints PASS and every command exits 0.

Run: `node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`

Expected: all tests PASS.

Run: `studio wp eval 'echo function_exists("promokodiki_filter_render") ? "yes" : "no";'`

Expected: `yes`.

Run:

```powershell
rg -n "fe_widget|fe_sort|increment_promocode_count|function load_more_promocodes" wp-content/themes/promokodiki
```

Expected: no matches in the three target templates, `functions.php` or `promocodes-ajax.js`.

- [ ] **Step 6: Perform browser acceptance checks before deactivation**

Use `studio status` to obtain the dynamic URL; if the site is stopped, run `studio start --skip-browser`. Verify desktop and mobile for:

1. Home category, brand, category+brand, weekly mode and all four sorts.
2. Category current branch dropdown and one child selection.
3. Shop brand dropdown with no unrelated/expired-only brand.
4. URL reload and browser Back/Forward.
5. «Показать ещё» without duplicates.
6. Empty state, forced network failure/retry and loading ARIA.
7. Every code/store click increments lifetime and current-day aggregate.
8. Keyboard-only operation and existing promo modal.

Expected: visual design remains the supplied `.promocodes__filters` row and existing mobile overflow behavior; «Проверенные» is absent.

- [ ] **Step 7: Deactivate Filter Everything only after acceptance passes**

Run: `studio wp plugin deactivate filter-everything filter-everything-pro`

Expected: both plugins report deactivated; their files/settings remain available for rollback.

Repeat the three page-context checks and confirm no layout or query changes.

- [ ] **Step 8: Commit the integration**

```powershell
git add .github/workflows/ci.yml wp-content/plugins/promokodiki-ajax-filter wp-content/themes/promokodiki/functions.php wp-content/themes/promokodiki/js/promocodes-ajax.js wp-content/themes/promokodiki/template-parts/partials/promocodes.php wp-content/themes/promokodiki/taxonomy-promocode_category.php wp-content/themes/promokodiki/taxonomy-shops_category.php
git commit -m "feat: replace Filter Everything with ajax filter"
```

## Completion Evidence

Before claiming completion, record:

- active plugin list before and after the controlled Filter Everything deactivation;
- output of every PHP and JavaScript test;
- output of the legacy-reference scan;
- database row proving same-day click aggregation;
- screenshots of home/category/shop at desktop and mobile widths;
- direct URLs proving state restoration;
- exact files changed and any deviations from this plan.
