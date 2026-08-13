# Shops Admitad Enrichment Design

## Goal

Audit and optimize `/shops/` and `shops_category` pages, enrich shop pages from the existing Admitad synchronization, preserve editorial control through optional ACF overrides, and stop reusing coupon artwork where a shop logo is required.

## Scope

The change covers the `promokodiki` theme templates and assets used by `/shops/` and `shops_category`, plus the existing `admitad-coupons` plugin's campaign normalization, storage, synchronization, administration, and tests. Public promocode card artwork inside `.promocodes__imgs` remains unchanged. The public layout is not redesigned.

## Approaches Considered

### 1. Hybrid local snapshot with editorial overrides (selected)

Extend the current background campaign sync to persist shop description, rating, website, and logo metadata locally. Public templates read ACF overrides when present, then Admitad metadata, then existing WordPress data. This keeps pages fast and resilient while retaining manual editorial control.

### 2. ACF-only shop profiles

Create and populate every shop field manually in ACF. This is predictable but does not scale to the existing catalog and duplicates data already available from Admitad.

### 3. Live Admitad requests from public templates

Request campaign details when visitors open a shop page. This gives fresh data but adds latency, rate-limit exposure, credential-dependent failures, and unstable page output. It is rejected.

## Data Ownership and Precedence

For each display value, a non-empty ACF value is authoritative. Otherwise the template uses the last non-empty Admitad value saved on the `shops_category` term. Existing WordPress taxonomy data is the final fallback. ACF is optional; the feature must work when ACF is inactive.

The plugin must not overwrite ACF fields or the built-in taxonomy description. Empty or malformed API fields must not erase the last valid Admitad snapshot. Automatic campaign-to-term updates are allowed only when the term has the matching stable `admitad_campaign_id`. Name matches are shown for review but never written automatically.

The campaign normalizer accepts and validates:

- `description` and `raw_description` as source text;
- `rating` as a finite number clamped to the supported 0–5 range;
- `image` as the campaign logo URL;
- `site_url` as the shop website;
- existing ID, name, status, and category fields.

## Description Rendering

`.promocodes__desc` renders the full shop description. Its order is an applicable manual ACF override, sanitized Admitad description, then the built-in taxonomy description. Admitad HTML uses a narrow WordPress allowlist and cannot emit scripts, inline event handlers, embedded forms, iframes, or unsafe URLs.

The “О магазине” sidebar uses `about_shop` when ACF supplies it. Otherwise it derives a summary from the first two meaningful paragraphs of the cleaned Admitad description, excludes partner-facing technical sections such as negative-keyword and webmaster instructions, and limits output to 700 characters without cutting a word. Empty blocks and their “Подробнее” control are not rendered.

## Rating

The sidebar uses a valid ACF `rating`, then the saved Admitad rating. Invalid, missing, zero, or out-of-range values hide the entire rating block. Random rating generation is removed. Star output reflects the stable numeric value and includes an accessible text label.

## Image Responsibilities

`.promocodes__imgs` in `template-parts/promocode-card.php` continues to display the coupon image and is not changed.

The shop header, “О магазине” panel, and “Промокоды магазинов” list display the corresponding campaign logo. Their source order is ACF `izobrazhenie_magazina`, a locally managed Admitad attachment, the last valid Admitad external logo URL, then the existing taxonomy image. They must not obtain a logo through `get_the_ID()` outside a promocode loop or from the latest coupon assigned to a shop.

## Managed Logo Storage

The reference sync downloads campaign logos in bounded background batches; public requests never download media. Only campaign logos are imported, never coupon images.

Each campaign points to at most one current managed attachment. The plugin records campaign ID, source URL, content hash, and ownership metadata. An unchanged URL does not trigger a download. Identical file content is shared by hash across campaigns. A changed logo is downloaded and validated before references switch.

Downloads have a strict byte limit and accept PNG, JPEG, and WebP. SVG is converted to PNG or WebP only when a supported server image library can safely decode it; otherwise the external URL remains the fallback and the SVG is not stored. Failed downloads retain the previous attachment and URL.

An old attachment may be deleted only when it is marked as owned by this integration and no shop references it. Admin cleanup first presents a dry-run list and totals; execution requires an administrator capability and nonce. It never touches unrelated media. The initial backfill also requires an administrator preview and explicit start.

## Synchronization and Failure Handling

The existing resumable reference cron remains the only Admitad campaign transport. It normalizes and stores campaign snapshots, enriches linked terms, and schedules bounded logo work without extending public request time. Retryable HTTP failures use the existing retry policy. A failed page or logo operation preserves all previously rendered data.

The administration UI reports preview totals, updated terms, planned/downloaded/reused/failed logos, unlinked campaigns, and cleanup candidates. The first enrichment is started manually after preview; later reference cron runs maintain it.

## `/shops/` Catalog and Search

The template obtains terms once, groups them once, and escapes names, attributes, IDs, and term links in their correct contexts. A shop appears only when it has at least one publicly eligible active offer according to the same visibility rules used by the promocode query layer. The shop term and its content are not deleted when offers disappear.

Client-side filtering remains as progressive enhancement but moves from inline PHP to an enqueued JavaScript file. `?s=` provides a server-rendered fallback with sanitized Unicode substring matching. Search retains useful empty-state output and works without JavaScript.

## Shop Page Query and Template Cleanup

The taxonomy template resolves the queried term once and never uses post globals for shop-level data. Duplicate wrappers and duplicated term/post queries are removed. Popular-shop logos come from shop profiles rather than one query per term and one coupon image per result. Output is escaped according to context; rich descriptions use the defined allowlist.

When no active offers exist, the page displays a neutral empty-state message. It remains indexable when it has a meaningful description. A page with neither active offers nor meaningful description emits `noindex, follow`. The term URL is not redirected or removed.

## Performance

Eligibility must not issue one full `WP_Query` per term on every `/shops/` request. The implementation stores or computes bounded active-shop IDs through the existing visibility/query rules and caches the resulting catalog set with explicit invalidation when imported coupon status, expiry, publication, or shop assignment changes. Templates use attachment APIs for responsive markup and lazy loading below the fold.

## Security and Accessibility

All remote URLs are validated, downloads are size- and MIME-bounded, and administrative mutations require `manage_options` (or the plugin's existing stricter capability), a nonce, and sanitized input. Public output uses `esc_html`, `esc_attr`, `esc_url`, or the approved HTML allowlist as appropriate. External links retain `nofollow noopener`.

Search has an associated label, useful status/empty output, and keyboard-safe behavior. Rating and image output includes meaningful accessible labels or alt text. Decorative payment icons retain empty alt text.

## Testing

Plugin tests cover campaign normalization, preservation of non-empty snapshots, stable campaign-ID linking, rating validation, description cleaning and summarization, logo validation/deduplication/ownership, failed replacement behavior, preview totals, cleanup safety, nonces, and capabilities.

Theme integration tests cover data precedence with and without ACF, absence of random ratings, correct shop-level logo selection, unchanged coupon-card `.promocodes__imgs`, escaped descriptions and URLs, active-shop filtering, server search fallback, no-JavaScript markup, empty state, and conditional robots output.

JavaScript tests cover live filtering, group visibility, form behavior, and empty results. Existing Admitad and AJAX-filter suites, PHP syntax checks, WordPress Coding Standards for touched plugin files, and theme lint/build commands must remain green.

## Acceptance Criteria

1. `/shops/` lists only shops with at least one active public offer and searches correctly with or without JavaScript.
2. A linked shop page displays stable description, summary, rating, website, and campaign logo without a public Admitad request.
3. Filled ACF values override API values independently, and the feature still works without ACF.
4. Empty or failed API responses never erase the last valid data.
5. Coupon card `.promocodes__imgs` remains unchanged while other shop imagery no longer reuses coupon artwork.
6. Missing ratings are hidden and never synthesized.
7. Managed media is bounded, deduplicated, attributable, previewable, and safely cleanable without touching unrelated attachments.
8. Existing shop URLs survive empty offer periods and receive the agreed conditional indexability behavior.
9. Public templates avoid duplicated taxonomy queries, per-shop coupon queries, unescaped output, and reliance on an unrelated post global.

## Out of Scope

- Redesigning public shop or promocode cards.
- Downloading or replacing individual coupon artwork.
- Inventing ratings or descriptions when all configured sources are empty.
- Automatically merging shops by name.
- Deleting shop terms or unrelated media.
- Registering a new ACF field group that could duplicate database-defined fields.
