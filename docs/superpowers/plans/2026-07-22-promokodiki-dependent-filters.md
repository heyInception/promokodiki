# Promokodiki Dependent Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сделать взаимозависимые категории и бренды на главной, корректные списки брендов для всех магазинов, скрытие бессмысленных dropdown и визуальный AJAX-лоадер.

**Architecture:** Новый `Promokodiki_Filter_Option_Service` вычисляет доступные варианты и нормализованное состояние из полного безопасного контекста. Renderer и AJAX-controller используют один сервис, а клиент получает карточки, состояние и варианты одним ответом и перестраивает существующие `<select>` без второго запроса.

**Tech Stack:** WordPress 7.0.1, PHP 8.1+, WP_Query, transients, admin-ajax, vanilla JavaScript, CSS, WP-CLI test harness, Node.js test runner.

## Global Constraints

- Категория и бренд — одиночные dropdown, пересечение условий выполняется через `AND`.
- В вариантах участвуют только опубликованные и неистёкшие промокоды.
- При несовместимой паре из URL категория сохраняется, бренд сбрасывается.
- Категория без потомков и магазин с не более чем одним связанным брендом не выводят соответствующий dropdown.
- Все магазины используют одно правило; AliExpress не получает специального условия.
- «Популярное за неделю» остаётся эксклюзивным.
- Любой AJAX-запрос показывает loader, сохраняет текущие карточки и поддерживает AbortController/retry.
- Не изменять `wp-content/themes/promokodiki/style.css`: в рабочем дереве находится пользовательское изменение.

---

### Task 1: Сервис совместимых вариантов главной страницы

**Files:**
- Create: `wp-content/plugins/promokodiki-ajax-filter/includes/class-option-service.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-option-service.php`

**Interfaces:**
- Consumes: `Promokodiki_Filter_Context::resolve(string $type, int $object_id): array|WP_Error` and normalized state from `Promokodiki_Filter_State::from_request()`.
- Produces: `Promokodiki_Filter_Option_Service::build(array $context, array $state): array|WP_Error` returning `array{state: array, category_options: array, brand_options: array}`.

- [ ] **Step 1: Write the failing option-service tests**

Create fixtures with two categories, two `shops_category` brands, one compatible pair per promocode, and one expired promocode. Assert the exact IDs:

```php
$category_selected = Promokodiki_Filter_Option_Service::build(
	$context,
	$state( array( 'category_id' => $category_a_id ) )
);
Promokodiki_Filter_Test_Harness::assert_same(
	array( $brand_a_id ),
	array_map( 'intval', wp_list_pluck( $category_selected['brand_options'], 'id' ) )
);

$brand_selected = Promokodiki_Filter_Option_Service::build(
	$context,
	$state( array( 'brand_id' => $brand_b_id ) )
);
Promokodiki_Filter_Test_Harness::assert_same(
	array( $category_b_id ),
	array_map( 'intval', wp_list_pluck( $brand_selected['category_options'], 'id' ) )
);

$incompatible = Promokodiki_Filter_Option_Service::build(
	$context,
	$state( array( 'category_id' => $category_a_id, 'brand_id' => $brand_b_id ) )
);
Promokodiki_Filter_Test_Harness::assert_same( 0, $incompatible['state']['brand_id'] );
Promokodiki_Filter_Test_Harness::assert_same( $category_a_id, $incompatible['state']['category_id'] );
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-option-service.php
```

Expected: FAIL because `Promokodiki_Filter_Option_Service` does not exist.

- [ ] **Step 3: Register and implement the service**

Require the new class before renderer/controller classes:

```php
require_once PROMOKODIKI_FILTER_DIR . 'includes/class-option-service.php';
```

Implement the public contract and category-priority normalization:

```php
final class Promokodiki_Filter_Option_Service {
	public static function build( array $context, array $state ): array|WP_Error {
		if ( 'home' !== $context['type'] ) {
			return array(
				'state'            => $state,
				'category_options' => $context['category_options'],
				'brand_options'    => $context['brand_options'],
			);
		}

		if ( $state['category_id'] && $state['brand_id'] && ! self::pair_exists( $state['category_id'], $state['brand_id'] ) ) {
			$state['brand_id'] = 0;
		}

		return array(
			'state'            => $state,
			'category_options' => self::categories_for_brand( $context, $state['brand_id'] ),
			'brand_options'    => self::brands_for_category( $context, $state['category_id'] ),
		);
	}
}
```

The three private query helpers use `WP_Query` with `fields => ids`, `post_type => promocode`, `post_status => publish`, `posts_per_page => -1`, the same active-expiry meta query as the context, and exact taxonomy clauses. Returned terms are intersected with `allowed_category_ids`/`allowed_brand_ids`, sorted by name, mapped to `id`, `label`, `depth`, and cached under `paf_options_{cache_version}_{category_id}_{brand_id}` for one hour.

- [ ] **Step 4: Run RED test and existing context/query tests**

Run:

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-option-service.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-query-service.php
```

Expected: all assertions PASS; expired-only combinations are absent.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/promokodiki-ajax-filter.php wp-content/plugins/promokodiki-ajax-filter/includes/class-option-service.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-option-service.php
git commit -m "feat: calculate compatible filter options"
```

### Task 2: Контекстные dropdown и исправление брендов всех магазинов

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`

**Interfaces:**
- Consumes: active promocode IDs within category/shop context.
- Produces: unfiltered associated shop terms for shop pages and conditional form controls based on option count.

- [ ] **Step 1: Extend failing context and renderer tests**

Assign an active shop promocode to the current shop plus two unrelated `shops_category` terms and assert all three are returned. Add renderer assertions:

```php
Promokodiki_Filter_Test_Harness::assert_same(
	array( $shop_id, $brand_a_id, $brand_b_id ),
	$context['allowed_brand_ids']
);

$leaf_html = Promokodiki_Filter_Renderer::render( 'category', $leaf_id );
Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_category"', $leaf_html );

$parent_html = Promokodiki_Filter_Renderer::render( 'category', $parent_id );
Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_category"', $parent_html );

$single_brand_html = Promokodiki_Filter_Renderer::render( 'shop', $single_shop_id );
Promokodiki_Filter_Test_Harness::assert_not_contains( 'name="paf_brand"', $single_brand_html );

$multi_brand_html = Promokodiki_Filter_Renderer::render( 'shop', $multi_shop_id );
Promokodiki_Filter_Test_Harness::assert_contains( 'name="paf_brand"', $multi_brand_html );
```

- [ ] **Step 2: Run tests and verify RED**

Run:

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
```

Expected: shop test returns only the current branch; leaf/single dropdown assertions fail.

- [ ] **Step 3: Remove the erroneous shop-branch intersection**

Keep all active associated terms returned by `wp_get_object_terms()` and remove the `get_term_children()`/`array_filter()` block. Normalize deterministic order after term retrieval:

```php
usort(
	$brands,
	static fn( WP_Term $left, WP_Term $right ): int => strnatcasecmp( $left->name, $right->name )
);
```

- [ ] **Step 4: Render controls only when a choice exists**

Replace form conditions with explicit booleans:

```php
$show_category = 'home' === $context['type']
	|| ( 'category' === $context['type'] && count( $context['category_options'] ) > 1 );
$show_brand = 'home' === $context['type']
	|| ( 'shop' === $context['type'] && count( $context['brand_options'] ) > 1 );
```

Use `$show_category` and `$show_brand` around their existing label/select markup. Sorting remains unconditional.

- [ ] **Step 5: Run context and renderer tests**

Run the two commands from Step 2.

Expected: all assertions PASS, including multi-brand terms outside the current taxonomy branch.

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/includes/class-context.php wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-context.php wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
git commit -m "fix: show only meaningful contextual dropdowns"
```

### Task 3: Единый AJAX-ответ, синхронизация dropdown и loader

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js`
- Modify: `wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- Consumes: `Promokodiki_Filter_Option_Service::build()` output.
- Produces: AJAX keys `state`, `category_options`, `brand_options`; DOM hook `[data-filter-loader]`.

- [ ] **Step 1: Write failing payload and loader assertions**

Assert the response contract and rendered markup:

```php
Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['state'] ) );
Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['category_options'] ) );
Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['brand_options'] ) );
Promokodiki_Filter_Test_Harness::assert_contains( 'data-filter-loader', $html );
Promokodiki_Filter_Test_Harness::assert_contains( 'aria-hidden="true"', $html );
```

The static integration test must also assert `filter.js` contains `replaceSelectOptions`, `data-filter-loader`, and `filter.css` contains `@keyframes promokodiki-filter-spin`.

- [ ] **Step 2: Run tests and verify RED**

Run:

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
```

Expected: missing payload and loader assertions FAIL.

- [ ] **Step 3: Use option service in initial and AJAX rendering**

In renderer, build state/options before the query and replace local context options:

```php
$options = Promokodiki_Filter_Option_Service::build( $context, $state );
if ( is_wp_error( $options ) ) {
	return self::error_markup( $options->get_error_message() );
}
$state = $options['state'];
$context['category_options'] = $options['category_options'];
$context['brand_options'] = $options['brand_options'];
```

In AJAX controller do the same before query and append:

```php
'state' => array(
	'category' => (string) $state['category_id'],
	'brand'    => (string) $state['brand_id'],
	'sort'     => $state['sort'],
	'popular'  => (bool) $state['popular'],
),
'category_options' => $options['category_options'],
'brand_options'    => $options['brand_options'],
```

- [ ] **Step 4: Add loader markup and styling**

Inside `.promokodiki-filter`, immediately before results, render:

```php
<div class="promokodiki-filter__loader" data-filter-loader aria-hidden="true" hidden>
	<span class="screen-reader-text"><?php esc_html_e( 'Загрузка…', 'promokodiki-ajax-filter' ); ?></span>
</div>
```

Add CSS:

```css
.promokodiki-filter__loader {
  position: absolute;
  z-index: 5;
  left: 50%;
  top: 84px;
  width: 36px;
  height: 36px;
  margin-left: -18px;
  border: 4px solid rgba(254, 51, 136, 0.2);
  border-top-color: #fe3388;
  border-radius: 50%;
  animation: promokodiki-filter-spin 0.7s linear infinite;
}
.promokodiki-filter__loader[hidden] { display: none; }
@keyframes promokodiki-filter-spin { to { transform: rotate(360deg); } }
```

Render it initially with `hidden`, and toggle both `hidden` and `aria-hidden` from `setLoading()`.

- [ ] **Step 5: Rebuild options after successful filter requests**

Add a DOM-safe helper:

```javascript
function replaceSelectOptions(select, placeholder, options, selected) {
  if (!select) return;
  const fragment = document.createDocumentFragment();
  if (placeholder) fragment.append(new Option(placeholder, '0'));
  options.forEach((item) => fragment.append(new Option(item.label, String(item.id))));
  select.replaceChildren(fragment);
  select.value = String(selected || 0);
}
```

After a non-append response, replace category/brand options, call `applyState(form, json.data.state)`, then call `updateUrl(json.data.state)`. For append requests, leave controls and URL unchanged. In `setLoading`, use:

```javascript
loader.hidden = !loading;
loader.setAttribute('aria-hidden', loading ? 'false' : 'true');
```

- [ ] **Step 6: Run focused tests and Node tests**

Run:

```powershell
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-ajax-service.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-renderer.php
studio wp eval-file wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js
```

Expected: all tests PASS.

- [ ] **Step 7: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/includes/class-renderer.php wp-content/plugins/promokodiki-ajax-filter/includes/class-ajax-controller.php wp-content/plugins/promokodiki-ajax-filter/templates/filter-form.php wp-content/plugins/promokodiki-ajax-filter/assets/js/filter.js wp-content/plugins/promokodiki-ajax-filter/assets/css/filter.css wp-content/plugins/promokodiki-ajax-filter/tests
git commit -m "feat: synchronize filters with ajax loader"
```

### Task 4: Регрессия, браузерная приёмка и эксплуатационная документация

**Files:**
- Modify: `wp-content/plugins/promokodiki-ajax-filter/README.md`
- Test: all files under `wp-content/plugins/promokodiki-ajax-filter/tests/php/`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js`

**Interfaces:**
- Verifies the complete plugin and current theme integration.
- Preserves inactive Filter Everything plugins and active Promokodiki AJAX Filter/WP Rocket state.

- [ ] **Step 1: Document dependent controls and loader**

Add a README section stating that home dropdowns narrow each other, category has URL priority for stale incompatible pairs, leaf category/single-brand controls are hidden, and every AJAX request displays a loader without clearing current cards.

- [ ] **Step 2: Run the complete automated suite**

Run every PHP test and the Node test:

```powershell
$failed = $false
Get-ChildItem wp-content/plugins/promokodiki-ajax-filter/tests/php/*.php | Sort-Object Name | ForEach-Object {
  $output = (& studio wp eval-file $_.FullName 2>&1 | Out-String)
  $output
  if ($output -match 'FAIL |Fatal error|Error: There has been') { $failed = $true }
}
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/filter-state.test.js
if ($LASTEXITCODE -ne 0 -or $failed) { exit 1 }
```

Expected: zero FAIL/Fatal/Error markers and zero Node failures.

- [ ] **Step 3: Run syntax, diff and security checks**

Run PHP lint over all plugin PHP files, `git diff --check`, and scans for legacy Filter Everything handlers and hardcoded secrets. Expected: zero failures and zero matching legacy/security lines in the target integration files.

- [ ] **Step 4: Browser acceptance**

Verify at `https://promokodiki.wp.local/`:

- category selection narrows brands;
- brand-first selection narrows categories and remains selected;
- loader becomes visible during requests and disappears afterward;
- «Показать ещё» appends cards.

Verify one leaf category and one parent category: only the parent page has category dropdown. Verify a single-brand shop and `https://promokodiki.wp.local/shops-category/aliexpress-rucis/`: the first hides the dropdown, while AliExpress shows every active associated brand. Confirm zero browser console errors and no Filter Everything assets.

- [ ] **Step 5: Confirm plugin state**

Run:

```powershell
studio wp plugin list --fields=name,status --format=csv | Select-String -Pattern 'filter-everything|promokodiki-ajax-filter|wp-rocket'
```

Expected: both Filter Everything plugins inactive; Promokodiki AJAX Filter and WP Rocket active.

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/promokodiki-ajax-filter/README.md
git commit -m "docs: describe dependent filter behavior"
git status --short
```

Expected: only the pre-existing user modification `wp-content/themes/promokodiki/style.css` remains unstaged.
