# Telegram Promocodes Design

## Goal

Replace the local random "Top Telegram promocodes" snapshot with an MTProto-backed importer that initially reads `@tranzhiraru`, supports more public channels, publishes high-confidence offers as normal WordPress `promocode` posts, and renders them in a responsive slider.

## Agreed Product Rules

- Use a dedicated Telegram service account through MTProto.
- Run a Python Telethon worker from Sprinthost Cron every three hours.
- Manage enabled channels in WordPress; seed `tranzhiraru` on activation.
- On first sync inspect at most 200 messages and no more than seven days of history.
- Publish only posts containing exactly one confidently detected promocode and at least one external merchant URL.
- Skip forwarded posts, reply/reference posts, posts without a merchant URL, and posts with multiple distinct codes.
- Store skipped reasons in an import log.
- Create the hierarchical term `Промокоды из Telegram` with slug `promokody-iz-telegram` in `promocode_category`.
- Create one normal `promocode` post per accepted Telegram message.
- Do not show the Telegram channel name, username, or source link on the frontend.
- Keep the source URL and raw text in protected post metadata for administration and synchronization.
- Use an explicit expiry when parsed; interpret today/tomorrow in Europe/Moscow; otherwise expire after 72 hours.
- Reparse edited posts, unpublish deleted/invalidated source posts, and preserve posts explicitly locked by an administrator.
- Download one image or video thumbnail into the WordPress media library when available.
- Generate an Admitad deeplink when an active campaign matches the destination domain; otherwise publish the cleaned direct destination URL.
- Keep website usage and like/dislike counters; Telegram views are ranking inputs only.
- Rank pinned posts first, then view velocity, freshness, and discount as a smaller bonus.
- Render between 4 and 20 records, configurable in WordPress.
- Use Swiper with 4 desktop, 2 tablet, and 1 mobile card; arrows and swipe enabled; autoplay disabled.
- Telegram records appear in the Telegram top block, the Telegram category archive, and site search, but not in ordinary Admitad/team collections.
- Remove the old three-hour random snapshot, its AJAX refresh action, and the legacy top script behavior.

## Architecture

### WordPress Plugin

`wp-content/plugins/promokodiki-telegram/` owns channel configuration, HMAC-authenticated REST endpoints, import validation, post persistence, expiry, Admitad link conversion, administrative screens, logs, and manual-lock/pin metadata.

Small configuration is stored in options. Imported offers remain ordinary `promocode` posts so the existing editor, modal, interaction counters, search, and taxonomy templates keep working.

### Worker

`worker/promokodiki_telegram_worker/` contains a testable parser and a Telethon orchestration layer. Secrets and the MTProto session stay outside `public_html` in an environment file and session file. The worker fetches channel configuration, scans new and tracked messages, resolves outbound URLs, parses candidates, downloads bounded media, posts one batch to WordPress, and exits.

### Authentication

Worker requests include timestamp, nonce, and an HMAC-SHA256 signature over method, REST path, nonce, timestamp, and raw body. WordPress rejects stale timestamps, reused nonces, and invalid signatures.

### Theme

The theme queries only active posts in the Telegram category through the plugin's public query method, renders existing interaction data without source attribution, initializes Swiper from the already bundled Swiper 11 runtime, and counts down to the next three-hour synchronization boundary.

## Import Payload

Each accepted item contains channel username, message ID, publication/edit timestamps, raw text, generated title and excerpt, code, discount label/value, cleaned destination URL, Telegram source URL, views, expiry timestamp, parser confidence, detected code count, and optional base64 media with MIME type.

Each batch also contains the newest observed message ID, inspected count, skipped-reason counters, inactive/deleted message IDs, and worker timestamp.

## Operational Configuration

Worker environment variables:

- `TELEGRAM_API_ID`
- `TELEGRAM_API_HASH`
- `TELEGRAM_SESSION`
- `TELEGRAM_PHONE` for first interactive login only
- `PROMOKODIKI_WORDPRESS_URL`
- `PROMOKODIKI_TELEGRAM_SECRET`

Sprinthost Cron invokes the virtualenv Python interpreter and `worker/run.py` every three hours. The first interactive login is performed once over SSH before enabling Cron.
