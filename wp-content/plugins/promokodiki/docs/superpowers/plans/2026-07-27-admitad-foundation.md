# Admitad Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the tested modular plugin bootstrap, secure configuration, roles, and versioned database schema without changing current import behavior.

**Architecture:** New `Promokodiki_Admitad_*` classes load beside the legacy functions. Activation becomes idempotent and installs capabilities plus seven prefixed custom tables. Legacy tables and imported content remain untouched.

**Tech Stack:** WordPress 6.4+, PHP 8.1+, `$wpdb`, `dbDelta`, WP-CLI through `studio wp`, WordPress Coding Standards.

## Global Constraints

- Do not modify WordPress core, the site category hierarchy, or `wp-content/themes/promokodiki/style.css`.
- Do not delete or rename legacy Admitad tables in this phase.
- Do not expose credentials or tokens in tests or output.
- Use `$wpdb->prefix`; remain compatible with local SQLite translation and production MySQL/MariaDB.
- Use TDD: observe each new test fail before implementation.
- Design source: `docs/superpowers/specs/2026-07-27-admitad-automation-category-mapping-design.md`.

---

## File Structure

- `admitad-coupons.php` — constants, focused requires, activation/deactivation, boot.
- `includes/class-plugin.php` — hook registration only.
- `includes/class-config.php` — defaults, constant precedence, sanitized settings.
- `includes/class-capabilities.php` — administrator/editor capabilities.
- `includes/class-schema.php` — table names, `dbDelta`, schema version.
- `includes/class-activator.php` — activation orchestration.
- `tests/harness.php` — WP-CLI assertions and fixture cleanup.
- `tests/php/test-bootstrap.php` — bootstrap and hook registration.
- `tests/php/test-config-capabilities.php` — defaults, credentials precedence, role permissions.
- `tests/php/test-schema.php` — idempotent schema installation.
- `phpcs.xml.dist` — WPCS for production plugin code.

### Task 1: Test Harness and Modular Bootstrap

**Files:**
- Create: `wp-content/plugins/admitad-coupons/tests/harness.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-bootstrap.php`
- Create: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Plugin::boot(): void`
- Produces: `Promokodiki_Admitad_Test_Harness::{run,assert_same,assert_true,finish}()`

- [ ] **Step 1: Write the failing bootstrap test**

```php
require_once dirname( __DIR__ ) . '/harness.php';
require_once WP_PLUGIN_DIR . '/admitad-coupons/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'plugin registers core hooks',
	static function (): void {
		Promokodiki_Admitad_Test_Harness::assert_true( class_exists( 'Promokodiki_Admitad_Plugin' ) );
		Promokodiki_Admitad_Test_Harness::assert_true(
			false !== has_action( 'init', array( 'Promokodiki_Admitad_Plugin', 'register' ) )
		);
	}
);
Promokodiki_Admitad_Test_Harness::finish();
```

- [ ] **Step 2: Run it and verify RED**

Run: `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-bootstrap.php`

Expected: FAIL because `Promokodiki_Admitad_Plugin` is not defined.

- [ ] **Step 3: Add the minimal modular bootstrap**

```php
final class Promokodiki_Admitad_Plugin {
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ), 0 );
	}

	public static function register(): void {
		admitad_register_content_types();
	}
}
```

Load the class from the main plugin file and call `Promokodiki_Admitad_Plugin::boot()`. Remove only duplicate hook registration now owned by the class; retain legacy importer/admin requires until later phases.

- [ ] **Step 4: Run the test and syntax check**

Run:

```powershell
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-bootstrap.php
Get-ChildItem wp-content/plugins/admitad-coupons -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Expected: bootstrap test PASS; every syntax check reports no errors.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/includes/class-plugin.php wp-content/plugins/admitad-coupons/tests
git commit -m "refactor: add modular Admitad plugin bootstrap"
```

### Task 2: Secure Configuration and Capabilities

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-config.php`
- Create: `wp-content/plugins/admitad-coupons/includes/class-capabilities.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-config-capabilities.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Config::get(string $key): mixed`
- Produces: `Promokodiki_Admitad_Config::sanitize(array $input): array`
- Produces: `Promokodiki_Admitad_Capabilities::install(): void`
- Produces capabilities: `manage_admitad_automation`, `review_admitad_mapping`

- [ ] **Step 1: Write failing config and role tests**

```php
$defaults = Promokodiki_Admitad_Config::defaults();
Promokodiki_Admitad_Test_Harness::assert_same( 3600, $defaults['coupon_interval'] );
Promokodiki_Admitad_Test_Harness::assert_same( 2, $defaults['missing_threshold'] );
Promokodiki_Admitad_Test_Harness::assert_same( 3, $defaults['max_categories'] );
Promokodiki_Admitad_Test_Harness::assert_same( true, $defaults['auto_tags'] );

Promokodiki_Admitad_Capabilities::install();
Promokodiki_Admitad_Test_Harness::assert_true(
	get_role( 'administrator' )->has_cap( 'manage_admitad_automation' )
);
Promokodiki_Admitad_Test_Harness::assert_true(
	get_role( 'editor' )->has_cap( 'review_admitad_mapping' )
);
Promokodiki_Admitad_Test_Harness::assert_true(
	! get_role( 'editor' )->has_cap( 'manage_admitad_automation' )
);
```

- [ ] **Step 2: Run it and verify RED**

Run: `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-config-capabilities.php`

Expected: FAIL because the classes do not exist.

- [ ] **Step 3: Implement defaults, safe bounds, and role installation**

```php
public static function defaults(): array {
	return array(
		'coupon_interval'       => HOUR_IN_SECONDS,
		'reference_interval'    => DAY_IN_SECONDS,
		'reconcile_interval'    => DAY_IN_SECONDS,
		'batch_size'            => 200,
		'max_retries'           => 3,
		'retry_base_seconds'    => 30,
		'missing_threshold'     => 2,
		'confidence_high'       => 80,
		'confidence_medium'     => 50,
		'weight_coupon_category' => 100,
		'weight_campaign'       => 60,
		'weight_company'        => 40,
		'weight_title'          => 20,
		'weight_description'    => 10,
		'max_categories'        => 3,
		'candidate_evidence'    => 5,
		'candidate_campaigns'   => 2,
		'candidate_conflicts'   => 0,
		'auto_tags'             => true,
		'email_alerts'          => true,
		'email_recipient'       => sanitize_email( get_option( 'admin_email', '' ) ),
		'queue_warning_count'   => 25,
		'editor_review_enabled' => true,
		'log_retention_days'    => 90,
	);
}
```

`get()` must prefer `PROMOKODIKI_ADMITAD_CLIENT_ID`, `PROMOKODIKI_ADMITAD_CLIENT_SECRET`, and `PROMOKODIKI_ADMITAD_WEBSITE_ID` over options. `sanitize()` must clamp intervals, batch size, thresholds, category count, and retention to documented safe bounds.

- [ ] **Step 4: Run tests**

Run:

```powershell
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-config-capabilities.php
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-bootstrap.php
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```powershell
git add wp-content/plugins/admitad-coupons/includes/class-config.php wp-content/plugins/admitad-coupons/includes/class-capabilities.php wp-content/plugins/admitad-coupons/includes/class-plugin.php wp-content/plugins/admitad-coupons/admitad-coupons.php wp-content/plugins/admitad-coupons/tests/php/test-config-capabilities.php
git commit -m "feat: add Admitad configuration and capabilities"
```

### Task 3: Versioned Schema and Activation

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-schema.php`
- Create: `wp-content/plugins/admitad-coupons/includes/class-activator.php`
- Create: `wp-content/plugins/admitad-coupons/tests/php/test-schema.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/db.php`

**Interfaces:**
- Produces: `Promokodiki_Admitad_Schema::install(): void`
- Produces: `Promokodiki_Admitad_Schema::table(string $name): string`
- Produces: `Promokodiki_Admitad_Activator::activate(): void`
- Stores option: `promokodiki_admitad_db_version=4`

- [ ] **Step 1: Write the failing idempotent schema test**

```php
Promokodiki_Admitad_Schema::install();
Promokodiki_Admitad_Schema::install();

foreach ( array( 'category_map', 'company_profile', 'company_category', 'rule', 'review_queue', 'sync_run', 'classification_history' ) as $suffix ) {
	$table = Promokodiki_Admitad_Schema::table( $suffix );
	Promokodiki_Admitad_Test_Harness::assert_same(
		$table,
		$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
	);
}
Promokodiki_Admitad_Test_Harness::assert_same( '4', get_option( 'promokodiki_admitad_db_version' ) );
```

Also assert that `promocode_category` term counts and the three legacy table row counts are unchanged before/after installation.

- [ ] **Step 2: Run it and verify RED**

Run: `studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-schema.php`

Expected: FAIL because schema class/tables do not exist.

- [ ] **Step 3: Implement schema and activation**

Use `dbDelta()` for the seven tables exactly specified in the design. Required indexes include external namespace/category IDs, campaign IDs, rule status/term, unresolved queue dedupe keys, run status/heartbeat, and history post/timestamp.

Use `site_term_id BIGINT UNSIGNED NOT NULL DEFAULT 0` for an unmapped synchronized external category. The first real mapping removes the zero-term reference row; mapped rows retain the unique key `(source_namespace, external_category_id, site_term_id)`.

```php
final class Promokodiki_Admitad_Activator {
	public static function activate(): void {
		admitad_register_content_types();
		Promokodiki_Admitad_Schema::install();
		Promokodiki_Admitad_Capabilities::install();
		admitad_schedule_events();
		flush_rewrite_rules();
	}
}
```

Retain `admitad_create_tables()` as a compatibility wrapper calling the new installer; do not drop old tables.

- [ ] **Step 4: Run schema, bootstrap, and WPCS checks**

Run:

```powershell
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-schema.php
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-bootstrap.php
studio wp eval-file wp-content/plugins/admitad-coupons/tests/php/test-config-capabilities.php
```

Expected: all tests PASS.

- [ ] **Step 5: Add WPCS and CI coverage**

Create `wp-content/plugins/admitad-coupons/phpcs.xml.dist` using WordPress, WordPress-Docs, and WordPress-Extra; exclude `tests/*`; set PHP 8.1-compatible code and WordPress 6.4 minimum. Extend `.github/workflows/ci.yml` to run PHPCS against both project plugins.

Run: `phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist wp-content/plugins/admitad-coupons`

Expected: zero errors.

- [ ] **Step 6: Commit**

```powershell
git add .github/workflows/ci.yml wp-content/plugins/admitad-coupons
git commit -m "feat: add versioned Admitad automation schema"
```

## Phase Gate

Run every Admitad test, syntax check, and PHPCS:

```powershell
Get-ChildItem wp-content/plugins/admitad-coupons/tests/php/*.php | Sort-Object Name | ForEach-Object { studio wp eval-file $_.FullName }
Get-ChildItem wp-content/plugins/admitad-coupons -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
phpcs --standard=wp-content/plugins/admitad-coupons/phpcs.xml.dist wp-content/plugins/admitad-coupons
```

Expected: all tests PASS, no syntax errors, no PHPCS errors, legacy content/rules unchanged.
