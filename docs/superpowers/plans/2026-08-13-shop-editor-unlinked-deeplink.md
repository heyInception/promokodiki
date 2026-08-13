# Shop Editor, Unlinked Stores, and Deeplink Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Добавить редактируемые описания и контакты магазинов, административную привязку Campaign ID, список несвязанных магазинов, пакетный генератор Deeplink Admitad и улучшенную подсветку поиска `/shops/`.

**Architecture:** Плагин Admitad остаётся владельцем исходных данных, административных операций и фоновых API-задач; тема только читает нормализованный локальный профиль. Новые сервисы разделяют очистку/контакты, term-editor, диагностику связей и Deeplink, а существующий coordinator запускает ограниченные пакеты без HTTP на публичных страницах.

**Tech Stack:** WordPress 6.4+, PHP 8.1+, term meta, WordPress HTTP/OAuth API, WP-Cron, существующий admin AJAX shell, vanilla JavaScript, Studio CLI integration tests.

## Global Constraints

- Публичные страницы никогда не обращаются к Admitad API.
- Автоматическая связь выполняется только по точному положительному `admitad_campaign_id`; сопоставления по названию и автоматического создания термов нет.
- Ручные значения не перезаписываются синхронизацией.
- Все административные записи требуют `manage_admitad_automation`, отдельный nonce, sanitization и escaping.
- `template-parts/promocode-card.php` и партнёрные ссылки карточек промокодов не изменяются.
- Пользовательское незакоммиченное изменение `wp-content/themes/promokodiki/style.css` не включать ни в один commit.
- Для PHP-тестов использовать только проверенный disposable Studio site; основной сайт не мутировать тестами.

---

## File Structure

- Create `includes/class-shop-content-service.php`: очистка ссылок вместе с текстом, allowlist HTML, извлечение однозначных контактов, заполнение только пустых term fields.
- Modify `includes/class-shop-profile-sync.php`: делегирование content service, хранение source description/audit и вызов локального enrichment.
- Create `admin/class-shop-term-editor.php`: поля edit-term, ограниченный editor, nonce/capability save, manual overrides и ручной Deeplink.
- Create `includes/class-shop-link-audit.php`: классификация несвязанных/дублированных термов и безопасное назначение Campaign ID.
- Create `admin/pages/class-unlinked-shops-page.php` and `admin/views/unlinked-shops.php`: пагинированная страница быстрого связывания.
- Create `includes/class-deeplink-service.php`: fingerprint, API generation, local state, fallback и manual override.
- Modify `includes/class-api-client.php`, `includes/api.php`: OAuth scope и проверяемый endpoint Deeplink.
- Modify `includes/class-sync-coordinator.php`, `includes/class-plugin.php`, `admin/views/sync.php`: ограниченные Deeplink batches и preview totals.
- Modify `wp-content/themes/promokodiki/inc/shops.php`, `taxonomy-shops_category.php`: чтение ручных/автоматических полей и affiliate link priority.
- Modify `wp-content/themes/promokodiki/js/shops.js`; create/update its Node test and a scoped stylesheet loaded only on `/shops/`.

---

### Task 1: Shop Content Sanitization and Contact Enrichment

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-shop-content-service.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-shop-profile-sync.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-content-service.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-profile-sync.php`

**Interfaces:**
- Produces `Promokodiki_Admitad_Shop_Content_Service::sanitize(string $html): string`.
- Produces `extract_contacts(string $html): array{phone:string,email:string,phone_candidates:array,email_candidates:array}`.
- Produces `fill_empty_contacts(int $term_id, array $campaign): array{website:bool,phone:bool,email:bool}`.
- `Shop_Profile_Sync::sync_campaign()` stores `_admitad_shop_source_description`, `_admitad_shop_synced_at`, contact hints and audit metadata.

- [ ] **Step 1: Write failing sanitizer/contact tests**

Add cases asserting that `<a href="https://x.test">удаляемый текст</a>` disappears entirely, allowed headings/lists remain, scripts/forms/images disappear, one valid `mailto:`-free email and one RU phone are extracted, ambiguous candidates remain hints, and address is never inferred.

```php
$clean = Promokodiki_Admitad_Shop_Content_Service::sanitize(
	'<p>До <a href="https://x.test">удалить меня</a> после</p><ul><li>Пункт</li></ul>'
);
assert_same( '<p>До  после</p><ul><li>Пункт</li></ul>', $clean );

$contacts = Promokodiki_Admitad_Shop_Content_Service::extract_contacts(
	'<p>Телефон: +7 (495) 123-45-67. Email: help@example.test</p>'
);
assert_same( '+7 (495) 123-45-67', $contacts['phone'] );
assert_same( 'help@example.test', $contacts['email'] );
```

- [ ] **Step 2: Run RED**

Run `studio wp --path <disposable-site> eval-file <worktree>/wp-content/plugins/admitad-coupons/tests/php/test-shop-content-service.php --skip-plugins=admitad-coupons,promokodiki-ajax-filter`.

Expected: FAIL because `Promokodiki_Admitad_Shop_Content_Service` does not exist.

- [ ] **Step 3: Implement the content service**

Remove `<a>...</a>` with `preg_replace_callback`/DOM-safe preprocessing before `wp_kses`; allow only `p`, `br`, `h2`–`h4`, `ul`, `ol`, `li`, `strong`, `em`. Normalize contacts, accept exactly one unique valid candidate, and write only empty `website`, `phone`, `email` ACF/term-meta targets. Store all candidates as `_admitad_shop_contact_hints`; never synthesize address.

- [ ] **Step 4: Integrate profile sync and verify GREEN**

Make `Shop_Profile_Sync` use the service, preserve non-empty manual fields, update source description independently, and store `_admitad_shop_audit` as `{updated_at,user_id:0,source:'admitad'}`. Run the new test plus `test-shop-profile-sync.php`.

- [ ] **Step 5: Commit**

Commit `feat: sanitize Admitad shop content and contacts`.

---

### Task 2: Secure Shop Term Editor

**Files:**
- Create: `wp-content/plugins/admitad-coupons/admin/class-shop-term-editor.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-assets.php`
- Modify: `wp-content/plugins/admitad-coupons/assets/js/admin.js`
- Modify: `wp-content/plugins/admitad-coupons/assets/css/admin.css`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-term-editor.php`
- Test: `wp-content/plugins/admitad-coupons/tests/js/test-admin-shell.cjs`

**Interfaces:**
- Produces `Promokodiki_Admitad_Shop_Term_Editor::register(): void`.
- Saves `admitad_campaign_id`, `_admitad_shop_manual_description`, manual contacts, `_admitad_shop_manual_affiliate_url`, and `_admitad_shop_manual_audit`.
- Consumes `Shop_Content_Service::sanitize()` and later `Deeplink_Service::queue_term(int $term_id)` when available.

- [ ] **Step 1: Write failing render/save tests**

Render `shops_category_edit_form_fields` as an administrator and assert the visible Campaign ID, source read-only block, constrained `wp_editor`, copy/reset controls, contact fields, Deeplink status/manual URL and nonce. Assert editor role cannot save; invalid nonce cannot save; valid administrator save sanitizes HTML and URL and records user/time/source.

- [ ] **Step 2: Run RED**

Expected: FAIL because editor hooks and fields are absent.

- [ ] **Step 3: Implement editor UI and persistence**

Register only taxonomy edit hooks. Configure `wp_editor` without media buttons and link toolbar; sanitize again server-side so pasted links and their text are removed. Keep reset explicit via a dedicated checkbox/action, not an empty transport ambiguity. Save Campaign ID only after positive-integer validation; full existence/uniqueness validation is delegated to Task 3 service.

- [ ] **Step 4: Add copy/reset JS behavior and verify GREEN**

Use text/HTML already embedded in the admin DOM; no remote fetch. Add accessible status messages. Run PHP test and `node --test .../test-admin-shell.cjs`.

- [ ] **Step 5: Commit**

Commit `feat: add Admitad shop term editor`.

---

### Task 3: Unlinked Shop Audit and Quick Assignment

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-shop-link-audit.php`
- Create: `wp-content/plugins/admitad-coupons/admin/pages/class-unlinked-shops-page.php`
- Create: `wp-content/plugins/admitad-coupons/admin/views/unlinked-shops.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-menu.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-ajax.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-link-audit.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-admin-mapping-ajax.php`

**Interfaces:**
- Produces `audit(array $args): array{items:array,total:int,pages:int}` with reason codes `missing`, `invalid`, `unknown`, `duplicate`.
- Produces `assign(int $term_id,int $campaign_id,int $user_id): true|WP_Error`.
- On success calls `Shop_Profile_Sync::sync_campaign($campaign)` and schedules logo/Deeplink work without external HTTP.

- [ ] **Step 1: Write failing audit tests**

Create term fixtures for all four reasons and one valid unique link. Assert server pagination/search, reason labels, campaign existence and duplicate rejection. Assert `assign()` leaves the old value untouched on error and immediately enriches from `Reference_Repository::campaign()` on success.

- [ ] **Step 2: Run RED**

Expected: FAIL because audit service/page/operation do not exist.

- [ ] **Step 3: Implement repository-style audit and assignment**

Fetch bounded term pages, precompute duplicate Campaign IDs, and query the local company profile table rather than Admitad. Validate term taxonomy, capability at controller boundary, campaign existence and uniqueness before `update_term_meta`. Store manual audit and enqueue bounded background work.

- [ ] **Step 4: Implement page and protected AJAX save**

Add `admitad-unlinked-shops` to `section_capabilities()` and routing. Reuse existing campaign autocomplete endpoint and admin AJAX fragment conventions. Each row has label, reason, hidden stable campaign ID, save, and `get_edit_term_link()`.

- [ ] **Step 5: Run GREEN and commit**

Run audit, admin security, admin mapping AJAX and menu tests. Commit `feat: manage unlinked Admitad shops`.

---

### Task 4: Deeplink API and Local State

**Files:**
- Create: `wp-content/plugins/admitad-coupons/includes/class-deeplink-service.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-api-client.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/api.php`
- Modify: `wp-content/plugins/admitad-coupons/admitad-coupons.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-deeplink-service.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-api-normalizers.php`

**Interfaces:**
- Produces `Api_Client::deeplink(int $campaign_id,string $ulp,string $subid='shop_page'): array|WP_Error`.
- Produces `Deeplink_Service::fingerprint(int,string,string): string`.
- Produces `process_term(int $term_id): array{state:string,url:string}` and `resolved_url(int $term_id,string $site_url): string`.
- Stores automatic URL/status/fingerprint/attempt/success timestamps without replacing `_admitad_shop_manual_affiliate_url`.

- [ ] **Step 1: Write failing API contract tests**

Inject HTTP/token providers and assert endpoint `/deeplink/{website}/advcampaign/{campaign}/`, repeated `ulp`, `subid=shop_page`, Authorization header, one forced refresh after 401, response list validation, and safe error status.

- [ ] **Step 2: Run RED**

Expected: FAIL because `deeplink()` and service are absent and OAuth scope lacks `deeplink_generator`.

- [ ] **Step 3: Implement API method and OAuth scope**

Add `deeplink_generator` to the exact token scope. Generalize API decode only enough to support list response without weakening page validation. Restrict `ulp` and returned link to HTTP(S).

- [ ] **Step 4: Write failing state/fallback tests**

Assert unchanged fingerprint skips HTTP, changed Website/Campaign/site queues regeneration, old ready URL survives transient error, unsupported falls back to site URL, and manual URL always wins.

- [ ] **Step 5: Implement service, run GREEN, commit**

Use term meta for local state and redacted error codes. Commit `feat: generate Admitad shop deeplinks`.

---

### Task 5: Bounded Deeplink Queue and Enrichment Preview

**Files:**
- Modify: `wp-content/plugins/admitad-coupons/includes/class-sync-coordinator.php`
- Modify: `wp-content/plugins/admitad-coupons/includes/class-plugin.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/class-admin-actions.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/pages/class-sync-page.php`
- Modify: `wp-content/plugins/admitad-coupons/admin/views/sync.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-sync-coordinator.php`
- Test: `wp-content/plugins/admitad-coupons/tests/php/test-shop-enrichment-admin.php`

**Interfaces:**
- Coordinator adds a resumable `deeplink` batch after profile/logo preparation.
- Preview adds `deeplinks => {create,update,unchanged,unsupported}`.
- Single-term regeneration validates nonce/capability, marks `queued`, and schedules one bounded event.

- [ ] **Step 1: Write failing coordinator/preview tests**

Assert no Deeplink batch runs before explicit first enrichment; batches process a configured maximum and resume cursor; preview makes no HTTP call; completed/unchanged terms are skipped; failed items do not block later terms.

- [ ] **Step 2: Run RED**

Expected: FAIL on missing preview keys, hooks and continuation state.

- [ ] **Step 3: Implement bounded queue integration**

Reuse existing run/lock/cursor primitives. Store only IDs/cursors in scheduled arguments. Ensure Website ID changes invalidate fingerprints lazily rather than scheduling an unbounded event list.

- [ ] **Step 4: Extend admin controls and run GREEN**

Render new counters and OAuth reauthorization notice when the cached token scope lacks `deeplink_generator`. Add protected one-term regeneration action. Run coordinator/admin tests.

- [ ] **Step 5: Commit**

Commit `feat: queue shop deeplink generation`.

---

### Task 6: Theme Profile and Affiliate Site Link

**Files:**
- Modify: `wp-content/themes/promokodiki/inc/shops.php`
- Modify: `wp-content/themes/promokodiki/taxonomy-shops_category.php`
- Test: `wp-content/plugins/promokodiki-ajax-filter/tests/php/test-theme-integration.php`

**Interfaces:**
- `promokodiki_shop_profile(WP_Term): array` adds resolved address/phone/email/site display URL/affiliate URL while preserving ACF → manual → Admitad → WordPress precedence per field.
- Taxonomy template uses affiliate URL only for `.promocodes__shop-site`.

- [ ] **Step 1: Write failing profile/output tests**

Assert manual description wins, reset falls back to cleaned Admitad source, contacts resolve independently, manual affiliate URL wins over automatic, automatic wins over direct site, displayed text is the direct host, and rendered link contains `target="_blank" rel="nofollow sponsored noopener noreferrer"`.

- [ ] **Step 2: Run RED**

Expected: FAIL on missing keys/priority and missing `sponsored` relation.

- [ ] **Step 3: Implement local-only profile resolution and template output**

Read only term fields/meta; never instantiate the API client. Keep all catalogue/internal links unchanged. Suppress empty contact rows.

- [ ] **Step 4: Run GREEN and commit**

Run theme integration and source-diff assertion for `template-parts/promocode-card.php`. Commit `feat: render Admitad shop affiliate links`.

---

### Task 7: `/shops/` Highlighting and Group Visibility

**Files:**
- Modify: `wp-content/themes/promokodiki/js/shops.js`
- Create: `wp-content/themes/promokodiki/css/shops.css`
- Modify: `wp-content/themes/promokodiki/inc/shops.php`
- Modify: `wp-content/plugins/admitad-coupons/tests/js/test-shops-search.cjs`

**Interfaces:**
- JS exports pure `highlightParts(label,query): Array<{text:string,match:boolean}>` for behavior testing.
- DOM filter rebuilds item text with `textContent` and created `<mark class="alphabetical__match">`; no `innerHTML` from data.

- [ ] **Step 1: Write failing behavior tests**

Assert Cyrillic/case-insensitive matching, hiding all nonmatching items, hiding a group whose children are all hidden, retaining `.alphabetical__name` unchanged, multiple safe text nodes around the first/all matches (choose all non-overlapping matches), and exact restoration after clearing.

- [ ] **Step 2: Run RED**

Run `node --test wp-content/plugins/admitad-coupons/tests/js/test-shops-search.cjs`.

Expected: FAIL because highlight parts and mark rendering are absent.

- [ ] **Step 3: Implement safe highlighting and scoped style**

Cache original label in JS state, use locale-normalized index mapping for matching, create DOM nodes without HTML injection, and set `.alphabetical__match { color: #fe1477; background: transparent; }`. Enqueue `css/shops.css` only with the existing shops asset condition.

- [ ] **Step 4: Run GREEN and commit**

Run both theme JS suites and PHP asset integration. Commit `feat: highlight shop catalogue search`.

---

### Task 8: Operations Documentation and Full Verification

**Files:**
- Modify: `docs/admitad-automation-operations.md`
- Verify: all touched plugin/theme/test files

- [ ] **Step 1: Update administrator instructions**

Document reauthorization for `deeplink_generator`, term editor fields, manual/source description behavior, contact fill-only-empty rule, unlinked reasons and assignment, preview counters, one-term regeneration, fallback and SubID `shop_page`.

- [ ] **Step 2: Run complete PHP integration suite**

Run `powershell -ExecutionPolicy Bypass -File wp-content/plugins/admitad-coupons/tests/run-all.ps1 -SitePath <disposable-site>`.

Expected: `All Admitad integration tests passed`.

- [ ] **Step 3: Run all JS suites**

Run bundled Node with:

```powershell
node --test wp-content/plugins/admitad-coupons/tests/js/*.cjs
node --test wp-content/plugins/promokodiki-ajax-filter/tests/js/*.test.js
```

Expected: zero failures.

- [ ] **Step 4: Run theme/filter PHP integration**

Execute every `test-*.php` through the disposable site, ensuring test isolation does not persist theme or taxonomy settings.

- [ ] **Step 5: Run syntax/static checks**

Parse every touched PHP file with Studio PHP `TOKEN_PARSE`, run `git diff --check`, confirm no diff for `template-parts/promocode-card.php` and no staged diff for `style.css`.

- [ ] **Step 6: Request independent code review**

Review the complete diff against the approved design, specifically capability/nonce paths, uniqueness races, OAuth scope, no-public-HTTP guarantee, editor sanitization and queue bounds. Fix Critical/Important findings through new failing tests.

- [ ] **Step 7: Repeat fresh verification and commit**

Repeat Steps 2–5 after review fixes. Commit `docs: document shop editing and deeplinks` or a precise final-fix message.

## Completion Checklist

- [ ] Empty alphabetical groups disappear and only item matches use `#fe1477`.
- [ ] Link elements and their text never enter either displayed description.
- [ ] Source and manual descriptions remain independent and resettable.
- [ ] Campaign ID is visible/editable with strict local validation.
- [ ] Website/phone/email fill only empty fields; address remains manual.
- [ ] All four unlinked reasons and quick assignment work without API calls.
- [ ] Deeplink uses `ulp=site_url`, `subid=shop_page`, bounded background work and safe fallback.
- [ ] Manual affiliate URL wins and public shop pages make zero Admitad requests.
- [ ] Existing coupon cards, user `style.css`, and unrelated worktrees remain untouched.
- [ ] Fresh full PHP/JS/syntax/static verification is green.
