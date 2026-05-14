# Adbot Tracking Platform — WordPress plugin

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress admin plugin that connects your Google marketing stack — **Google Tag Manager, Google Analytics 4, Google Ads, and Google Search Console** — through a hosted backend. It can inject a GTM container snippet, run tracking audits, and (with entitlement) apply audit fixes via the Google Tag Manager API.

> This repository is the **public source** for the plugin published on the WordPress.org Plugin Directory as `adbot-tracking-platform`. It also satisfies WordPress.org Guideline 1 (public, maintained access to source code and build tools).

## How it works

The plugin itself stores no third-party credentials. All Google OAuth tokens, Supabase records, and Paystack interactions live on the **Adbot Tracking backend** (`https://adbot-tracking-platform.vercel.app`). The WordPress site authenticates to that backend with a per-site token minted on first use.

```
WordPress site  ──►  Adbot backend (Vercel)  ──►  Google / Supabase / Paystack
   plugin            (holds all secrets)
```

External services are documented in `adbot/readme.txt` under **External services**. No outbound request is made until the user has opted in via the consent toggle in the plugin Settings screen.

## Repository layout

| Path | Purpose |
|------|---------|
| `adbot/` | **Shipped plugin** — PHP source under `includes/`, built JS/CSS in `build/`, `readme.txt`, `adbot.php`. |
| `src/` | React source for the admin UI. `npm run build` compiles into `adbot/build/`. |
| `docs/ADBOT_BACKEND_CONTRACT.md` | API contract between the plugin and the Vercel backend. |
| `dev/tools/build-release.sh` | Production ZIP build (Composer prod install + webpack build + rsync excludes). |

The WordPress.org-style readme that end users see lives at **`adbot/readme.txt`**.

## Requirements

- **WordPress** 6.0+ (see `adbot/readme.txt` for "Tested up to")
- **PHP** 8.0+
- **Node.js** 18+ and **npm** — only needed to rebuild the admin UI
- **Composer** — only needed for the release build

## Local development

```bash
# 1. Install JS dependencies and compile the admin bundle
npm ci
npm run build

# 2. Run a local WordPress with the plugin mounted
npx @wordpress/env start   # uses .wp-env.json in this repo
```

Then open `http://localhost:8888/wp-admin` and activate **Adbot Tracking Platform**.

You can also symlink or copy the `adbot/` directory into any existing `wp-content/plugins/` install.

### Site registration and Google Connect

After consent, the backend verifies your site by calling a public endpoint:

```
GET /wp-json/adbot/v1/verify?challenge=…
```

The WordPress **Site Address (URL)** must therefore be reachable from the public internet — a live HTTPS domain, or a tunnel such as [ngrok](https://ngrok.com) or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/). Sites whose URL is only `http://localhost:…` will not complete registration because the backend cannot reach them.

### Linting

```bash
npm run lint:js
npm run lint:css
```

## Building the release ZIP

From the repository root:

```bash
bash dev/tools/build-release.sh
```

Artifact: **`dist/adbot-tracking-platform.zip`** — install via **Plugins → Add New → Upload Plugin** in any WordPress site, or submit to [WordPress.org plugin review](https://wordpress.org/plugins/developers/add/).

The build script:

1. Runs `composer install --no-dev --optimize-autoloader` inside `adbot/`.
2. Runs `npm ci && npm run build` at the repo root.
3. Copies `adbot/` into `dist/` using `.plugin-build-excludes` to drop dev-only files.

`composer.json` and `composer.lock` stay inside the packaged plugin so the WordPress.org Plugin Check can validate the `vendor/` tree.

## Optional configuration

The plugin works with zero configuration. Two optional defines in `wp-config.php` are supported:

| Constant | Purpose |
|----------|---------|
| `ADBOT_API_BASE` | Point the plugin at an alternate backend (e.g. staging). No trailing slash. Default: `https://adbot-tracking-platform.vercel.app/api/wp`. |
| `ADBOT_DEBUG` | When `true` and `WP_DEBUG` is on, enables verbose error logging. |

See **`docs/ADBOT_BACKEND_CONTRACT.md`** for the full HTTP contract, error codes, and security assumptions.

## Security

- OAuth tokens are stored encrypted at rest on the backend, never in the WordPress database.
- The plugin's per-site token is encrypted in WordPress using AES-256-GCM with a key derived from `AUTH_KEY` / `SECURE_AUTH_KEY` / `LOGGED_IN_KEY` / `NONCE_KEY` from `wp-config.php`. No additional key configuration is required.
- All REST routes use `permission_callback` checks; the public `/verify` endpoint accepts only a single-use challenge.

To report a security issue privately, please email **security@adbot.co.za** rather than opening a public issue.

## Contributing

Issues and pull requests are welcome. For non-trivial changes please open an issue first to discuss scope.

When submitting a PR:

- Run `npm run lint:js && npm run lint:css` before pushing.
- Keep the WordPress.org readme (`adbot/readme.txt`) in sync if user-visible behaviour changes.
- Do not commit secrets, OAuth client IDs, or `.env*` files — `.env.local` is gitignored.

## License

Released under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html). See `adbot/adbot.php` for the plugin header.

## Trademarks

"Adbot" and the Adbot logo are © Adbot. Google product names and logos (Tag Manager, Google Analytics, Google Ads, Google Search Console) are trademarks of Google LLC, used for identification purposes in accordance with Google's brand guidelines.
