=== Adbot ===
Contributors: keegalix
Tags: analytics, marketing, tag-manager, tracking, audit
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your Google marketing stack in one click. Inject a Google Tag Manager container and audit your tracking setup.

== Description ==

Adbot helps you connect and maintain your marketing measurement stack — Google Tag Manager, Google Analytics 4, Google Ads, and Google Search Console — from a single WordPress admin screen.

Features:

* Guided onboarding wizard.
* Google OAuth connection to your Google marketing accounts.
* One-click Google Tag Manager container injection (head + body snippet) with an admin-exclusion toggle.
* Automated audit of your GTM container (tags, triggers, gaps, a tracking health score).
* Optional paid audit-apply feature via Paystack.

= External Services =

This plugin sends data to third-party services to provide its functionality. **No external requests are made until you explicitly start the connection flow in the plugin UI.** The plugin stores an opt-in flag (`adbot_consent_given`) and refuses to contact any external service until the flag is true.

The plugin communicates **only** with the Adbot backend service (`api.adbot.co.za`). The Adbot backend then relays authorized requests to the following services on your behalf:

* **Adbot backend** (`https://api.adbot.co.za`) — the proxy/service your WordPress site talks to. Terms & privacy: https://adbot.co.za
* **Google OAuth and Google APIs** (called server-side by the Adbot backend) — authenticates and accesses the Google services you choose to connect (Tag Manager, Analytics, Ads, Search Console). Terms: https://policies.google.com/terms · Privacy: https://policies.google.com/privacy
* **Supabase** (called server-side by the Adbot backend) — stores the account linkage and encrypted OAuth tokens. Terms: https://supabase.com/terms · Privacy: https://supabase.com/privacy
* **Paystack** (called server-side by the Adbot backend) — processes payments if you enable the paid audit-apply feature. Terms: https://paystack.com/terms · Privacy: https://paystack.com/privacy

Data sent from your WordPress site to the Adbot backend: site URL, site name, WordPress version, admin email (on first activation only), and — per feature — the Google container you choose, the audit parameters you run, and the payment reference you verify. The WordPress site never sees, stores, or transmits your Google OAuth tokens directly; those live on the Adbot backend and are encrypted at rest.

== Installation ==

1. Upload the `adbot` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the Adbot menu in the WordPress admin.
4. Review the external-services disclosure on the Settings tab, then tick the consent toggle.
5. Follow the onboarding wizard to connect Google and install your GTM container.

== Frequently Asked Questions ==

= Does this plugin make external requests on activation? =

No. Activation only seeds local defaults and sets the consent flag to false. External requests occur only after you tick the consent toggle AND explicitly start a connection or feature in the plugin UI.

= Where are my OAuth tokens stored? =

Access and refresh tokens are encrypted with a key you configure (via the `ADBOT_ENCRYPTION_KEY` constant in `wp-config.php` or the plugin Settings screen) and sent to the Adbot backend (Supabase) for use during API calls. Tokens are never logged and never written to your WordPress database in plaintext.

= How do I revoke Google access? =

Click "Disconnect" on the Adbot Connect tab. This removes the stored account link on the Adbot backend and clears local references. You can also revoke access at https://myaccount.google.com/permissions.

= How do I cancel a Paystack payment or get my data removed? =

Contact support via https://adbot.co.za. Uninstalling the plugin also removes all local Adbot data from your WordPress site.

= Do I need to configure anything? =

No. The plugin works out of the box. There are no secrets, API keys, OAuth client IDs, or `.env` files to set up. All third-party credentials are held by the Adbot backend service; the plugin communicates with that service via a per-site token it mints automatically on first use.

= Optional developer overrides =

Define either in `wp-config.php` (both optional):

* `ADBOT_API_BASE` — point the plugin at a staging Adbot backend (defaults to `https://api.adbot.co.za/wp/v1`).
* `ADBOT_DEBUG` — verbose error logging when `WP_DEBUG` is on.

== Screenshots ==

1. Setup wizard.
2. Connection status.
3. Audit results.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Credits ==

* Adbot brand and logo: © Adbot. Used with permission for the official Adbot plugin.
* Google product logos (Tag Manager, Google Analytics, Google Ads, Google Search Console) are trademarks of Google LLC. Used for identification purposes in accordance with Google's brand guidelines.
