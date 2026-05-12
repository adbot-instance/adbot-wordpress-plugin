# Adbot WordPress plugin

WordPress admin app that connects Google Tag Manager, Google Analytics 4, and related Google marketing products via a hosted backend. It can inject a GTM snippet, run tracking audits, and (with entitlement) apply fixes through Google Tag Manager.

## Repository layout

| Path | Purpose |
|------|---------|
| `adbot/` | **Shipped plugin** — PHP (`includes/`), built assets in `build/`, `readme.txt`, `adbot.php`. |
| `src/` | React source for the admin UI (`npm run build` outputs into `adbot/build/`). |
| `docs/ADBOT_BACKEND_CONTRACT.md` | API behaviour between the plugin and the Vercel backend. |
| `dev/tools/build-release.sh` | Production ZIP build (Composer prod + webpack + rsync excludes). |

The WordPress.org–style readme lives at **`adbot/readme.txt`**.

## Requirements

- **WordPress** 6.0+ (see `adbot/readme.txt` for “Tested up to”).
- **PHP** 8.0+.
- **Node.js** + npm — only needed to rebuild admin JavaScript/CSS.

## Local development

1. Install JS dependencies and compile assets:

   ```bash
   npm ci
   npm run build
   ```

2. Link or copy the `adbot` folder into `wp-content/plugins/` (or use [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) — see `.wp-env.json` in this repo).

3. Activate **Adbot** in wp-admin.

### Site registration and Google connect

The backend proves it can reach your site by requesting:

`/wp-json/adbot/v1/verify?challenge=…`

So the WordPress **Site Address (URL)** must be reachable from the internet (HTTPS production domain or a tunnel such as ngrok or Cloudflare Tunnel). A URL that is only `http://localhost:…` on your laptop will not complete registration, because the remote backend cannot call that address.

## Release ZIP (WordPress.org or manual install)

From the repository root:

```bash
bash dev/tools/build-release.sh
```

Artifact: **`dist/adbot.zip`** — install by uploading the ZIP in **Plugins → Add New → Upload**, or submit that ZIP for [WordPress.org plugin review](https://wordpress.org/plugins/developers/add/).

The script runs `composer install --no-dev` inside `adbot/`, runs `npm ci && npm run build`, then rsyncs `adbot/` into the ZIP (optional excludes in `.plugin-build-excludes`; **`composer.json` / `composer.lock` stay in the package** so WordPress.org Plugin Check is satisfied when `vendor/` is included).

## Configuration (optional)

All secrets live on the Adbot backend. The plugin supports optional defines in `wp-config.php`:

- `ADBOT_API_BASE` — alternate backend root (no trailing slash), same path pattern as production (`…/api/wp`).
- `ADBOT_DEBUG` — extra logging when debugging.

See **`docs/ADBOT_BACKEND_CONTRACT.md`** for endpoints and security assumptions.

## License

GPL-2.0-or-later (see `adbot/adbot.php`).
