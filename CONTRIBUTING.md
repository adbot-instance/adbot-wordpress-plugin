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

### UI and the Adbot design system

The plugin is one of three Adbot surfaces, alongside the Tracking Platform (the web app) and the Shopify app. All three follow one cross-product design system. The source of truth is `docs/design-system.md` in the Tracking Platform repo, and the Shopify app keeps the cross-product version that says how each product maps onto it. Those repos are private. Maintainers keep a local copy at `docs/design-system.md` here, which is gitignored, so it won't be in your clone. The rules that apply to plugin changes are:

- **Our brand, WordPress's chrome.** Adbot owns the colours, fonts, card shape, status tone names and wording. WordPress owns the admin menu, form controls, modals and notices. Use `@wordpress/components` for controls and re-tint them; don't port components from the other products.
- **Tokens, not hex values.** Colours, fonts and radii live as `--adbot-*` custom properties at the top of `src/index.scss`. Use those variables: `--adbot-primary` (#ff6900), `--adbot-primary-dark`, `--adbot-primary-light`, `--adbot-secondary`, `--adbot-ink`, `--adbot-border`, `--adbot-font-body` (Karla) and `--adbot-font-display` (P22 Mackinac). If you need a new value, add a token instead of hard-coding it.
- **One orange primary action per view.** Setup cards on a service page are the main task of that page, so their next step is the primary. The brand green (`--adbot-secondary`) is used sparingly and never on buttons.
- **Headings** use the display font at weight 500.
- **Status tones** have the same meaning in every product. Render them with `<StatusBadge tone>` from `src/components/StatusBadge.js`, and map a domain value to a tone with `toneFor()` there (for example `SEVERITY_TONE`). Never add a colour map inside a page or component.
  - *success*: connected, verified or healthy.
  - *warning*: works but needs attention.
  - *danger*: broken or blocks setup (older components pass `error`, which means the same thing).
  - *info*: a neutral fact, or something in progress.
  - *accent*: available and ready to connect.
  - *neutral*: coming soon or disabled.

  Status pills are sentence case, 12px semibold and fully rounded, never uppercase.
- **Don't change the brand orange in this plugin alone.** White text on `#ff6900` fails WCAG AA contrast. That is a brand-level decision to make once across all three products.
- **Navigation:** the menu is *Overview*, then one item per Google service (*Analytics*, *Tag Manager*, *Search Console*, *Merchant Center*, *Business Profile*), then *Settings*, the same order as the Shopify app. A service page uses `ProductPage` (`src/components/ProductPage.js`) and, where it has sub-screens, `UrlTabs` (Tag Manager: Container / Audit / Fix issues; Settings: Connections / General). Each service's status comes from `src/lib/productStates.js` so Overview, its page and Settings agree. Add a new feature to the service it belongs to rather than as a new menu item.
- **Charts:** use the components in `src/components/charts/` with the insights payload (`GET /adbot/v1/insights/<product>`). Series use `--adbot-chart-1…5` in order, one y-axis per chart, a legend for two or more series, and a table view. Never show sample or made-up numbers; an empty state says why.
- **Radius:** cards and panels use `--adbot-radius` (14px). Buttons, inputs and list rows use `--adbot-radius-control` (8px).

If a UI change needs something these rules don't cover, say so in your PR. A maintainer will check it against the full design system.

## Branching and PR workflow

The repository uses three kinds of long-lived branch:

| Branch | Purpose |
|--------|---------|
| `main` | The released plugin, matching what is published on WordPress.org. Never branch from it or target it with a PR. |
| `staging` | Integration branch. Each new release branch is cut from here. |
| `<version>-release` | The release currently in progress, e.g. `1.0.15-release`. Your work goes here. |

Releases increase by patch number (`1.0.14` → `1.0.15` → `1.0.16` …). See the **Branching and releases** section of [README.md](README.md) for how maintainers open and finish a release branch.

1. Find the open release branch (`git branch -r | grep -- '-release'`; use the highest version) and create your feature branch from it:
   ```bash
   git fetch origin
   git checkout -b fix/short-description origin/1.0.15-release
   ```
   If no release branch is open, branch from `staging` instead.
2. Make focused commits — descriptive messages, present tense ("Fix audit retry loop", not "fixed").
3. Run lint before pushing:
   ```bash
   npm run lint:js
   npm run lint:css
   ```
4. If your change is user-visible, update `adbot/readme.txt` (the WordPress.org-style readme).
5. Push to your fork and open a PR against the **same release branch you branched from** (or `staging` if you branched from it), never against `main`. The PR template will prompt you for a summary, test plan, and a checklist.
6. Do not bump the plugin version in your PR. The version (`adbot/adbot.php` header and `ADBOT_VERSION`, `Stable tag` in `adbot/readme.txt`) is set once by the maintainer when the release branch is opened. Do add a line for your change under that version's entry in the `== Changelog ==` section of `adbot/readme.txt`.
7. A maintainer will review. Be ready for revision rounds — bigger or security-adjacent changes get more scrutiny.

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

Releases are maintainer-only. When a release branch is complete:

1. The `<version>-release` branch is merged into `staging` and tested there.
2. `staging` is merged into `main`.
3. The ZIP is built from `main`:
   ```bash
   bash dev/tools/build-release.sh
   ```
   This produces `dist/adbot-tracking-platform.zip`, which is tagged and uploaded to WordPress.org SVN under the new version.
4. The next release branch (e.g. `1.0.16-release`) is cut from `staging`.

## Communication

- **Bugs / features** — GitHub Issues.
- **Security** — `keegan@adbot.co.za` (see [SECURITY.md](SECURITY.md)).
- **General questions** — open a Discussion if enabled, otherwise an Issue with the `question` label.

Thanks again for contributing.
