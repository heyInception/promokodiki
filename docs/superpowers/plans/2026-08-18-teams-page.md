# Teams Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `/teams/` as an ACF-managed page with section descriptions and avatar-only members.

**Architecture:** A dedicated `page-teams.php` calls functions from a new `inc/teams-page.php` module. The module locally registers ACF fields, returns empty values without ACF, filters incomplete records, and renders escaped markup. CSS is scoped to `.teams-page` in the existing override stylesheet.

**Tech Stack:** WordPress classic PHP theme, ACF local PHP registration, CSS, standalone PHP contract test.

**Spec:** `docs/superpowers/specs/2026-08-18-teams-page-design.md`

## Global Constraints

- Target manually assigned `page-teams.php`; do not change core or coupon components.
- Preserve pre-existing changes in `style.css`; add new styles only to `assets/css/overrides.css`.
- Never require ACF for frontend execution and never render placeholders.
- Omit a member lacking either a name or an image; omit sections without valid members.
- Names stay visually hidden and serve as `alt` text and linked-avatar `aria-label`.
- Use `wp_kses_post()`, `esc_html()`/`esc_attr()`, and `esc_url()` at their matching output boundaries.

## File Structure

- Create `wp-content/themes/promokodiki/tests/teams-page.php`: rendering contract.
- Create `wp-content/themes/promokodiki/inc/teams-page.php`: getters, renderer, and ACF fields.
- Modify `wp-content/themes/promokodiki/functions.php`: require the module.
- Create `wp-content/themes/promokodiki/page-teams.php`: selectable page template.
- Modify `wp-content/themes/promokodiki/assets/css/overrides.css`: layout and responsive styles.

### Task 1: Rendering contract

**Files:**
- Create: `wp-content/themes/promokodiki/tests/teams-page.php`

**Interfaces:**
- Consumes `promokodiki_render_teams_page( array $sections, array $data ): string`.
- Produces a `php tests/teams-page.php` test script.

- [ ] **Step 1: Write a failing test for a complete section, linked/unlinked avatars, and omitted invalid data.**

```php
<?php
require_once dirname( __DIR__ ) . '/inc/teams-page.php';
$html = promokodiki_render_teams_page(
	array(
		array(
			'title' => 'Редакция',
			'description' => '<p>Ищем лучшие предложения.</p>',
			'members' => array(
				array( 'name' => 'Анна', 'photo' => array( 'url' => '/anna.jpg' ), 'url' => '/authors/anna/' ),
				array( 'name' => 'Иван', 'photo' => array( 'url' => '/ivan.jpg' ), 'url' => '' ),
				array( 'name' => '', 'photo' => array( 'url' => '/invalid.jpg' ) ),
			),
		),
		array( 'title' => 'Пустая секция', 'members' => array() ),
	),
	array( 'title' => 'Наша команда', 'introduction' => '<p>Знакомьтесь.</p>' )
);
```

Add local assert helpers like `tests/faq-page.php`; assert the `h1`, introduction, `h2`, linked `/authors/anna/` avatar and `aria-label="Анна"`, the unlinked `/ivan.jpg` image with `alt="Иван"`, and absence of `Пустая секция` and `invalid.jpg`.

- [ ] **Step 2: Run `php tests/teams-page.php`; expect failure because `inc/teams-page.php` is absent.**
- [ ] **Step 3: Commit with `git add wp-content/themes/promokodiki/tests/teams-page.php` then `git commit -m "test: define teams page rendering contract"`.**

### Task 2: ACF model and safe renderer

**Files:**
- Create: `wp-content/themes/promokodiki/inc/teams-page.php`
- Modify: `wp-content/themes/promokodiki/functions.php`
- Test: `wp-content/themes/promokodiki/tests/teams-page.php`

**Interfaces:**
- Produces `promokodiki_get_teams_page_data( $post_id )`, `promokodiki_get_teams_page_sections( $post_id )`, `promokodiki_render_teams_page( $sections, $data )`, and `promokodiki_register_teams_page_fields()`.

- [ ] **Step 1: Implement ACF-tolerant data getters.**

```php
function promokodiki_get_teams_page_data( $post_id ) {
	$data = array( 'title' => get_the_title( $post_id ), 'introduction' => '' );
	if ( function_exists( 'get_field' ) ) {
		$data['introduction'] = get_field( 'teams_page_introduction', $post_id );
	}
	return $data;
}

function promokodiki_get_teams_page_sections( $post_id ) {
	$sections = function_exists( 'get_field' ) ? get_field( 'teams_page_sections', $post_id ) : array();
	return is_array( $sections ) ? $sections : array();
}
```

- [ ] **Step 2: Render only validated records.**

```php
$valid_members = array_filter(
	$members,
	static function ( $member ) {
		return ! empty( $member['name'] ) && ! empty( $member['photo']['url'] );
	}
);
if ( empty( $section['title'] ) || empty( $valid_members ) ) {
	continue;
}
```

Generate `.teams-page` with an escaped title, optional `wp_kses_post()` introduction, and sections containing optional WYSIWYG description plus `ul.teams-page__members`. Use `esc_url()` for photos/links and `esc_attr()` for `alt` and `aria-label`; wrap images in an anchor only for a non-empty URL.

- [ ] **Step 3: Register ACF fields on `acf/init`.**

Use guarded `acf_add_local_field_group()` with `group_promokodiki_teams_page`, location `page_template == page-teams.php`, WYSIWYG `teams_page_introduction`, and block repeater `teams_page_sections`. Its mandatory `title` has nested `members` with mandatory text `name`, mandatory image `photo` (`return_format => 'array'`), and optional URL `url`. All keys use `field_promokodiki_teams_`.

- [ ] **Step 4: Add the module load directly after the FAQ include in `functions.php`.**

```php
require_once get_template_directory() . '/inc/teams-page.php';
```

- [ ] **Step 5: Run `php tests/teams-page.php`, `php -l inc/teams-page.php`, and `php -l functions.php`; expect the contract pass and no syntax errors.**
- [ ] **Step 6: Commit the three files with message `feat: add ACF teams page module`.**

### Task 3: Template and presentation

**Files:**
- Create: `wp-content/themes/promokodiki/page-teams.php`
- Modify: `wp-content/themes/promokodiki/assets/css/overrides.css`
- Test: `wp-content/themes/promokodiki/tests/teams-page.php`

**Interfaces:**
- Consumes the Task 2 data getters and renderer.
- Produces the selectable «Наша команда» template and `.teams-page` CSS namespace.

- [ ] **Step 1: Add the template wrapper.**

```php
<?php
/** Template Name: Наша команда */
get_header();
?>
<main id="primary" class="site-main">
	<?php
	$post_id = get_the_ID();
	echo promokodiki_render_teams_page(
		promokodiki_get_teams_page_sections( $post_id ),
		promokodiki_get_teams_page_data( $post_id )
	);
	?>
</main>
<?php get_footer();
```

- [ ] **Step 2: Add desktop and mobile styles in `overrides.css`.**

Use `.container`, `var(--white)`, `var(--black)`, `var(--dark-grey)`, `var(--font-family)`, and `var(--second-family)`. Create readable white rounded section cards, a wrapping avatar layout, 88px circular `object-fit: cover` desktop images, and pink `:focus-visible` outline. At `max-width: 767px`, reduce padding, heading sizes, avatars to 64px, and gaps; no JavaScript or horizontal scroll.

- [ ] **Step 3: Run `php tests/teams-page.php; php -l page-teams.php; php -l inc/teams-page.php; git diff --check`; expect success and no whitespace error.**
- [ ] **Step 4: In WordPress assign «Наша команда» to `/teams/`, populate one linked and one unlinked avatar, inspect desktop and mobile. Confirm no visible names, keyboard focus, omitted invalid rows, and wrapping avatars.**
- [ ] **Step 5: Commit template and CSS with message `feat: add teams page template and styles`.**

## Plan Self-Review

- Task 1 proves the required rendering edge cases; Task 2 covers ACF, fallback, filtering, and escaping; Task 3 covers template, responsive CSS, and browser validation.
- Names, field names, template name, and function signatures are consistent with the approved design.
