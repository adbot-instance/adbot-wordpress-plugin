=== Adbot Tracking Platform ===
Contributors: keegankelly
Tags: analytics, marketing, tag-manager, tracking, audit
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.14
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Google Tag Manager, GA4, Search Console, and Merchant Center in one click, then audit your tracking setup from one WordPress screen.

Source code and build tools: https://github.com/adbot-instance/adbot-wordpress-plugin

== Description ==

Adbot helps you connect and maintain your marketing measurement stack from a single WordPress admin screen: Google Tag Manager, Google Analytics 4, Google Search Console, and Google Merchant Center.

Features:

* Guided onboarding wizard.
* Google OAuth connection to your Google marketing accounts.
* One-click Google Tag Manager container injection (head + body snippet) with an admin-exclusion toggle.
* Automated audit of your GTM container (tags, triggers, gaps, a tracking health score).
* Google Search Console: Adbot proves you own the site for you, adds the property, and submits your WordPress sitemap.
* Google Merchant Center (requires WooCommerce): Adbot publishes a product feed on your own site and registers it in Merchant Center, so Google fetches your products daily.
* Optional paid audit-apply feature via Paystack.

= External Services =

This plugin sends data to third-party services to provide its functionality. **No external requests are made until you explicitly start the connection flow in the plugin UI.** The plugin stores an opt-in flag (`adbot_consent_given`) and refuses to contact any external service until the flag is true.

The plugin communicates **only** with the Adbot Tracking backend service (`https://adbot-tracking-platform.vercel.app`). The same API will later be served from `https://tracking.adbot.co.za` when DNS is migrated; until then the plugin uses the Vercel deployment URL. The Adbot backend then relays authorized requests to the following services on your behalf:

* **Adbot Tracking backend** (`https://adbot-tracking-platform.vercel.app/api/wp`). The proxy/service your WordPress site talks to. Terms & privacy: https://adbot.co.za
* **Google OAuth and Google APIs** (called server-side by the Adbot backend). Authenticates and accesses the Google services you choose to connect (Tag Manager, Analytics, Search Console, Merchant Center, Site Verification). Search Console and Merchant Center access is requested separately, only when you open that card and grant it. Terms: https://policies.google.com/terms · Privacy: https://policies.google.com/privacy
* **Supabase** (called server-side by the Adbot backend). Stores the account linkage and encrypted OAuth tokens. Terms: https://supabase.com/terms · Privacy: https://supabase.com/privacy
* **Paystack** (called server-side by the Adbot backend). Processes payments if you enable the paid audit-apply feature. Terms: https://paystack.com/za/terms · Privacy: https://paystack.com/za/terms?q=/privacy

Data sent from your WordPress site to the Adbot backend: site URL, site name, WordPress version, admin email (when the site first registers with the backend), and, per feature, the Google container you choose, the audit parameters you run, the payment reference you verify, the Search Console property and sitemap URL you submit, and the Merchant Center account and product-feed URL you register. The WordPress site never sees, stores, or transmits your Google OAuth tokens directly; those live on the Adbot backend and are encrypted at rest.

**Your product data is not sent to the Adbot backend.** If you use the Merchant Center feature, the plugin publishes a product feed at an unguessable URL on your own site (`/adbot-feed/<random token>/products.xml`) and tells Merchant Center where to find it. Google then fetches that feed from your site directly, on a daily schedule. Product titles, prices, images and identifiers therefore travel from your site to Google, never through Adbot. The feed URL is not linked from your site and is served with `X-Robots-Tag: noindex`, so search engines do not index it. It is deliberately *not* blocked in `robots.txt`, because Merchant Center's scheduled fetch obeys `robots.txt` and would otherwise be refused. Anyone who has the URL can read it, so treat it as private.

**Public output added to your site.** When you verify Search Console ownership, the plugin adds a `<meta name="google-site-verification">` tag to your site's `<head>`. It must stay published for Google to keep recognising your ownership (the same proof is what lets Merchant Center claim your store website). It is removed when you disconnect Google.

== Installation ==

1. Upload the `adbot` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the **Adbot** menu in the WordPress admin.
4. In the Adbot admin screen, open **Settings** in the sidebar to review the external-services disclosure. Enable the consent toggle there before connecting, or follow the onboarding wizard / Google connection flow (consent may be recorded when you start a connection).
5. Complete onboarding to connect Google and install your GTM container when ready.

== Frequently Asked Questions ==

= Does this plugin make external requests on activation? =

No. Activation only seeds local defaults and sets the consent flag to false. External requests occur only after consent has been granted (via the Settings toggle or when starting a flow that records consent, such as Google connection) and the plugin performs the corresponding action.

= Why does the plugin say the site is still registering or “not finished registering”? =

Before Google OAuth, the plugin registers your site with the Adbot backend and stores a per-site token. The backend verifies ownership by requesting a public URL on your WordPress site (`/wp-json/adbot/v1/verify`). Your **Settings → General → Site Address (URL)** must therefore be reachable from the internet (for example a live HTTPS domain or an HTTPS tunnel). Installations that only use `http://localhost` as the site URL generally cannot finish registration because the backend cannot reach that address.

= Where are my OAuth tokens stored? =

Google OAuth access and refresh tokens are **not** stored in your WordPress database. They are held and encrypted **on the Adbot backend** (and associated storage). The plugin only keeps an encrypted **site token** (used to authenticate API calls to the backend). That site token is encrypted at rest in WordPress using AES-256-GCM with a key derived from your `wp-config.php` authentication salts (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`). No separate encryption key is required from you for OAuth tokens.

= Do I need WooCommerce? =

Only for the Merchant Center product feed. Everything else — Tag Manager, Analytics, the audit, and Search Console — works on any WordPress site. The Merchant Center card tells you when WooCommerce is missing instead of failing.

= How do I show a backorder date in Merchant Center? =

Google only accepts "backorder" availability together with a restock date, and WooCommerce has no restock-date field. So a product on backorder is sent as "out of stock" unless Adbot knows when it will be available. To supply one, set the product (or variation) meta field `_adbot_availability_date` to a date such as `2026-12-01`, or return one from the `adbot_product_feed_availability_date` filter (arguments: the date string and the product). The date must be in the future and no more than one year ahead; anything else is ignored.

= I sell used or refurbished products. How do I set the condition? =

Every product is sent to Merchant Center as "new" by default, because WooCommerce has no condition field. Return `refurbished` or `used` from the `adbot_product_feed_condition` filter (arguments: the condition, the product, and the parent product for a variation); any other value falls back to `new`. Products with no GTIN and no brand + MPN pair are sent with `identifier_exists` set to "no"; the `adbot_product_feed_identifier_exists` filter overrides that per product.

= Adbot says my sitemap cannot be submitted. Why? =

The plugin submits WordPress's own sitemap (`/wp-sitemap.xml`). SEO plugins such as Yoast SEO, Rank Math, All in One SEO and SEOPress switch that off and serve their own sitemap instead. When Adbot detects that, it names the plugin and asks you to submit that plugin's sitemap in Search Console directly, rather than submitting a URL that would return an error.

= Why does Google ask for permission twice? =

Search Console and Merchant Center are requested separately, and only when you open the matching card on the Channels screen. A site that only wants tag tracking is never asked for access to its Search Console data or its product catalogue. If you connected Google before these features existed, your existing connection keeps working and each card offers a one-time "Grant access" button.

= How do I revoke Google access? =

Click "Disconnect" on the Adbot Connect tab. This removes the stored account link on the Adbot backend and clears local references. You can also revoke access at https://myaccount.google.com/permissions.

= How do I cancel a Paystack payment or get my data removed? =

Contact support via https://adbot.co.za. Uninstalling the plugin also removes all local Adbot data from your WordPress site.

= Do I need to configure anything? =

No. The plugin works out of the box. There are no secrets, API keys, OAuth client IDs, or `.env` files to set up. All third-party credentials are held by the Adbot backend service; the plugin communicates with that service via a per-site token it mints automatically on first use.

= Optional developer overrides =

Define either in `wp-config.php` (both optional):

* `ADBOT_API_BASE`: point the plugin at a staging or alternate backend (defaults to `https://adbot-tracking-platform.vercel.app/api/wp`; omit trailing slash).
* `ADBOT_DEBUG`: verbose error logging when `WP_DEBUG` is on.

== Screenshots ==

1. Setup wizard.
2. Connection status.
3. Audit results.

== Changelog ==

= 1.0.14 =
* New: Google Search Console. Adbot confirms you own the site (by publishing Google's verification tag for you), adds the property, and submits your WordPress sitemap — with a clear explanation when another SEO plugin has taken sitemaps over.
* New: Google Merchant Center for WooCommerce stores. Adbot publishes a product feed on your own site and registers it in Merchant Center as a daily scheduled fetch, including variations, sale prices (with their scheduled start and end dates, so Google switches the price on time), brands, GTINs and stock status. The Merchant Center card warns when your shop shows prices excluding tax in a country where Google requires tax-inclusive prices. Developers can set each product's condition with the `adbot_product_feed_condition` filter. Your product data goes straight from your site to Google and never passes through Adbot.
* New: a Channels screen for both, plus Search Console and Merchant Center tiles on the dashboard.
* Improve: Search Console and Merchant Center permissions are requested only when you open that card, so connecting Google for tag tracking no longer asks for access you are not using. Existing connections keep working and are offered a one-time grant.
* Fix: uninstalling now removes several settings the plugin had been leaving behind in the database, including your selected GA4 measurement ID and snippet-detection results.

= 1.0.13 =
* Improve: rewrote the plugin description and the External services notes in plainer sentences. No functional changes.

= 1.0.12 =
* New: payments settled outside the checkout now unlock the fixes step on their own. If you paid by EFT and the Adbot team verified it, or you bought on adbot.co.za before installing the plugin, the wizard recognises it — you no longer have to pay a second time.
* Fix: disconnecting Google no longer made it look as though you had not paid. The payment is remembered independently of the Google connection.
* Fix: restarting the setup wizard no longer jumped straight back to the "Apply fixes" step on the next screen load.
* Improve: when the Adbot backend is briefly unreachable, the plugin now backs off instead of retrying on every admin screen, so the setup pages stay responsive.

= 1.0.11 =
* Fix: container, snippet, and settings selections (including Debug mode) could appear not to save on sites with aggressive caching such as LiteSpeed — the cache served stale admin data even though the change was saved. The plugin now instructs page/object/CDN caches never to store its authenticated admin REST responses, and flushes any stale entries once on update.
* New: the Connect tab and the onboarding flow now show the exact GTM `<head>` and `<body>` snippet code that is on your site.
* Improve: the onboarding tracking report now lists each issue found with its severity (instead of only counts), shows what is already in your GTM container (tags, triggers, variables), and recognises a brand-new empty container to guide first-time setup.
* Fix: corrected the "Apply fixes" request so the generated GTM setup plan is sent to the backend in the expected shape.

= 1.0.10 =
* Fix: payment failed with "Invalid request body" because the completed audit's id was not stored (the backend returns audit_id; the plugin read auditId). The audit id is now saved correctly, and the payment step shows a clear message if no audit has been run yet.

= 1.0.9 =
* Fix: the "Unlock fixes" step showed the price as 0. The plugin now displays the correct amount (default ZAR 4950, overridable via the ADBOT_FIX_PRICE / ADBOT_FIX_CURRENCY constants). The charge amount remains authoritative on the Adbot backend.

= 1.0.8 =
* Fix: selecting a container then running the audit failed with "No GTM container selected." The plugin now sends the container's public GTM-XXXX id (which the snippet and audit require) instead of the numeric container id, so the audit runs correctly.

= 1.0.7 =
* Improved: redesigned the Tag Manager container picker on the "Find property" step — accounts are grouped with counts, containers are shown as clear selectable cards with hover/focus/selected states, and a search box lets you filter by name, domain, or ID when you have many containers.

= 1.0.6 =
* Fix: the "Find property" step could error with "e.forEach is not a function" once containers loaded. The plugin now adapts the Google Tag Manager container list into the shape the setup UI expects, so your accounts and containers display correctly.

= 1.0.5 =
* Fix: resolved a loading loop on the setup wizard after connecting Google. A slow or temporarily unavailable backend response no longer bounces you back to the "Connect Google" step; the connection state is briefly cached and treated as stable across transient failures.

= 1.0.4 =
* Fix: the "Find property" step no longer times out while loading your Google Tag Manager containers. Google API calls are rate-limited server-side to respect quotas, so the plugin now allows these slower requests (container listing, audit, snippet install, and GTM publish) more time to complete. Results are cached for 5 minutes.

= 1.0.3 =
* Fix: the setup wizard now continues past "Connect Google" as soon as the account is connected, even when Google profile details aren't returned. Previously it could wait indefinitely on the connect screen after a successful sign-in.

= 1.0.2 =
* Fix: the Google sign-in popup now closes automatically when authorization completes and returns you to the setup wizard, which continues to the next step.
* Fix: clicking "Continue with Google" now forces site registration immediately and shows the real reason if it fails, instead of a generic "still registering" message.

= 1.0.1 =
* Maintenance release.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.13 =
Documentation only. The plugin description and External services notes are clearer; nothing functional changed.

= 1.0.12 =
Payments made by EFT or on adbot.co.za now unlock the fixes step automatically, without paying again.

= 1.0.10 =
Fixes "Invalid request body" when starting payment by correctly storing the audit id.

= 1.0.9 =
Fixes the payment step showing the price as 0.

= 1.0.8 =
Fixes "No GTM container selected" on the audit step after choosing a container.

= 1.0.7 =
Redesigned, searchable Google Tag Manager container picker on the Find property step.

= 1.0.6 =
Fixes a "forEach is not a function" error on the Find property step when loading GTM containers.

= 1.0.5 =
Fixes a loading loop on the setup wizard after connecting your Google account.

= 1.0.4 =
Fixes the "Find property" step timing out while loading Google Tag Manager containers.

= 1.0.3 =
Fixes the setup wizard getting stuck on the "Connect Google" step after a successful sign-in.

= 1.0.2 =
Fixes Google sign-in: the popup now closes and the wizard continues automatically, and connection errors are reported clearly.

= 1.0.1 =
Maintenance release.

= 1.0.0 =
Initial release.

== Credits ==

* Adbot brand and logo: © Adbot. Used with permission for the official Adbot plugin.
* Google product logos (Tag Manager, Google Analytics, Google Ads, Google Search Console) are trademarks of Google LLC. Used for identification purposes in accordance with Google's brand guidelines.
