# Discounts AJAX Sorting Design

## Goal

Turn the `/discounts/` tabs into sorting controls for one promocode feed, load results and additional pages over AJAX, preserve a server-rendered fallback, and remove confirmed duplicate inline JavaScript from the theme footer.

## Scope

The change covers the Discounts page, the existing `promokodiki-ajax-filter` plugin facilities needed by that page, and confirmed duplicate scripts in `wp-content/themes/promokodiki/footer.php`. It does not remove legacy AJAX handlers elsewhere in the theme because those handlers may still serve the home page, archives, search, and shop pages.

## Approaches Considered

### 1. Extend `promokodiki-ajax-filter` (selected)

Add a `discounts` context and `discussed` sort to the existing plugin, reuse its nonce validation, card renderer, pagination, URL state, loading status, and error handling, and keep the theme template thin. This avoids a second public AJAX API and follows the repository's current architecture.

### 2. Implement the feature entirely in the theme

Add handlers, queries, rendering, and JavaScript to the theme. This would be locally simple but would duplicate security, pagination, rendering, and browser-history behavior already implemented by the plugin.

### 3. Keep three pre-rendered tab panels and AJAX only their pagination

Retain the current three-query template and load more items into each panel. This keeps the existing markup but renders up to 18 cards on the first response, duplicates panel state, and makes URL/history behavior more complex.

## Architecture

The plugin owns Discounts query state and transport. The theme owns the page shell and the sort navigation markup. Initial requests are server rendered; JavaScript progressively enhances the same controls with AJAX. If the plugin is inactive, the theme renders six cards and the sort controls remain ordinary GET links.

The existing public AJAX endpoint remains the single results endpoint. A verified `discounts` context token prevents callers from changing the query context. The server sanitizes `paf_sort`, bounds page sizes, and returns rendered card HTML plus pagination state.

## Sort Contract

The canonical query parameter is `paf_sort`:

- `popular`: active promocodes ranked by clicks recorded during the latest seven-day window. If the window contains no ranked results, fall back to `_promocode_used_count` for a useful cold-start result.
- `newest`: active promocodes ordered by publication date descending, with post ID descending as a deterministic tie-breaker.
- `discussed`: active promocodes ordered by total reactions descending, with publication date and post ID as deterministic tie-breakers.

The UI labels are `Топ`, `Новинки`, and `Обсуждаемое`. The default is `popular`.

## Reaction Totals

`_promocode_votes_total` stores `_promocode_likes + _promocode_dislikes`. The plugin updates it after every vote handled by its current voting endpoint. Existing posts are not synchronously migrated. Query ordering falls back to the sum of the two legacy counters when the total meta value is absent, so old data remains correctly ranked and normalizes progressively as users vote.

## Eligibility Rules

Only published, active, non-expired promocodes are eligible:

- `_promocode_is_active = no` is excluded.
- A non-empty `_promocode_expiry_date` earlier than the current WordPress-local `Y-m-d` date is excluded.
- Missing or empty expiry dates are treated as indefinite and remain eligible.

These rules apply identically to initial rendering, AJAX replacements, and load-more requests.

## Rendering and Pagination

One `.promocodes__items` container holds the feed. The initial server response contains six cards. Each `Показать ещё` request appends six more cards. Changing sort replaces the current cards and resets pagination to page one. The load-more button is hidden when the response reports `has_more = false`.

The server returns a useful empty state rather than an empty string. Rendering continues to use `template-parts/promocode-card.php`, so AJAX and non-AJAX cards remain identical.

## Browser State and SEO

Sort links work without JavaScript. With JavaScript enabled, successful sort changes update `paf_sort` through the History API without reloading. `popstate` restores the corresponding server result. Direct visits render the requested sort on the server.

Sorted query variants emit a canonical URL for the base Discounts page, preventing duplicate sortable URLs from competing in search results while preserving shareable direct links.

## Loading, Errors, and Concurrency

While replacing a sort, existing cards remain visible, the results region receives `aria-busy=true`, controls are temporarily disabled, and a compact loading indicator is shown. A newer request aborts an older in-flight request. Only a successful response replaces or appends cards.

On failure, existing cards remain unchanged. The status region announces the failure and offers `Повторить`. Retrying repeats the last failed operation. Controls are always restored after completion or failure.

## Sort Navigation UX

The sort navigation gains a visible `Сортировать:` label and compact segmented controls. It remains horizontally scrollable without wrapping on narrow screens. Active, hover, disabled, and `focus-visible` states are distinct. The controls expose their selected state to assistive technology and support keyboard activation. The card grid itself is not redesigned.

## Footer Cleanup

Remove all inline JavaScript from `footer.php`, including duplicate tab/load-more implementations, search scrolling code, duplicate modal behavior, and inline submenu behavior. Retain footer markup, the promocode modal markup, and `wp_footer()`.

Behavior that is still required globally must be supplied by its existing enqueued script or moved to one focused enqueued file before the inline copy is removed. The Discounts behavior lives with the AJAX filter assets rather than in Footer. Unrelated legacy PHP handlers and scripts outside Footer remain unchanged in this task.

## Testing

PHP tests cover:

- Discounts context resolution and nonce validation.
- Sanitization and defaults for `paf_sort`.
- all three ordering contracts and deterministic tie-breakers.
- seven-day popular ranking and all-time cold-start fallback.
- discussed fallback for legacy reaction counters and total updates after voting.
- exclusion of inactive and expired posts while retaining undated posts.
- six-card initial/load-more offsets and `has_more` behavior.
- server fallback markup and canonical output.

JavaScript tests cover:

- sort replacement versus load-more append behavior.
- URL updates and `popstate` restoration.
- cancellation of stale requests.
- preservation of cards and retry UI after errors.
- loading, disabled, and accessible state changes.

The existing plugin PHP and JavaScript suites must remain green. Theme integration tests must confirm that Footer no longer contains inline scripts and that the modal remains present.

## Acceptance Criteria

1. A direct `/discounts/?paf_sort=...` request renders the correct first six active cards.
2. Clicking a sort changes one feed without reloading and updates browser history.
3. `Показать ещё` appends exactly the next six cards without duplicates.
4. Back and Forward restore the correct sort and results.
5. Failed or superseded requests never erase valid cards.
6. The page remains usable when JavaScript or the plugin is unavailable.
7. `footer.php` contains no inline JavaScript and retains required markup and `wp_footer()`.
8. Existing plugin and theme integration tests pass.

## Out of Scope

- Removing every legacy theme AJAX handler.
- Redesigning promocode cards or other pages.
- Recording dated vote events or adding a time-windowed Discussed ranking.
- Changing the global AJAX filter settings UI beyond what the Discounts context requires.
