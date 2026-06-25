# Adbot Backend API Contract (WordPress Plugin ↔ Vercel Backend)

The plugin is a thin client. The Vercel backend owns Google OAuth client secret, Paystack secret, Supabase service key, and token encryption. The plugin never touches Google/Paystack/Supabase directly.

- **Base URL:** `ADBOT_API_BASE` (default `https://adbot-tracking-platform.vercel.app/api/wp`, matching `Adbot\Backend\Client::DEFAULT_BASE`; planned migration to `https://tracking.adbot.co.za/api/wp`). Override via `define( 'ADBOT_API_BASE', '…' )` in `wp-config.php` (no trailing slash).
- **Auth:** every request except `/sites/register` and `/sites/verify` carries `Authorization: Bearer <site_token>`. `site_token` is a per-site opaque string minted by the backend.
- **Content type:** JSON, UTF-8.
- **Errors:** non-2xx responses MUST return `{ "code": "<snake_case>", "message": "<human readable>", "details": { … } }`. The plugin surfaces `message` verbatim.

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
{ "return_url": "https://example.com/wp-admin/admin.php?page=adbot" }
```
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
  "account":   { "id": "acct_…", "email": "…", "name": "…", "picture": "https://…" }
}
```
or `{ "connected": false, "account": null }`.

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

## Plugin-side constants (all optional, for overrides only)

- `ADBOT_API_BASE` — point the plugin at a staging or alternate backend (same path prefix as production, e.g. `…/api/wp`).
- `ADBOT_DEBUG` — verbose logging when `WP_DEBUG` is on.

No secrets required. No `.env` file required. No user configuration required.
