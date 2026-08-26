# Admitad Admin AJAX Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the shared, secure, progressively enhanced AJAX and presentation foundation used by all Admitad admin sections.

**Architecture:** Add focused presenter, request-state, asset, fragment, and AJAX-controller classes while retaining existing page controllers and admin-post fallbacks. A single vanilla-JavaScript entry point owns form/table state, History API synchronization, notices, tooltips, and loading UI.

**Tech Stack:** WordPress 7.0+, PHP 8.4, WordPress AJAX API, vanilla JavaScript, WordPress admin CSS, existing WP-CLI integration harness.

## Global Constraints

- Follow `docs/superpowers/specs/2026-07-28-admitad-admin-ajax-recovery-design.md`.
- Keep internal database status values unchanged.
- Use purpose-specific nonces and existing Admitad capabilities.
- Preserve non-JavaScript GET and admin-post fallbacks.
- Page sizes are exactly 20, 50, and 100; default is 20.
- Do not modify WordPress core or the editorial taxonomy.
- Do not modify the user's existing `wp-content/themes/promokodiki/style.css` changes.

---

### Task 1: Shared Russian Presenter

**Files:**
- Create: `wp-content/plugins/admitad-coupons/admin/class-admin-presenter.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-presenter.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Admin_Presenter::status(string $domain, string $value): array{label:string,class:string}`
- Produces: `Promokodiki_Admitad_Admin_Presenter::term_path(int $term_id): string`
- Produces: `Promokodiki_Admitad_Admin_Presenter::term_options(array $terms): array<int,array{id:int,label:string}>`
- Consumes: WordPress `get_term()` and `promocode_category`.

- [ ] **Step 1: Write failing presenter tests**

```php
Promokodiki_Admitad_Test_Harness::run(
	'admin presenter translates statuses and builds full term paths',
	static function () use ( &$term_ids ): void {
		$parent = wp_insert_term( 'Parent Fixture', 'promocode_category' );
		$child  = wp_insert_term(
			'Child Fixture',
			'promocode_category',
			array( 'parent' => (int) $parent['term_id'] )
		);
		$term_ids = array( (int) $child['term_id'], (int) $parent['term_id'] );

		$status = Promokodiki_Admitad_Admin_Presenter::status( 'rule', 'archived' );
		Promokodiki_Admitad_Test_Harness::assert_same( 'В архиве', $status['label'] );
		Promokodiki_Admitad_Test_Harness::assert_same(
			'Parent Fixture → Child Fixture',
			Promokodiki_Admitad_Admin_Presenter::term_path( (int) $child['term_id'] )
		);
	}
);
```

- [ ] **Step 2: Run the test and verify the class is missing**

Run:

```powershell
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-admin-presenter.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter
```

Expected: failure mentioning `Promokodiki_Admitad_Admin_Presenter`.

- [ ] **Step 3: Implement the presenter**

Implement immutable registries for sync, mapping/profile, rule, review,
confidence, and snapshot domains. Return a neutral `Неизвестный статус
(<value>)` fallback. Build paths iteratively with a visited-ID set and a maximum
depth of 100; return `—` for invalid terms.

- [ ] **Step 4: Load the class before page classes**

Add:

```php
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-presenter.php';
```

before the page-controller `require_once` calls.

- [ ] **Step 5: Run the presenter test**

Expected: all presenter tests pass and fixtures are removed in `finally`.

- [ ] **Step 6: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/class-admin-presenter.php wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/tests/php/test-admin-presenter.php
git commit -m "feat: add Admitad admin presenter"
```

### Task 2: Canonical Admin Request State

**Files:**
- Create: `wp-content/plugins/admitad-coupons/admin/class-admin-request.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-request.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Admin_Request::from_array(array $input, string $page): self`
- Produces: `page(): string`, `paged(): int`, `per_page(): int`, `search(): string`, `filter(string $key): string`
- Produces: `query_args(): array<string,int|string>`
- Produces: `url(): string`

- [ ] **Step 1: Write failing request-state tests**

```php
$request = Promokodiki_Admitad_Admin_Request::from_array(
	array(
		'paged'    => '2',
		'per_page' => '50',
		's'        => '<b>needle</b>',
		'reason'   => 'low_confidence',
	),
	'admitad-history'
);
Promokodiki_Admitad_Test_Harness::assert_same( 2, $request->paged() );
Promokodiki_Admitad_Test_Harness::assert_same( 50, $request->per_page() );
Promokodiki_Admitad_Test_Harness::assert_same( 'needle', $request->search() );
Promokodiki_Admitad_Test_Harness::assert_true(
	str_contains(
		$request->url(),
		'edit.php?post_type=promocode&page=admitad-history&paged=2&per_page=50'
	)
);
```

Also assert invalid `per_page=500` becomes `20`, `paged=0` becomes `1`, and
unknown filters are omitted.

- [ ] **Step 2: Run the test and verify failure**

Expected: missing request-state class.

- [ ] **Step 3: Implement parsing and canonical URL generation**

Use only allowlisted page slugs and filter keys. Build URLs with:

```php
add_query_arg( $this->query_args(), admin_url( 'edit.php' ) );
```

Never use `paginate_links()` without an explicit `base`.

- [ ] **Step 4: Run the request-state test**

Expected: correct canonical URL and bounds.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/class-admin-request.php wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/tests/php/test-admin-request.php
git commit -m "feat: add canonical Admitad admin state"
```

### Task 3: Admin Assets and Accessible Interaction Shell

**Files:**
- Create: `wp-content/plugins/admitad-coupons/admin/class-admin-assets.php`
- Create: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Create: `wp-content/plugins/admitad-coupons/assets/css/admin.css`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-menu.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-assets.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Admin_Assets::register(): void`
- Produces browser events: `admitad:before`, `admitad:success`, `admitad:error`, `admitad:complete`
- Consumes markup contracts: `[data-admitad-ajax]`, `[data-admitad-table]`, `[data-admitad-notices]`, `[data-admitad-tooltip]`.

- [ ] **Step 1: Write failing enqueue-scope tests**

Assert `admin_enqueue_scripts` is registered, assets enqueue only when
`$_GET['post_type'] === 'promocode'` and `$_GET['page']` begins with
`admitad-`, and localized data includes only:

```php
array(
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'nonce'   => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
	'i18n'    => array(
		'loading' => 'Загрузка…',
		'retry'   => 'Повторить',
	),
)
```

- [ ] **Step 2: Run the asset test and verify failure**

- [ ] **Step 3: Implement scoped asset registration**

Enqueue with `ADMITAD_PLUGIN_VERSION`, `array()` dependencies, and footer
loading. Register on plugin boot, not from views.

- [ ] **Step 4: Implement the JavaScript request primitive**

The request primitive must:

```js
async function request(action, payload, signal) {
	const body = new URLSearchParams({ action, _ajax_nonce: config.nonce, ...payload });
	const response = await fetch(config.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body,
		signal,
	});
	const json = await response.json();
	if (!response.ok || !json.success) {
		throw new Error(json?.data?.message || config.i18n.error);
	}
	return json.data;
}
```

Abort superseded table requests, preserve current table content, disable the
submitting button, update notices through an ARIA live region, and restore focus
after fragment replacement.

- [ ] **Step 5: Implement History API and progressive enhancement**

Intercept only forms/links explicitly marked `data-admitad-ajax`. Update URL
from the server-returned canonical URL. On `popstate`, request the state encoded
in `location.search`.

- [ ] **Step 6: Implement CSS**

Include namespaced `.promokodiki-admitad-*` selectors for cards, badges,
tooltips, field help, responsive tables, overlay loaders, notices, focus states,
and `prefers-reduced-motion`.

- [ ] **Step 7: Run asset tests and browser lint checks**

Run PHP test and:

```powershell
node --check wp-content/plugins/admitad-coupons/assets/js/admin.js
```

- [ ] **Step 8: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/class-admin-assets.php wp-content/plugins/admitad-coupons/assets wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/admin/class-admin-menu.php wp-content/plugins/admitad-coupons/tests/php/test-admin-assets.php
git commit -m "feat: add Admitad admin AJAX shell"
```

### Task 4: Shared AJAX Controller and Fragment Responses

**Files:**
- Create: `wp-content/plugins/admitad-coupons/admin/class-admin-fragments.php`
- Create: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-admin-ajax.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Admin_Ajax::register(): void`
- Produces: `Promokodiki_Admitad_Admin_Ajax::dispatch(): void`
- Produces: `Promokodiki_Admitad_Admin_Fragments::render(string $fragment, array $context): string`
- JSON success: `{message:string,html?:string,url?:string,state?:object,progress?:object}`
- JSON error: `{message:string,code:string,fields?:object}`

- [ ] **Step 1: Write failing security and response tests**

Cover:

```php
Promokodiki_Admitad_Test_Harness::assert_true(
	false !== has_action(
		'wp_ajax_promokodiki_admitad_admin',
		array( 'Promokodiki_Admitad_Admin_Ajax', 'dispatch' )
	)
);
```

Exercise a directly callable `handle(array $request)` method and assert invalid
nonce returns `invalid_nonce`, editor access to administrator fragments returns
`forbidden`, and an unknown operation returns `invalid_operation`.

- [ ] **Step 2: Run the AJAX test and verify failure**

- [ ] **Step 3: Implement a testable dispatcher**

`dispatch()` must call `check_ajax_referer()`, delegate sanitized input to
`handle()`, then use `wp_send_json_success()` or `wp_send_json_error()` with
appropriate 400/403/500 status codes. Do not catch and expose raw exception
messages; map exceptions to a Russian generic message and log only sanitized
context.

- [ ] **Step 4: Implement fragment rendering**

Use an allowlist mapping fragment names to files under
`admin/views/partials/`. Extract context variables explicitly; reject unknown
fragment names.

- [ ] **Step 5: Register the controller**

Add `Promokodiki_Admitad_Admin_Ajax::register()` after class loading.

- [ ] **Step 6: Run tests**

Run `test-admin-ajax.php`, `test-admin-security.php`, and
`test-admin-operations.php`.

- [ ] **Step 7: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admin/class-admin-fragments.php wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/tests/php/test-admin-ajax.php
git commit -m "feat: add secure Admitad AJAX controller"
```

### Task 5: Foundation Verification

**Files:**
- Modify if required: files from Tasks 1–4 only.

**Interfaces:**
- Verifies all foundation contracts before page-specific work begins.

- [ ] **Step 1: Run focused tests**

```powershell
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-admin-presenter.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-admin-request.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-admin-assets.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-admin-ajax.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter
```

- [ ] **Step 2: Run the complete integration suite**

```powershell
powershell -ExecutionPolicy Bypass -File wp-content/plugins/admitad-coupons/tests/run-all.ps1 -SitePath .
```

- [ ] **Step 3: Run syntax and style checks**

```powershell
studio wp eval 'echo "WordPress loaded";'
node --check wp-content/plugins/admitad-coupons/assets/js/admin.js
phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist wp-content/plugins/admitad-coupons
```

If `phpcs` is unavailable, record that explicitly and run the repository's
available PHP syntax checks instead.

- [ ] **Step 4: Confirm the worktree contains no unrelated staged changes**

Run `git status --short` and keep the existing theme stylesheet modification
unstaged.
