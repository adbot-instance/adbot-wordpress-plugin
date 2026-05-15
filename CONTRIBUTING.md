# Contributing to Adbot Tracking Platform

Thanks for your interest in improving the Adbot WordPress plugin! This project is open source under GPLv2+, and we welcome bug reports, feature suggestions, documentation fixes, and code contributions from anyone.

By participating you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

---

## Ways to contribute

- **Report a bug** — open an issue using the *Bug report* template.
- **Suggest a feature** — open an issue using the *Feature request* template.
- **Improve documentation** — `README.md`, `adbot/readme.txt`, or `docs/`.
- **Fix something** — pick up an issue labelled `good first issue` or `help wanted`, then open a PR.
- **Report a security issue** — privately, via the process in [SECURITY.md](SECURITY.md). Do **not** open a public issue.

## Development setup

You will need:

- **Node.js 18+** and **npm**
- **PHP 8.0+** and **Composer** (only if you rebuild `vendor/` for a release)
- **Docker Desktop** (for `@wordpress/env`)

```bash
# 1. Fork and clone
git clone https://github.com/<your-user>/adbot-wordpress-plugin.git
cd adbot-wordpress-plugin

# 2. Install JS deps and build the admin bundle
npm ci
npm run build

# 3. Start a local WordPress with the plugin mounted
npx @wordpress/env start
```

Open <http://localhost:8888/wp-admin> (login: `admin` / `password`) and activate **Adbot Tracking Platform** under *Plugins*.

The plugin talks to the **hosted Adbot backend** (`https://adbot-tracking-platform.vercel.app`), so contributors normally do **not** need to run their own Supabase / Vercel / Paystack stack. If you do, copy `.env.example` to `.env.local` and fill in your own values.

### Site verification (Google Connect)

The backend verifies your WordPress site by calling `/wp-json/adbot/v1/verify?challenge=…` over the public internet. `http://localhost:8888` will not work for this flow. Use a tunnel such as [ngrok](https://ngrok.com) or [Cloudflare Tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/) if you need to test the connect flow end-to-end.

## Coding standards

- **PHP** — follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/). All output is escaped at the boundary; all input is sanitized.
- **JS / CSS** — `npm run lint:js` and `npm run lint:css` must pass.
- **Translations** — keep the text domain `adbot`. Update `adbot/languages/adbot.pot` for new strings if your editor supports it (not required for first-time contributors).

## Branching and PR workflow

1. Create a feature branch off `main`: `git checkout -b fix/short-description`.
2. Make focused commits — descriptive messages, present tense ("Fix audit retry loop", not "fixed").
3. Run lint before pushing:
   ```bash
   npm run lint:js
   npm run lint:css
   ```
4. If your change is user-visible, update `adbot/readme.txt` (the WordPress.org-style readme).
5. Push to your fork and open a PR against `main`. The PR template will prompt you for a summary, test plan, and a checklist.
6. A maintainer will review. Be ready for revision rounds — bigger or security-adjacent changes get more scrutiny.

### What makes a PR easy to merge

- One logical change per PR. If you're tempted to add "while I was in there" cleanup, open a separate PR.
- Screenshots or short clips for UI changes.
- A clear test plan: what you did to verify the change works and didn't regress anything.
- No bundled changes to `adbot/build/` unless you've also changed `src/` — the build output is regenerated on release.
- No secrets, OAuth client IDs, tokens, or `.env*` files. Ever.

## Testing

Automated tests are limited today. Until that improves, please document the manual smoke steps you ran:

- Plugin activates and deactivates cleanly.
- Settings screen renders without console errors.
- Tracking audit completes on a test site.
- A fix can be applied (with entitlement) or correctly blocks (without).

If you add testable code, prefer pure helpers under `adbot/includes/` so we can grow a PHPUnit suite over time.

## Build and release

Maintainers cut releases with:

```bash
bash dev/tools/build-release.sh
```

This produces `dist/adbot-tracking-platform.zip`. Tagging and uploading to WordPress.org is maintainer-only.

## Communication

- **Bugs / features** — GitHub Issues.
- **Security** — `keegan@adbot.co.za` (see [SECURITY.md](SECURITY.md)).
- **General questions** — open a Discussion if enabled, otherwise an Issue with the `question` label.

Thanks again for contributing.
