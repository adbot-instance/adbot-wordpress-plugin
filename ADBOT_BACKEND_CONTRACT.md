# Adbot Backend API Contract (WordPress Plugin ↔ Vercel Backend)

The plugin is a thin client. The Vercel backend owns Google OAuth client secret, Paystack secret, Supabase service key, and token encryption. The plugin never touches Google/Paystack/Supabase directly.

- **Base URL:** `ADBOT_API_BASE` (default `https://api.adbot.co.za/wp/v1`, overridable via `define( 'ADBOT_API_BASE', '…' )` in `wp-config.php`).
- **Auth:** every request except `/sites/register` and `/sites/verify` carries `Authorization: Bearer <site_token>`. `site_token` is a per-site opaque string minted by the backend.
- **Content type:** JSON, UTF-8.
- **Errors:** non-2xx responses MUST return `{ "code": "<snake_case>", "message": "<human readable>", "details": { … } }`. The plugin surfaces `message` verbatim.

## Site registration (activation flow)

### `POST /sites/register`

Called once on plugin activation. No auth header.

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
2. The plugin's public `/wp-json/adbot/v1/verify?challenge=…` endpoint returns `{ "nonce": "<same nonce>" }` only if the challenge matches what was stored in a transient.
3. If the returned nonce matches, backend marks the site `verified` and mints a `site_token`.

**Response 200:**
```json
{ "site_token": "st_…" }
```

After this, the plugin stores `site_token` (encrypted at rest with a key derived from WP salts) and uses it on every subsequent request.

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
{ "auth_url": "https://api.adbot.co.za/wp/v1/auth/google/callback?state=…" }
```
Plugin opens `auth_url` in a popup or top-level window.

### `GET /auth/google/callback`

Backend handles the full Google redirect. Exchanges code → tokens → stores encrypted tokens keyed by `site_id`. Then issues an HTTP 302 to `return_url` with `?connected=1` appended (or `?error=<code>` on failure).

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
Response 200:
```json
{
  "authorization_url": "https://checkout.paystack.com/…",
  "reference":         "adbot_xxxx",
  "amount_subunits":   4999900,
  "currency":          "ZAR"
}
```

Plugin redirects the browser to `authorization_url`.

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
- Plugin encrypts `site_token` at rest in `wp_options` using AES-256-GCM with a key derived from `AUTH_KEY . SECURE_AUTH_KEY` WP salts via `hash_hmac( 'sha256', 'adbot-site-token-key', $salts )`. No key shipped.
- Backend verifies site ownership via the HTTP challenge-response on registration. A token is only minted after the verify callback succeeds.
- All inbound webhooks are HMAC-signed.

## Plugin-side constants (all optional, for overrides only)

- `ADBOT_API_BASE` — point the plugin at a staging backend.
- `ADBOT_DEBUG` — verbose logging when `WP_DEBUG` is on.

No secrets required. No `.env` file required. No user configuration required.
