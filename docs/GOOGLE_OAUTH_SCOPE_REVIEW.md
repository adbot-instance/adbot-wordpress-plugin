# Google OAuth Scope Review — Search Console & Merchant Center (release 1.0.14)

Material for the **incremental** sensitive-scope verification of the Adbot
Tracking Platform OAuth client. The consent screen is already in production and
verified for the existing Tag Manager / GA4 / userinfo scopes; this submission
adds three scopes only:

| Scope | Requested in bundle | Google's description |
|---|---|---|
| `https://www.googleapis.com/auth/webmasters` | `gsc` | View and manage Search Console data for your verified sites |
| `https://www.googleapis.com/auth/siteverification` | `gsc`, `gmc` | Manage the list of sites and domains you control |
| `https://www.googleapis.com/auth/content` | `gmc` | Manage your product listings and accounts for Google Shopping |

Bundles are defined in the backend (`lib/wp/google-oauth.ts`,
`WP_GOOGLE_SCOPE_SETS`) and requested by name only; the plugin cannot ask for a
raw scope string (`Auth_Controller::SCOPE_SETS`, re-validated server-side). The
auth URL uses `include_granted_scopes=true`, so each grant **extends** the
existing GTM/GA4 grant instead of replacing it (see
`docs/ADBOT_BACKEND_CONTRACT.md` → *Google OAuth*).

Anything marked **[confirm in backend]** depends on code in the Vercel backend
repository, which is not in this repo. Check it before pasting.

> **Timing.** Google's sensitive-scope docs say new sensitive scopes "require
> review by Google before any Google Account can grant access", and review can
> take up to about 10 days. Until approval, users who click *Grant Search
> Console access* / *Grant Merchant Center access* will see the unverified-app
> warning. Either hold the 1.0.14 release until approval, or ship it knowing the
> Search Console and Merchant Center pages show that warning in the meantime.
> Nothing else in the plugin (GTM/GA4 connect, audit, payments) is affected,
> because the new scopes are only requested from those two pages.

> **Tier check.** Before submitting, open Google Auth Platform → **Data Access**,
> add the three scopes, and confirm which table each lands in (sensitive or
> restricted). This document assumes all three are **sensitive** (not restricted,
> so no CASA security assessment). If any shows as restricted, the process
> changes. Stop and re-plan.

---

## 0. What the feature does (overview for the reviewer)

Adbot is a WordPress plugin with a hosted backend. A site owner connects their
Google account once, during the plugin's setup (the wizard's Connect step), for
Tag Manager and GA4; **Settings → Connections** then shows and manages that
connection. Release 1.0.14 gives each Google service its own page in the plugin
menu (Analytics, Tag Manager, Search Console, Merchant Center, Business
Profile). The **Search Console** page (`src/pages/SearchConsole.js`) and the
**Merchant Center** page (`src/pages/MerchantCenter.js`) each start with a setup
card that asks for its own permission the first time it is used, and only when
it is used:

1. **Google Search Console** (`src/channels/SearchConsoleChannel.js`). Proves the
   user owns the WordPress site by publishing Google's site-verification meta
   tag in the site's `<head>`. It then adds the site as a Search Console property
   and submits the WordPress core sitemap (`/wp-sitemap.xml`).
2. **Google Merchant Center** (`src/channels/MerchantCenterChannel.js`;
   WooCommerce stores only). The user picks a Merchant Center account. Adbot
   claims the store website for that account and registers a product feed that
   the plugin serves from the site itself
   (`/adbot-feed/<32-char token>/products.xml`) as a daily scheduled fetch. The
   user can also ask Google to fetch it immediately.

The plugin never holds Google credentials. Every Google API call is made
server-side by the Adbot backend with the user's encrypted refresh token. The
WordPress plugin only talks to the Adbot backend (`Adbot\Backend\Client`).

**New in this release: performance charts.** Each service page also shows
read-only performance for the property or account the site owner chose:
GA4 reports (`analytics.readonly`, already verified for this app), Search
Console search analytics (`webmasters`) and Merchant Center product status and
performance (`content`). These reads use the same grants as the setup features,
are made only when the owner opens a page, are cached briefly and are never
stored long term. **Because GA4 reporting is a new use of an already verified
scope, update the app's verification details for `analytics.readonly` too.**

**Product data never transits Adbot.** Google fetches the feed directly from the
merchant's WordPress site. Adbot sends Google only the feed URL, the account id
and the feed settings (language, country label, time zone).

---

## (a) Per-scope justifications (ready to paste)

Paste each block into the **justification** field for that scope. Keep the
wording in sync with the demo video.

### `auth/webmasters`

> **Feature:** "Google Search Console" setup card on the Adbot plugin's Search Console page
> screen in WordPress admin. The site owner clicks "Grant Search Console
> access", then "Verify this site", picks the Search Console property, and
> clicks "Submit sitemap". Adbot then shows the property, the sitemap URL and
> when it was last submitted, on the card and on the plugin Overview. Below the
> setup card, the same page charts the property's search performance for the
> last 7, 28 or 90 days (clicks, impressions, CTR, average position, top
> queries and top pages), so the site owner can see how the site does in
> Google Search without leaving WordPress.
>
> **API methods (Search Console API v1):** `sites.list` (show the properties
> the user can access so they can pick the one for this WordPress site, and
> show their permission level), `sites.add` (add this WordPress site as a
> property once ownership is verified), `sitemaps.submit` (submit the site's
> WordPress sitemap), `sitemaps.list` (read the submitted sitemap's
> processing status in a once-daily status refresh), and
> `searchanalytics.query` (read the selected property's clicks, impressions,
> CTR and position, by date, query and page, for the performance charts).
> **[confirm in backend: exact method set, e.g. `sitemaps.list` vs `sitemaps.get`,
> once the `/api/wp/insights/gsc` route lands]**
>
> **Why a narrower scope does not work:** `webmasters.readonly` only allows
> reading. The two core actions of this feature are writes: `sites.add`
> creates a property and `sitemaps.submit` submits a sitemap. Both require
> `https://www.googleapis.com/auth/webmasters`. With the read-only scope the
> feature would only be able to list properties, so the user would still have
> to add the property and submit the sitemap by hand in Search Console, which is
> exactly what this feature removes. Adbot does not call the URL Inspection or
> sitemap-delete endpoints.
>
> **Data accessed:** the list of Search Console property URLs the user can
> access and their permission level, and the submitted sitemap's metadata
> (path, last submitted, last downloaded, pending flag, warning and error
> counts), and aggregated search performance for the selected property only
> (daily clicks, impressions, CTR and average position, plus the top queries and
> pages), read when the site owner opens the Search Console page or Overview.
>
> **Storage and retention:** Adbot stores the selected property URL, its
> permission level, the verification timestamp, the sitemap URL and the
> submission timestamp. These are kept against the user's connected site on the
> Adbot backend and in the user's own WordPress database, which also holds a
> once-daily snapshot of the sitemap status. The full property list is cached in
> WordPress for up to 5 minutes so the picker loads quickly. Search performance
> data is not stored: it is cached for up to 15 minutes in WordPress and briefly
> on the backend to respect Google's quotas, then discarded. Disconnect deletes
> the WordPress copies and the backend tokens; uninstalling the plugin deletes
> the WordPress copies. **[confirm in backend: that `WpSite.config.gsc` is
> cleared on `/auth/disconnect`, what happens to it after uninstall (the plugin
> makes no backend call on uninstall), and whether the backend caches the
> property list anywhere]**. It is never sold or shared, and never used for
> advertising or to train AI/ML models.

### `auth/siteverification`

> **Feature:** the "Verify this site" step of the Search Console card. The same
> ownership proof is what lets Google Merchant Center claim the store website
> on the Merchant Center card, so the user verifies once and both features
> work.
>
> **API methods (Site Verification API v1):** `webResource.getToken` with
> `verificationMethod=META` (get the verification token; the plugin renders it
> as `<meta name="google-site-verification">` in the site's `<head>`),
> `webResource.insert` (ask Google to check the tag and record the user as a
> verified owner), and `webResource.list` (show on the property picker which
> properties the user has verified ownership of, so the card only offers
> "Verify this site" when it is actually needed).
> **[confirm in backend: that `webResource.list` (or `get`) is really called to
> build the `verified` flag on `GET /gsc/sites`. `docs/ADBOT_BACKEND_CONTRACT.md`
> says "`verified` comes from the Site Verification API", which points to the
> full scope being needed, but check the code; see the note below]**
>
> **Why a narrower scope does not work:** `siteverification.verify_only`
> allows verifying *new* sites but gives "no read access for existing verified
> sites". Adbot reads the user's existing verifications so a site that is
> already verified is not sent through verification again, and so the card can
> say whether the selected property is verified.
>
> **Data accessed:** the verification token for this site and the list of
> sites and domains the user has verified with Google.
>
> **Storage and retention:** the token is stored in the user's WordPress
> database and published in the site's `<head>`. It must stay published,
> because Google re-checks it periodically. It is removed when the user clicks
> Disconnect or uninstalls the plugin. Adbot stores the verification timestamp
> only. The list of verified sites is used to render the picker and is not
> stored beyond the same 5-minute cache. **[confirm in backend]**

> **Read this before pasting the `siteverification` justification.** Google asks
> for the *narrowest* scope, and `siteverification.verify_only` is a real,
> narrower alternative. If the backend does **not** call `webResource.list` /
> `webResource.get` (that is, if `verified` on `GET /gsc/sites` is inferred from
> Search Console's `permissionLevel` instead), switch both bundles to
> `https://www.googleapis.com/auth/siteverification.verify_only` **before**
> submitting. `getToken` and `insert` work under it, and it is an easier review.
> Keep the full scope only if the read is needed, and then use the paragraph
> above.
>
> **Second check:** the `gmc` bundle also includes `siteverification`, but the
> Merchant Center card has no verify action of its own. Its copy tells the user
> to verify on the Search Console card first. A reviewer watching the GMC part
> of the video will see `siteverification` on the GMC consent screen with no
> matching action. Either (i) add a "Verify this site" action to the Merchant
> Center card, or (ii) say plainly in the justification that the GMC bundle
> includes it so a merchant who has not used the Search Console card can still
> have ownership verified before the claim. Also confirm that `/gmc/claim`
> works when only the `gmc` bundle is granted. **[confirm in backend]**

### `auth/content`

> **Feature:** "Google Merchant Center" setup card on the Adbot plugin's Merchant Center page
> screen (WooCommerce stores only). The merchant clicks "Grant Merchant Center
> access", picks their Merchant Center account, clicks "Claim website", then
> "Register product feed", and optionally "Fetch now". Adbot then shows the
> claim date, the feed URL, the registration date and the target country and
> language. Below the setup card, the same page charts how the catalogue is
> doing in Google: product counts by status (approved, limited, disapproved)
> and, where the Merchant API provides it, performance over time.
>
> **API methods (Merchant API v1: `accounts_v1`, `datasources_v1`):**
> `accounts.list` (show the Merchant Center accounts, including sub-accounts of
> an advanced account, so the merchant can pick one; also used to re-check that
> a selected account is still reachable before saving it),
> `accounts.homepage.claim` (claim the store website for the chosen account,
> without `overwrite`), `dataSources.list` + `dataSources.create` /
> `dataSources.patch` (create or update a single primary product data source
> named "Adbot — <host>" whose `fileInput.fetchSettings` points at the
> merchant's own feed URL, fetched daily), `dataSources.get` (show the data
> source's status in the daily status refresh), and `dataSources.fetch` (the
> "Fetch now" button), plus read-only product-status and performance reports
> for the performance charts. **[confirm in backend: exact method set, including
> which Merchant API sub-API (`reports_v1` and/or `products_v1` statuses) the
> `/api/wp/insights/gmc` route calls]**. The Content API for Shopping v2.1
> is not used.
>
> **Why a narrower scope does not work:** `https://www.googleapis.com/auth/content`
> is the only OAuth scope the Merchant API and the Content API for Shopping
> accept; Google publishes no read-only or per-resource alternative. The
> feature also has to write: claiming the homepage and creating the data
> source both change the account.
>
> **Data accessed:** Merchant Center account ids and display names, a
> test-account flag, the homepage claim status, and Adbot's own data source.
> For the charts, Adbot reads aggregated product status counts and, where
> available, performance reports for the selected account. Adbot does **not**
> insert or modify product records through the API.
> Products reach Merchant Center only because Google fetches the merchant's
> feed directly from the merchant's own WordPress site, so product data never
> passes through Adbot's servers.
>
> **Storage and retention:** Adbot stores the selected account id and name, the
> claim timestamp, the data-source id and name, the feed URL and the
> registration timestamp. WordPress also keeps a once-daily snapshot of the feed
> status, and caches the account list for up to 5 minutes for the picker.
> Disconnect deletes the WordPress copies and the backend tokens; uninstall
> deletes the WordPress copies. **[confirm in backend: `WpSite.config.gmc`
> cleared on disconnect, and what happens to it after uninstall]**. The Merchant
> Center data source itself stays in the merchant's account until the merchant
> removes it. It is never sold or shared, and never used for advertising or to
> train AI/ML models.

### Background access (applies to all three; add to whichever field asks about offline use)

> Adbot requests offline access so the plugin can refresh status once a day
> while no user is present. The daily job (`adbot_daily_channel_sync`) makes
> **read-only** status calls: the submitted sitemap's status (Search Console),
> and the data source and claim status (Merchant Center). It runs only for sites
> that have selected a property or registered a feed. It never adds sites,
> submits sitemaps, claims websites or changes data sources. It stops when the
> plugin is deactivated.

> **Gap to close before submitting (Limited Use).** The daily job stores its
> result in `adbot_channel_status` (`Sync::snapshot()`), but in this working
> tree **nothing in the UI displays it**. `getSitemapStatus()` /
> `getProductFeedStatus()` in `src/api/gsc.js` / `gmc.js` are not imported by
> any component, and the cards show only local timestamps. Limited Use requires
> data to serve a feature that is prominent in the UI. Either (i) render the
> sitemap status (last downloaded / warnings / errors) and the data-source
> status on the cards before recording the video, or (ii) remove the background
> read and the `sitemaps.list` / `dataSources.get` mentions above. Do not claim
> the status is shown until it is.

---

## (b) Demo video script (shot by shot)

**Upload:** YouTube, **Unlisted**, link pasted into the verification form.
**Language:** the browser and Google account language must be **English**, so
the consent screen renders in English. **Length:** aim for about 5–7 minutes,
without cutting away from any OAuth screen.

### Demo-site prerequisites (set these up before recording)

- A public HTTPS WordPress site (Google has to fetch it; `localhost` will not
  work). `Store::feed_readiness()` blocks feed registration over plain HTTP.
- Adbot 1.0.14 installed. Google already connected under **Settings → Connections** for
  GTM/GA4 (the previously verified grant), with neither channel bundle granted
  yet. Use a fresh test Google account, or revoke the app at
  myaccount.google.com/permissions and reconnect for GTM/GA4 only. The
  service pages are locked (`isOpenPath()` in `src/components/Layout.js`) until
  Google is connected; only Overview and Settings stay open.
- The demo site must **not already be verified** for the test Google account
  in Search Console. "Verify this site" only appears while `thisSiteVerified` is
  false, so shots 8–9 cannot be recorded otherwise. Remove any earlier
  verification and property for that account first.
- **Core WordPress sitemaps on:** no Yoast SEO, Rank Math, All in One SEO or
  SEOPress active (`Sitemap::conflicting_plugin()` would block the submit
  step). Check that `/wp-sitemap.xml` loads.
- WooCommerce active, with a few published products and the store country set
  (WooCommerce → Settings → General); otherwise the feed is blocked.
- A Merchant Center account the test Google user can access, whose website is
  **not** already claimed by another account. The claim is sent without
  `overwrite`, so an existing claim makes it fail.
- Any page cache purged. The plugin purges the common ones itself, but a CDN
  cache can still serve a stale `<head>`.
- The browser zoomed so the 600×700 OAuth popup's address bar is readable. If
  it is not readable, the popup can be dragged larger; reviewers need to read
  `client_id=` in the URL.

### Shots

| # | Screen | Action / what to say | Must be visible |
|---|---|---|---|
| 1 | Google Cloud console → Google Auth Platform → **Clients** | Open the production web OAuth client. Say: "This is the Adbot Tracking Platform OAuth client." | The client ID (keep the secret hidden), and the Authorized redirect URI(s) `…/api/wp/oauth/google/return` |
| 2 | Google Auth Platform → **Branding** and **Data Access** | Show the app name, logo, homepage, privacy policy and terms links, and the three new scopes listed alongside the existing ones. | App name matches the consent screen; scopes match this submission |
| 3 | WordPress admin → **Adbot → Settings → Connections** | Show that Google is already connected (the existing verified grant, for GTM/GA4). | Connected account email |
| 4 | **Adbot → Search Console** (then later **Adbot → Merchant Center**) | Explain that each service asks for its own permission. The page shows **Ready to connect**. | Search Console card and Merchant Center card |
| 5 | Search Console card → **Grant Search Console access** | The popup opens. Pause on the Google account chooser if it appears. | |
| 6 | Consent popup (GSC) | **Pause 3–5 s.** Point to the address bar, then the app name, then the list of requested permissions. | `client_id=<your client id>` in the URL, app name "Adbot Tracking Platform" (or the exact verified brand name), the new webmasters and siteverification permissions must be readable (how Google lists previously granted scopes on an incremental grant varies). Tick any unticked boxes on camera. |
| 7 | Back on the Search Console page | The card refreshes on window focus and now shows **Verify this site** and the property picker. | State badge changes |
| 8 | Search Console card → **Verify this site** | Say: "Adbot gets a verification token from the Site Verification API and adds Google's verification meta tag to this site." Show the success notice "Ownership confirmed and the property was added." | Notice text |
| 9 | New tab: site homepage → View Source | Search for `google-site-verification`. | The meta tag in `<head>` (`Site_Verification::inject_meta`) |
| 10 | Back to the card → **property picker** | Pick the site's property. Say: "This list comes from the Search Console API (`sites.list`)." | The property URL and permission level |
| 11 | Search Console card → **Submit sitemap** | Show "Submitted https://…/wp-sitemap.xml to Search Console." and the *Last submitted* time. | Sitemap URL, timestamp |
| 12 | search.google.com/search-console → that property → **Sitemaps** | Prove the write happened in Google: the property exists and the sitemap is listed. Optionally show **Settings → Ownership verification**, which lists the meta-tag method. | Property + sitemap in Search Console |
| 13 | **Adbot → Merchant Center** → **Grant Merchant Center access** | Popup. | |
| 14 | Consent popup (GMC) | **Pause 3–5 s**, as in shot 6. | `client_id=…`, app name, the "Manage your product listings and accounts for Google Shopping" permission (and siteverification) |
| 15 | Merchant Center card → **account picker** | Pick the account. Say: "Listed with the Merchant API `accounts.list`." | Account name/id |
| 16 | **Claim website** | Say: "This uses the ownership we verified in step 8 to claim the store website for this Merchant Center account." Show "Website claimed for this Merchant Center account." | *Website claimed* date |
| 17 | **Feed URL** link (new tab) | Open the feed. Say: "This XML is generated by WordPress on the merchant's own site. Google fetches it directly; Adbot never receives product data." | `/adbot-feed/<token>/products.xml` with a few `<item>`s |
| 18 | **Register product feed** | Show "Product feed registered. Google will fetch it daily." and the Target (for example `ZA · en`). | Registered date |
| 19 | **Fetch now** | Show "Asked Google to fetch the feed now." | Notice |
| 20 | merchants.google.com → Settings → **Data sources** (and Business info → Website) | Show the "Adbot — <host>" data source with the feed URL and daily schedule, and the website shown as claimed. After a short wait, show products arriving. | Data source + claimed website |
| 21 | **Adbot → Settings → Connections → Disconnect** → confirm | Say: "Disconnect revokes Adbot's Google tokens and removes the channel selections and the verification tag from this site." Note that **Disconnect is under Settings → Connections**, not on the service pages, and it revokes the whole grant (GTM/GA4 too). | Confirmation dialog, disconnected state |
| 22 | myaccount.google.com/permissions | Show Adbot is no longer listed (proof of revocation). **[confirm in backend: `/auth/disconnect` calls Google's token revocation endpoint]** | App absent |
| 23 | (optional) Site homepage → View Source | `google-site-verification` is gone. | Tag removed |

Spoken or captioned at the end: what each scope was used for (one sentence
each), and that product data is fetched by Google directly from the merchant's
site.

---

## (c) Privacy policy

The privacy policy must be on the same domain as the homepage (`adbot.co.za`),
linked from the homepage, and be the same URL as the one on the consent screen.
It must describe how Google user data is accessed, used, stored and shared,
naming the new data separately.

### Limited Use disclosure (paste on the privacy policy, and ideally the homepage or an FAQ)

The sample sentence is the one Google gives in its OAuth verification FAQ; the
rest is Adbot-specific. Re-check the current wording at
<https://support.google.com/cloud/answer/13463817> before publishing.

> Adbot's use and transfer to any other app of information received from Google
> APIs will adhere to the
> [Google API Services User Data Policy](https://developers.google.com/terms/api-services-user-data-policy),
> including the Limited Use requirements. Adbot uses Google user data only to
> provide the features you turn on in the Adbot plugin: installing and auditing
> Google Tag Manager, connecting Google Analytics 4, adding your site to Google
> Search Console and submitting its sitemap, and connecting your WooCommerce
> store to Google Merchant Center. We do not sell Google user data. We do not
> use it or transfer it for advertising, for credit-worthiness or lending
> decisions, or to train generalised or non-personalised AI or machine-learning
> models. No person at Adbot reads it unless you ask us to for support and give
> us permission, it is necessary for security purposes such as investigating
> abuse, or it is required by law.

### Additions needed to the privacy policy

1. **Search Console data.** We access the list of Search Console properties your
   Google account can access and their permission levels, so you can choose the
   one for your site. With your action we add your site as a property and submit
   its WordPress sitemap. We store the selected property URL, the sitemap URL and
   when they were verified and submitted. We read the sitemap's processing status
   (last downloaded, pending, warnings, errors) to show it in the plugin.
   *(Keep this sentence only once the status is actually displayed; see the
   Limited Use gap in (a).)* We do
   **not** access search performance data (queries, clicks, impressions).
2. **Site verification.** We obtain a Google site-verification token and publish
   it as a `<meta name="google-site-verification">` tag on your site. It stays
   published while you are connected, because Google re-checks it, and it is
   removed when you disconnect. We read the list of sites you have verified
   with Google to show whether your site is verified. *(Drop the last sentence
   if the backend moves to `siteverification.verify_only`.)*
3. **Merchant Center data.** We access your Merchant Center account ids and
   names so you can choose one. With your action we claim your store website
   for it and create one product data source pointing at your feed. We store the
   account id and name, the data-source id, the feed URL and timestamps.
4. **Product feed / product data never transits Adbot.** The plugin publishes
   your WooCommerce product feed at an unguessable URL on **your own** site.
   Google Merchant Center fetches it directly from your site, so product titles,
   prices, images, stock and identifiers go from your site to Google and never
   through Adbot's servers. Adbot only knows the feed URL. Anyone with the URL can
   read the feed. It is served with `X-Robots-Tag: noindex` but not blocked in
   `robots.txt`, because Merchant Center's fetch obeys `robots.txt`.
5. **Background processing.** Once a day, while you are connected, Adbot reads
   the status of your submitted sitemap and product data source so the plugin
   can show it. It makes no changes during this check.
6. **Where it is stored and who processes it.** Google OAuth tokens are
   encrypted at rest on the Adbot backend (hosted on Vercel) and stored with
   Supabase. Channel selections are also stored in your WordPress database.
   Name Vercel and Supabase as sub-processors. **[confirm in backend: the
   token-encryption method and where `WpSite.config` lives]**
7. **Retention and deletion.** Say how long data is kept, and that Disconnect
   (in the plugin, or by revoking access at myaccount.google.com/permissions)
   revokes the tokens and deletes the stored Search Console and Merchant Center
   selections. Uninstalling the plugin deletes its WordPress-side data. State
   that the Search Console property, the sitemap submission, the website claim
   and the Merchant Center data source remain in **your** Google accounts until
   you remove them there. The product feed URL also **keeps serving after
   Disconnect**: the feed token is kept on purpose, so a reconnect does not break
   the data source. It stops when the plugin is deactivated (the rewrite rule is
   flushed) or uninstalled (the token is deleted). State any backend retention period (for example
   backups, deleted within N days). **[confirm in backend]**
8. **Contact** for data-deletion requests.

---

## (d) Pre-submission checks

- [ ] **Authorized domain** `adbot.co.za` is listed under Branding → Authorized
      domains **and verified in Search Console** by a project Owner or Editor.
      `tracking.adbot.co.za` is covered by the `adbot.co.za` entry. Check what
      Branding → Authorized domains already lists for the current
      `adbot-tracking-platform.vercel.app` redirect URI (it passed the earlier
      verification), and leave that entry in place while installs still use it.
- [ ] **Homepage, privacy policy and terms** are live on `adbot.co.za`, publicly
      reachable without login, linked from the homepage, and exactly the URLs on
      the Branding page. The homepage describes the app and its Google features
      (including Search Console and Merchant Center).
- [ ] The **privacy policy includes the additions in (c)** and the Limited Use
      sentence before you submit. Reviewers check it against the justifications.
- [ ] **Redirect URI on the custom domain** is added to the OAuth client's
      Authorized redirect URIs, verbatim:
      `https://tracking.adbot.co.za/api/wp/oauth/google/return`.
      This is the real callback path, not `/auth/google/callback`; see the drift
      note in `docs/ADBOT_BACKEND_CONTRACT.md`. Keep the existing
      `https://adbot-tracking-platform.vercel.app/api/wp/oauth/google/return`
      entry while installs still point there.
      **[confirm in backend: the `WP_GOOGLE_REDIRECT_URI` value in the production
      environment]**
- [ ] The **plugin's default `ADBOT_API_BASE`** (`Client::DEFAULT_BASE`) still
      points at `adbot-tracking-platform.vercel.app`. If the demo video and
      submission use `tracking.adbot.co.za`, record the demo with
      `define( 'ADBOT_API_BASE', 'https://tracking.adbot.co.za/api/wp' );` in
      `wp-config.php`, or switch the default first, so that the redirect URI shown
      in the video matches the one being reviewed.
- [ ] **Scope set is final**: decide `siteverification` vs
      `siteverification.verify_only` (see (a)), and whether `gmc` really needs
      `siteverification`. Then update `WP_GOOGLE_SCOPE_SETS`, the Data Access
      page and this document together. Every scope on the Data Access page must
      appear in the video.
- [ ] **APIs enabled** in the same Cloud project: Google Search Console API,
      Site Verification API, Merchant API. **[confirm in backend / Cloud console]**
- [ ] **Merchant API developer registration (mandatory):** the Cloud project
      that owns the OAuth client must be registered once with
      `accounts.developerRegistration.registerGcp`, called from that project
      against an Adbot-owned Merchant Center account (test accounts are not
      eligible). Until it is, every Merchant API call from the project fails with
      401 `UNAUTHENTICATED`, so the GMC half of the demo cannot be recorded.
      It is a one-off for the project, not a per-merchant step.
      <https://developers.google.com/merchant/api/guides/quickstart/registration>
      **[confirm in backend / Cloud console that this has been done]**
- [ ] **Brand unchanged:** no branding edits during review. Google requires
      cancelling verification to change branding.
- [ ] **Support email and developer contact** on the Branding page are
      monitored. Google's follow-up questions arrive by email and an unanswered
      request stalls the review.
- [ ] The **demo video** was recorded against the production client, in English,
      with `client_id` readable on both consent screens, and is uploaded as
      Unlisted.
- [ ] The readme's *External services* text (`adbot/readme.txt`) matches the
      privacy policy (it already describes the GSC/GMC data and the feed).
- [ ] **Not in this submission:** Google Business Profile. That needs a separate
      GBP API access application (submit it now, for the next release) and its
      own scope (`auth/business.manage`). Do not add that scope to the Data
      Access page yet, because every declared scope must be demonstrated.

---

## Reference: where each claim comes from in this repo

| Claim | Source |
|---|---|
| Incremental, bundle-named scope requests | `adbot/includes/API/Auth_Controller.php` (`SCOPE_SETS`), `src/lib/oauthPopup.js` |
| GSC flow: verify → select → sitemap | `adbot/includes/API/GSC_Controller.php`, `src/channels/SearchConsoleChannel.js` |
| Meta tag published in `<head>` and kept until disconnect | `adbot/includes/Channels/Site_Verification.php` |
| Only the WordPress core sitemap is submitted; SEO-plugin detection | `adbot/includes/Channels/Sitemap.php` |
| GMC flow: account → claim → feed → fetch | `adbot/includes/API/GMC_Controller.php`, `src/channels/MerchantCenterChannel.js` |
| Feed served from the site, token-guarded, `noindex`, not in robots.txt | `adbot/includes/Feed/Feed_Endpoint.php` |
| Feed contents (WooCommerce products/variations) | `adbot/includes/Feed/Product_Feed.php` |
| Language, country label and time zone sent with the feed | `adbot/includes/Channels/Store.php` |
| 5-minute picker caches | `GSC_Controller::SITES_CACHE`, `GMC_Controller::ACCOUNTS_CACHE` |
| Daily read-only status job | `adbot/includes/Channels/Sync.php` |
| Disconnect clears selections and the tag, keeps the feed token | `Auth_Controller::disconnect()` |
| Uninstall cleanup | `adbot/uninstall.php` |
| Backend endpoints, Merchant API v1, redirect path | `docs/ADBOT_BACKEND_CONTRACT.md` |

---

## Sources checked (2026-11-13)

- Google Cloud: Verification requirements: <https://support.google.com/cloud/answer/13464321>
- Sensitive scope verification (demo video, client ID in the address bar, review time, adding scopes): <https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification>
- OAuth 2.0 scopes list (webmasters / webmasters.readonly, siteverification / siteverification.verify_only, content): <https://developers.google.com/identity/protocols/oauth2/scopes>
- Site Verification API scopes (`verify_only` = "no read access for existing verified sites"): <https://developers.google.com/site-verification/v1/getting_started>
- Google API Services User Data Policy (Limited Use): <https://developers.google.com/terms/api-services-user-data-policy>
- OAuth verification FAQ (sample Limited Use disclosure): <https://support.google.com/cloud/answer/13463817>
- Merchant API developer registration (mandatory; unregistered projects get 401): <https://developers.google.com/merchant/api/guides/quickstart/registration>
