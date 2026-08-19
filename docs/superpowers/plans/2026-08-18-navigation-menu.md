# Navigation Menu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one-level promocode categories to desktop navigation and deliver the approved full-panel mobile menu with an administrator-controlled initial state.

**Architecture:** A focused theme module will query and render navigation data, while `header.php` supplies the existing WordPress menu as data to that renderer. The current navigation script will become the state controller, and isolated overrides will implement the responsive Figma layout without touching the user's `style.css` edits.

**Tech Stack:** WordPress classic theme, PHP, WordPress Customizer, vanilla JavaScript, CSS.

**Spec:** `docs/superpowers/specs/2026-08-18-navigation-menu-design.md`

## Global Constraints

- Work directly on `main` as explicitly requested.
- Do not modify or overwrite the user's existing `style.css` changes.
- Do not add runtime dependencies.
- Keep existing primary-menu content administered by WordPress.
- Escape all rendered labels, URLs, attributes, and image sources.
- Use exactly one taxonomy level and `hide_empty => true`.

---

### Task 1: Navigation rendering contract

**Files:**
- Create: `wp-content/themes/promokodiki/tests/navigation-menu.php`
- Create: `wp-content/themes/promokodiki/inc/navigation-menu.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Modify: `wp-content/themes/promokodiki/header.php`

**Interfaces:**
- Produces: `promokodiki_get_menu_categories(): array`, `promokodiki_render_primary_navigation(array $args = array()): string`.
- Consumes: root `promocode_category` terms and the assigned `menu-1` WordPress menu.

- [ ] Write a failing PHP contract test proving root-only alphabetical category rendering, fallback images, separate link/toggle controls, existing menu preservation, and mobile-only favorite markup.
- [ ] Run `php wp-content/themes/promokodiki/tests/navigation-menu.php` and confirm it fails because the module does not exist.
- [ ] Implement the minimal module, include it from `functions.php`, and replace header navigation markup with the renderer.
- [ ] Re-run the contract test and confirm it passes.

### Task 2: Customizer option

**Files:**
- Modify: `wp-content/themes/promokodiki/tests/navigation-menu.php`
- Modify: `wp-content/themes/promokodiki/inc/customizer.php`
- Modify: `wp-content/themes/promokodiki/inc/navigation-menu.php`

**Interfaces:**
- Produces: theme mod `promokodiki_mobile_categories_expanded`, defaulting to `true`.
- Consumes: the theme mod when rendering initial `aria-expanded` and submenu visibility.

- [ ] Extend the contract test so disabled and default settings produce the correct initial markup.
- [ ] Run the test and confirm the new assertion fails for the missing setting behavior.
- [ ] Register a sanitized Customizer checkbox in a `Мобильное меню` section and consume it in the renderer.
- [ ] Re-run the contract test and confirm it passes.

### Task 3: Responsive interactions and visual design

**Files:**
- Modify: `wp-content/themes/promokodiki/js/navigation.js`
- Modify: `wp-content/themes/promokodiki/assets/css/overrides.css`
- Create: `wp-content/themes/promokodiki/img/menu/menu-shops.svg`
- Create: `wp-content/themes/promokodiki/img/menu/menu-promocodes.svg`
- Create: `wp-content/themes/promokodiki/img/menu/menu-discounts.svg`
- Create: `wp-content/themes/promokodiki/img/menu/menu-rating.svg`
- Create: `wp-content/themes/promokodiki/img/menu/menu-about.svg`

**Interfaces:**
- Consumes: renderer classes and ARIA attributes from Task 1.
- Produces: desktop dropdown behavior, mobile full panel, submenu toggle state, body scroll lock, and platform-specific favorite help.

- [ ] Add a failing JavaScript contract test for opening/closing the panel, independent submenu toggling, Escape, link-close behavior, and mobile favorite instructions.
- [ ] Run the test and confirm it fails against the current script.
- [ ] Implement the state controller and neutral replaceable SVG placeholders.
- [ ] Add scoped desktop/mobile styles at the 1229px breakpoint, including reduced-motion handling.
- [ ] Re-run the JavaScript and PHP tests and confirm they pass.

### Task 4: Verification

**Files:**
- Verify all changed files.

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces: evidence that the feature is safe to hand off.

- [ ] Run PHP syntax checks on every changed PHP file.
- [ ] Run the theme PHP contract tests.
- [ ] Run JavaScript lint or syntax validation available in the repository.
- [ ] Run PHPCS when the local dependency is available; otherwise record the exact unavailable command.
- [ ] Inspect `git diff --check`, `git diff`, and `git status --short` to confirm scope and preserve the pre-existing `style.css` changes.

