# Adbot Backend API Contract (WordPress Plugin ↔ Vercel Backend)

The plugin is a thin client. The Vercel backend owns Google OAuth client secret, Paystack secret, Supabase service key, and token encryption. The plugin never touches Google/Paystack/Supabase directly.

- **Base URL:** `ADBOT_API_BASE` (default `https://adbot-tracking-platform.vercel.app/api/wp`, matching `Adbot\Backend\Client::DEFAULT_BASE`; planned migration to `https://tracking.adbot.co.za/api/wp`). Override via `define( 'ADBOT_API_BASE', '…' )` in `wp-config.php` (no trailing slash).
- **Auth:** every request except `/sites/register` and `/sites/verify` carries `Authorization: Bearer <site_token>`. `site_token` is a per-site opaque string minted by the backend.
- **Content type:** JSON, UTF-8.
- **Errors:** non-2xx responses MUST return `{ "code": "<snake_case>", "message": "<human readable>" }`. The plugin surfaces `message` verbatim.

  > **Corrected 2026-09-30:** this previously documented a third `details` key. No route under `/api/wp` emits one (it exists only on non-WP routes such as `/api/demo-requests`), and the plugin has never read it. Codes in use: `unauthorized`, `invalid_request`, `invalid_site_url`, `google_not_connected` (409), `google_scope_required` (403), `google_api_error`, `payment_required` (402), `no_pending_challenge`, `challenge_expired`, `challenge_mismatch`, `verification_failed`, `verification_unreachable`.

## Site registration (first-use flow)

Registration is **not** tied to the activation hook alone. The plugin attempts registration on **`admin_init`** when consent has been granted and a site token is not yet stored (`Site_Registration::maybe_register`), debounced to at most once per minute while the token is missing.

For verification to succeed, the backend must perform `GET <verification_url>` against the WordPress site’s public URL. **`localhost` URLs are typically unreachable** from the hosted backend; use a publicly resolvable HTTPS Site Address (or a tunnel) for development.

### `POST /sites/register`

Called from WordPress when registering the site. No auth header.

**Request:**
```json
{
  "site_url":       "https://example.com",
  "site_name":      "Example",
  "admin_email":    "owner@example.com",
  "wp_version":     "6.7.1",
  "plugin_version": "1.0.0",
  "nonce":          "<32-char plugin-generated string>"
}
```

**Response 201:**
```json
{
  "site_id":          "site_xxx",
  "verification_url": "https://example.com/wp-json/adbot/v1/verify?challenge=…"
}
```

The backend generates `challenge`, stores `(site_id, nonce, challenge, pending)`, and returns the verification URL that points back at the site. The plugin does NOT call this URL directly — the backend does, next step.

> **⚠ Code drift (verified 2026-06-25 against `app/api/wp/sites/register/route.ts`):** the live backend does **not** implement a separate `challenge` or return a `verification_url`. The 201 response body is just `{ "site_id": "…" }`. The backend stores the plugin's `nonce` (as `verifyNonce`) and later uses that **same nonce as the `challenge` query param** when it calls the site back. So in practice **`challenge == nonce`** — the plugin's transient (`adbot_site_challenge`) holds the nonce, and `handle_verify_request()` compares the inbound `?challenge=` against it. Treat the `challenge`/`verification_url` fields above as aspirational, not current.
>
> Also note: `/sites/register` upserts by `site_url` and **sets `siteTokenHash = null` on every call**, with no auth. An unauthenticated caller who knows a registered site's URL can therefore revoke its live token; the plugin only re-registers when its *local* token is empty (it never clears a backend-rejected token), so recovery is not automatic. Tracked as a hardening item.

### `POST /sites/verify`

Called by the plugin immediately after register. No auth header.

**Request:**
```json
{
  "site_id": "site_xxx",
  "nonce":   "<same nonce as register>"
}
```

Backend action:
1. Performs `GET <verification_url>` server-side.
2. The plugin's public `/wp-json/adbot/v1/verify?challenge=…` endpoint returns `{ "nonce": "<same nonce>" }` (JSON) only if the challenge matches what was stored in a transient.
3. If the returned nonce matches, backend marks the site `verified` and mints a `site_token`.

> **⚠ Code drift (verified 2026-06-25 against `app/api/wp/sites/verify/route.ts`):** the backend GETs `<site_url>/wp-json/adbot/v1/verify?challenge=<nonce>` — i.e. the **nonce is passed as the `challenge`** (`url.searchParams.set("challenge", nonce)`). There is no independent challenge value. The challenge transient is short-lived (the plugin sets `verifyNonceExpiresAt = now + 10 min`); after that the backend returns `410 challenge_expired` and the site must re-register.

**Response 200:**
```json
{ "site_token": "st_…" }
```

After this, the plugin stores `site_token` encrypted at rest (`Token_Store`) and uses it on every subsequent request.

### `POST /sites/heartbeat`

Optional. Weekly cron to refresh last-seen. Body: `{"plugin_version": "…", "wp_version": "…"}`. Response 204.

## Google OAuth

### `POST /auth/google/start`

Request:
```json
{
  "return_url": "https://example.com/wp-admin/admin.php?page=adbot",
  "scopes":     ["gsc"]
}
```

`scopes` is optional and holds **scope-bundle names**, never raw Google scope
strings — the plugin cannot widen its own grant. Bundles are defined in
`lib/wp/google-oauth.ts` (`WP_GOOGLE_SCOPE_SETS`):

| Bundle | Google scopes |
|---|---|
| `gsc` | `auth/webmasters`, `auth/siteverification` |
| `gmc` | `auth/content`, `auth/siteverification` |

Omitting `scopes` requests the base GTM/GA4/userinfo set only. The auth URL sets
`include_granted_scopes: true`, so an incremental grant **extends** the existing
one rather than replacing it — without that flag, granting Search Console would
silently revoke Tag Manager.

> `WP_GOOGLE_SCOPES` in the backend environment overrides only the **base** set.
> Bundles are always appended, so setting that variable cannot disable a channel.
Response 200:
```json
{ "auth_url": "https://adbot-tracking-platform.vercel.app/api/wp/auth/google/callback?state=…" }
```
Plugin opens `auth_url` in a popup or top-level window.

### `GET /auth/google/callback`

Backend handles the full Google redirect. Exchanges code → tokens → stores encrypted tokens keyed by `site_id`. Then issues an HTTP 302 to `return_url` with `?connected=1` appended (or `?error=<code>` on failure).

> **⚠ Code drift (verified 2026-06-25 against `app/api/wp/oauth/google/return/route.ts`) — this caused a live `redirect_uri_mismatch` on a client site:**
>
> - The real callback route is **`GET /api/wp/oauth/google/return`**, *not* `/auth/google/callback`. This exact URL is what `getWpGoogleAuthUrl()` sends to Google as `redirect_uri` (from the `WP_GOOGLE_REDIRECT_URI` env), so it **must be registered verbatim** in the Google Cloud OAuth client's *Authorized redirect URIs*. For the default backend host that is:
>   ```
>   https://adbot-tracking-platform.vercel.app/api/wp/oauth/google/return
>   ```
>   (add the `https://tracking.adbot.co.za/...` variant before migrating hosts). If it isn't registered, Google returns `Error 400: redirect_uri_mismatch` before any token is issued.
> - On return the backend appends **`?adbot_oauth=success`** (or **`?adbot_oauth=error&reason=<state_expired|missing_code|no_refresh_token|exchange_failed|…>`**) to the `return_url` — *not* `?connected=1` / `?error=`. This is cosmetic for the plugin, which detects connection by re-polling `GET /auth/status` on window focus rather than reading these params.
> - A `refresh_token` is required: Google must return one (the auth URL uses `access_type=offline` + `prompt=consent`), or the backend fails with `reason=no_refresh_token`.

### `GET /auth/status`

Response 200:
```json
{
  "connected": true,
  "account":   { "id": "acct_…", "email": "…", "name": "…", "picture": "https://…" },
  "scopes":    "https://www.googleapis.com/auth/webmasters …",
  "scopeSets": ["gsc"]
}
```
or `{ "connected": false, "account": null, "scopes": null, "scopeSets": [] }`.

`scopeSets` lists the bundles the stored token fully covers. The plugin reads it
to render a channel card's picker straight away instead of provoking a 403 from
Google to discover a missing scope. A bundle appears only when **every** scope in
it is held — `webmasters` without `siteverification` cannot complete the verify
flow, so reporting it would offer a picker that then fails.

### `POST /auth/disconnect`

Revokes Google tokens + clears the connection. Response 200 `{ "disconnected": true }`.

## Google Tag Manager

### `GET /gtm/containers`

Response 200:
```json
{
  "accounts": [
    {
      "accountId":   "6000…",
      "accountName": "My Agency",
      "containers": [
        { "containerId": "GTM-XXXX", "name": "example.com", "path": "accounts/6000…/containers/123" }
      ]
    }
  ]
}
```

### `POST /gtm/snippet/install`

Request:
```json
{
  "container_id":      "GTM-XXXX",
  "container_path":    "accounts/.../containers/...",
  "ga4_measurement_id":"G-ABC",
  "site_url":          "https://example.com"
}
```
Response 200: `{ "installed": true }`.

Backend records the install in Supabase. The plugin injects the snippet locally in the `<head>`/`<body>` — this request is purely bookkeeping.

### `POST /gtm/snippet/uninstall`

Empty body. Response 200: `{ "uninstalled": true }`.

## Google Search Console

All routes require the `gsc` scope bundle. Persisted to `WpSite.config.gsc`
(`{ siteUrl, permissionLevel, verifiedAt, sitemapUrl, sitemapSubmittedAt }`) —
no migration; same untyped-blob precedent as `config.gtm`.

Search Console identifies a URL-prefix property **with** a trailing slash
(`https://example.com/`), which is the opposite of how `normalizeSiteUrl()` keys
`WpSite.siteUrl`. Every call converts via `toPropertyUrl()`.

### `GET /gsc/sites`

Response 200:
```json
{
  "sites": [
    { "siteUrl": "https://example.com/", "permissionLevel": "siteOwner",
      "verified": true, "isThisSite": true }
  ],
  "siteUrl":          "https://example.com/",
  "thisSiteVerified": true
}
```

`verified` comes from the Site Verification API, `permissionLevel` from Search
Console; a property can be one without the other. `siteUnverifiedUser` means the
account can see the property but not submit sitemaps, and the plugin disables it
in the picker.

### `POST /gsc/verify/token`

Request `{ "site_url": "https://example.com/" }` (optional; defaults to the
registered site). Response 200: `{ "method": "META", "token": "<opaque>", "site_url": "…" }`.

Google returns the *whole* meta tag for the META method; the backend parses out
the `content` value and returns only that, so the plugin never echoes
backend-supplied HTML into a page. The plugin stores it in
`adbot_site_verification_token` and renders the tag itself on `wp_head`.

### `POST /gsc/verify`

Call **after** the tag is live — Google fetches the site during this call.
Response 200:
```json
{ "verified": true, "added": true, "add_error": null,
  "site_url": "https://example.com/", "verified_at": "2026-09-30T…Z" }
```

Verification and `sites.add` are reported separately on purpose: verification is
the part that can genuinely fail, and a verified property that was not yet added
is recoverable on the next list, so `added: false` is a warning rather than an
error.

### `POST /gsc/select`

Request `{ "site_url": "…", "permission_level": "siteOwner" }`. Bookkeeping only,
no Google call — the mirror of `/ga4/select`. Response `{ "selected": true, "site_url": "…" }`.

### `POST /gsc/sitemap`

Request `{ "sitemap_url": "https://example.com/wp-sitemap.xml" }`. Rejects a
sitemap on a different host than the property with a 400 before calling Google.
Response 200: `{ "submitted": true, "sitemap_url": "…", "site_url": "…", "submitted_at": "…" }`.

### `GET /gsc/sitemap`

Response 200: `{ "site_url", "submitted_url", "submitted_at", "sitemaps": [ { "path", "lastSubmitted", "lastDownloaded", "isPending", "warnings", "errors" } ] }`.

## Google Merchant Center

All routes require the `gmc` scope bundle. Uses the **Merchant API**
(`merchantapi` `accounts_v1` + `datasources_v1`), not Content API for Shopping
v2.1. Persisted to `WpSite.config.gmc`
(`{ accountName, accountId, accountLabel, homepageClaimedAt, dataSourceName, dataSourceId, feedUrl, registeredAt }`).

### `GET /gmc/accounts`

Response 200:
```json
{
  "accounts": [
    { "name": "accounts/123", "accountId": "123", "accountName": "My Store", "testAccount": false }
  ],
  "selected": "accounts/123"
}
```

`accounts.list` already returns sub-accounts reachable through an advanced
(multi-client) account, so there is no separate hierarchy walk.

### `POST /gmc/select`

Request `{ "account_name": "accounts/123" }`. Unlike `/ga4/select` this **does**
call Google: the posted id is re-resolved against the accounts the token can
reach, so a stale or hand-edited value can never point the feed at someone
else's account. 404 `google_api_error` when it is not reachable.

### `POST /gmc/claim`

Claims the store website (`accounts/{id}/homepage:claim`). Requires the domain
already verified — the same META proof the Search Console flow publishes, which
is why that flow is built once and used twice. `overwrite` is deliberately not
sent: silently taking a claim from another of the user's accounts is destructive
and invisible, so a conflict surfaces as an error instead.

Response 200: `{ "claimed": true, "account_name": "accounts/123", "claimed_at": "…" }`.

### `POST /gmc/feed`

Request:
```json
{
  "feed_url":         "https://example.com/adbot-feed/<token>/products.xml",
  "content_language": "en",
  "feed_label":       "ZA",
  "time_zone":        "Africa/Johannesburg"
}
```

Creates or updates a `PrimaryProductDataSource` whose
`fileInput.fetchSettings` points at the plugin-hosted feed with
`frequency: FREQUENCY_DAILY`. Matched by `displayName` (`Adbot — <host>`) so a
re-register updates the same source rather than creating a second one that would
contend over the same products.

`content_language` and `feed_label` are **required and immutable** on a
file-input data source, so the plugin resolves them from the store (site locale
and the WooCommerce base country) instead of the backend guessing. HTTPS is
enforced: Merchant Center's `fetchSettings.username`/`password` are documented
for SFTP only, so the feed is protected by an unguessable path token that must
not travel in clear text.

Response 200: `{ "registered": true, "data_source": { "name", "dataSourceId", "displayName", "fetchUri", "fetchEnabled" }, "registered_at": "…" }`.

### `GET /gmc/feed`

Response 200: `{ "registered", "account_name", "feed_url", "registered_at", "homepage_claimed_at", "data_source" }`, or `{ "registered": false, … "data_source": null }` before registration.

### `POST /gmc/feed/fetch`

Triggers an immediate fetch instead of waiting for the daily schedule, so the
user sees items land rather than being told to come back tomorrow. Response 200:
`{ "fetching": true, "data_source_name": "accounts/123/dataSources/456" }`.

> **Product data never reaches this backend.** Google fetches the feed URL from
> the WordPress site directly. Only the URL, the account and the data-source id
> transit Adbot — a deliberately smaller data flow than a per-product push, and
> the reason the hosted-fetch design was chosen.

## Insights (product page charts)

### `GET /insights/{product}`

`product` is `ga4`, `gsc`, `gmc` or `gbp`. Query: `range` = `7d` | `28d` | `90d`
(default `28d`); for `ga4` also `property_id` (numeric GA4 property id, which the
plugin stores locally in `adbot_ga4_property_id` when the user picks a property).
Search Console and Merchant Center use the backend's stored selections.

Returns the same `ProductInsights` payload as the Shopify app
(`lib/insights/types.ts`): `product`, `source` (`"live"` or `"none"` — never
sample data), `connected`, `comingSoon`, `accountLabel`, `range`, `compare`,
`compareLabel`, `currency`, `scorecards[]`, `charts[]` (time series),
`breakdowns[]` and `emptyReason`.

Expected conditions are 200 with `source: "none"` and a plain `emptyReason`
(nothing selected, no data in range, scope not granted). A revoked grant is
409 `google_not_connected`.

Plugin side (`Insights_Controller`): proxies as
`GET /wp-json/adbot/v1/insights/{product}?range=`, caches live results for 15
minutes per product and range (`adbot_insights_*` transients, cleared on a
selection change, on disconnect and on uninstall), answers `gbp` locally with
`comingSoon: true`, and turns a backend 404 (older backend) into an empty
state.

## Audits

### `POST /audit/run`

Request:
```json
{ "container_path": "accounts/.../containers/..." }
```
Response 200 — full audit payload (tags, triggers, gaps, score, snippetInstalled, etc.). Same shape as current plugin `Audit_Controller` returns.

### `GET /audit/{audit_id}`

Response 200 — previously-run audit payload. 404 if expired.

## Payments

### `POST /payments/initialize`

Request:
```json
{ "audit_id": "optional" }
```

Response **200** — JSON is passed through to the payment UI. The browser flow expects fields compatible with Paystack Inline, for example:

```json
{
  "publicKey": "pk_test_…",
  "email":     "admin@example.com",
  "amount":    4999900,
  "currency":  "ZAR",
  "reference": "adbot_xxxx"
}
```

(`amount` is typically expressed in the smallest currency unit; confirm against your backend implementation.)

The plugin opens Paystack’s inline checkout using these values.

### `POST /payments/verify`

Request:
```json
{ "reference": "adbot_xxxx" }
```
Response 200:
```json
{ "paid": true, "reference": "adbot_xxxx" }
```
or 402 with `code: "payment_not_successful"` / `payment_below_price`.

## Webhook (backend → site, optional)

### `POST /wp-json/adbot/v1/entitlements`

Backend pushes entitlement changes back to the site when Paystack webhook fires. Authenticated via HMAC signature header `X-Adbot-Signature: sha256=…` using a shared secret derived per-site on register. Plugin updates `adbot_onboarding` state idempotently.

## Security model

- Plugin never holds Google client secret, Paystack secret, or Supabase service key.
- `site_token` is the only bearer credential; it identifies the site, not a user. Backend enforces rate limits and revokes tokens on `/auth/disconnect`.
- Plugin encrypts `site_token` at rest in `wp_options` using AES-256-GCM with a key derived from `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, and `NONCE_KEY` via `hash_hmac( 'sha256', 'adbot-site-token-key-v1', $salts )` (see `Token_Store::key()`). No key shipped.
- Backend verifies site ownership via the HTTP challenge-response on registration. A token is only minted after the verify callback succeeds.
- All inbound webhooks are HMAC-signed.
- Channel scopes are requested incrementally by **bundle name**. The plugin
  allow-lists the names (`Auth_Controller::SCOPE_SETS`) and the backend
  re-validates them against `WP_GOOGLE_SCOPE_SETS`, so the security boundary is
  server-side and a modified plugin build cannot request an arbitrary scope.
- The product feed is the plugin's only unauthenticated public endpoint besides
  `/verify`. It is guarded by a 32-character alphanumeric path token compared
  with `hash_equals` and served `X-Robots-Tag: noindex`. It is deliberately
  **not** disallowed in `robots.txt`: Merchant Center's scheduled fetch obeys
  `robots.txt` and would fail as "denied by robots.txt". An
  unconfigured feed and a wrong token are indistinguishable (both 404). The
  token is a capability URL, not a credential for the Google account, so it is
  deliberately **kept** across a Google disconnect — rotating it would break a
  Merchant Center data source the user may be about to reconnect.
- The verification meta tag stays published after a successful claim: Google
  re-checks periodically and drops ownership if it disappears, which would
  silently break both the Search Console property and the Merchant Center claim.
  It is removed only on disconnect.

## Plugin-side constants (all optional, for overrides only)

- `ADBOT_API_BASE` — point the plugin at a staging or alternate backend (same path prefix as production, e.g. `…/api/wp`).
- `ADBOT_DEBUG` — verbose logging when `WP_DEBUG` is on.

No secrets required. No `.env` file required. No user configuration required.
