# Google Cloud setup checklist — GTM, GA4, GSC, GMC (1.0.14) and GBP (next release)

Status as of 2026-11-13. This is the console-side work that has to happen
outside this repo so the plugin's Google permissions work end to end. Code
changes are not covered here. Tick each box as it is done.

**What is already in place (supplied by the owner, not re-checked here):**

- The OAuth consent screen is **In production** and **verified** for the
  existing GTM / GA4 / userinfo scopes.
- `tracking.adbot.co.za` is attached to the Vercel backend project.
- The plugin's default `ADBOT_API_BASE` still points at
  `https://adbot-tracking-platform.vercel.app/api/wp`
  (`adbot/includes/Backend/Client.php`, `adbot/readme.txt`).
- Google Business Profile ships in the **next** release. The API access
  application goes in **now** because approval takes time.

**New scope bundles** (backend `lib/wp/google-oauth.ts`, `WP_GOOGLE_SCOPE_SETS`;
see `docs/ADBOT_BACKEND_CONTRACT.md` → `POST /auth/google/start`):

| Bundle | Google scopes |
|---|---|
| `gsc` | `https://www.googleapis.com/auth/webmasters`, `https://www.googleapis.com/auth/siteverification` |
| `gmc` | `https://www.googleapis.com/auth/content`, `https://www.googleapis.com/auth/siteverification` |
| *(next release)* GBP | `https://www.googleapis.com/auth/business.manage` |

Roles used below:

- **Project Owner**: a Google account with the Owner role on the Google Cloud
  project that holds the production OAuth client.
- **Backend dev**: whoever can change Vercel env vars and deploy the backend.
- **Merchant admin**: a user with ADMIN access on Adbot's own production
  (non-test) Merchant Center account.
- **GBP manager**: an owner or manager of a verified Business Profile.

---

## Release gate for 1.0.14

1.0.14 should not go to customers until every item below is ticked. The order
matters: later items depend on earlier ones.

- [ ] **G1.** The production redirect URI(s) are registered on the OAuth client,
      and a GTM-only connect still works (§1.3).
- [ ] **G2.** Search Console API, Site Verification API and Merchant API are
      enabled in the production project (§2).
- [ ] **G3.** The `gsc` and `gmc` scopes are added on the Data Access page (§3).
- [ ] **G4.** Incremental verification for those scopes is **approved**, not just
      submitted (§4). Until it is approved, Google may show customers who
      click "Connect Search Console / Merchant Center" an **"unverified app"**
      screen before consent, new users may be blocked, and a cap of **100 new
      users in total** applies
      (<https://support.google.com/cloud/answer/7454865>). Project members
      testing the flow will see the same screen. Decide whether to hold the
      release or ship GSC/GMC behind that screen.
      **Who:** Project Owner. **Where:** Verification Center (§4).
      **Confirm:** the added scopes show *Verified*.
- [ ] **G5.** The Merchant API developer registration is done for this project.
      A `gmc/accounts` call no longer returns the 401 "not registered" error,
      including against a store you do not own (§5).
- [ ] **G6.** An end-to-end check on a real WooCommerce site works:
      connect → pick site → verify → sitemap, and connect → pick merchant →
      claim → feed → fetch.
      **Who:** the plugin developer, using a non-admin test Google account.
      **Where:** the plugin's *Search Console* and *Merchant Center* pages on a staging WordPress site that
      points at the production backend.
      **Confirm:** the sitemap appears in Search Console, and the
      `Adbot — <host>` data source shows fetched products in Merchant
      Center.
- [ ] **G7.** Quota alerts exist for Tag Manager API and Merchant API (§7).

Not part of the gate, but due **now**: the GBP Basic API Access application (§6).

---

## 1. Domain and OAuth client

### 1.1 Verify `adbot.co.za` in Search Console

- [ ] **Who:** Project Owner (or an Editor on the project). The Google account
      that verifies the domain **must** be an Owner or Editor on the Cloud
      project. If a different account verifies it, the OAuth verification team
      cannot see that verification.
- **Where:** <https://search.google.com/search-console> → *Add property* →
  **Domain** property `adbot.co.za` (DNS TXT record). Verify the **top private
  domain** (`adbot.co.za`). A subdomain such as `tracking.adbot.co.za` is
  covered by it.
- **Confirm:** the property shows `adbot.co.za` with you as a *Verified owner*
  under *Settings → Users and permissions*. A new DNS record can take up to
  about 24 hours to verify.
- **Depends on:** nothing. Do this first, because §1.2 and §4 rely on it.
- Note: `vercel.app` is a public suffix, so the "top private domain" of the
  current host is `adbot-tracking-platform.vercel.app` itself. That host was
  presumably handled during the earlier GTM/GA4 verification. The custom domain
  is easier to defend in review.
- Ref: <https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification>

### 1.2 Authorized domains and branding

- [ ] **Who:** Project Owner.
- **Where:** Google Cloud console → *Google Auth Platform* → **Branding**
  (<https://console.cloud.google.com/auth/branding>; the console path may
  differ, so confirm in the console) → *Authorized domains*.
- [ ] Add `adbot.co.za`. Keep the existing entry that covers the `vercel.app`
      host during the transition.
- [ ] Check that the home page, privacy policy and terms URLs are on a verified
      domain. The privacy policy must be on the same domain as the home page and
      linked from the consent screen. If they move to `adbot.co.za`, update them
      here.
- **Confirm:** both hosts are listed, and *Branding* shows no "domain not
  verified" warning. Every domain used by a redirect URI in §1.3 must appear in
  this list.
- **Depends on:** §1.1.
- **Caution (confirm in console):** editing branding (name, logo, domains) on
  a verified app may need re-verification. Change only what the migration
  needs, and do it together with the §4 submission so there is only one review
  round.

### 1.3 Redirect URIs on the production OAuth client

> ⚠ **Path discrepancy, so check before adding anything.** The contract's route
> heading (`GET /auth/google/callback`) and its sample `auth_url` name
> `/api/wp/auth/google/callback`. The backend contract
> (`docs/ADBOT_BACKEND_CONTRACT.md`, "Code drift", verified against
> `app/api/wp/oauth/google/return/route.ts`) says Google actually receives
> **`/api/wp/oauth/google/return`**, read from the `WP_GOOGLE_REDIRECT_URI` env.
> That exact drift has already caused a live `Error 400: redirect_uri_mismatch`.
> **Register exactly the string that `WP_GOOGLE_REDIRECT_URI` holds (or will
> hold) in each Vercel environment.** If both paths really are live, register
> both.

- [ ] **Who:** Project Owner, together with the Backend dev, who reads the Vercel env.
- **Where:** *Google Auth Platform* → **Clients** (or *APIs & Services →
  Credentials*; <https://console.cloud.google.com/auth/clients>) → the production *Web
  application* client → *Authorized redirect URIs*.
- [ ] Read the current value: `vercel env ls` / `vercel env pull` for
      `WP_GOOGLE_REDIRECT_URI` in Production (and Preview, if used).
- [ ] **Keep** `https://adbot-tracking-platform.vercel.app/api/wp/oauth/google/return`
      for every installed plugin ≤ 1.0.14 that still uses the default base.
- [ ] **Add** `https://tracking.adbot.co.za/api/wp/oauth/google/return`
      (and the `/auth/google/callback` form only if the backend really serves it).
- **Confirm:** save the client. Changes can take a few minutes, sometimes
  hours, to take effect. Then start a connect from a test site and check that
  Google shows the consent screen and not `redirect_uri_mismatch`.
- **Depends on:** §1.2. The URI's domain must already be an authorized domain.

### 1.4 Later: switch the default host (after the gate, not before)

- [ ] **Backend dev:** set `WP_GOOGLE_REDIRECT_URI` (and any public base URL
      env) to the `tracking.adbot.co.za` value in Vercel Production, then
      redeploy. One backend serves both hosts, so the redirect it sends must
      match a URI registered in §1.3.
- [ ] **Plugin:** change the default `ADBOT_API_BASE` in
      `adbot/includes/Backend/Client.php` and the readme line to
      `https://tracking.adbot.co.za/api/wp`, in a later release.
- [ ] Remove the `vercel.app` redirect URI only when no supported plugin
      version still defaults to it. Until then, keep both.
- **Confirm:** a fresh install with no `ADBOT_API_BASE` override connects
  through `tracking.adbot.co.za`, and an older install still connects through
  `vercel.app`.

---

## 2. APIs to enable (production project)

**Who:** Project Owner. **Where:** *APIs & Services → Library*
(<https://console.cloud.google.com/apis/library>), search for each API → **Enable**.
**Confirm:** each one appears under *APIs & Services → Enabled APIs & services*.
**Depends on:** nothing, but this must be done before §5 and before any user
test.

Already in use (confirm they are still enabled; left untouched on 2026-11-13, previously enabled for the approved GTM/GA4 app):

- [ ] **Tag Manager API** (`tagmanager.googleapis.com`)
- [ ] **Google Analytics Admin API** (`analyticsadmin.googleapis.com`)
- [ ] **Google Analytics Data API** (`analyticsdata.googleapis.com`). Enable it
      only if the backend calls `runReport`. Check `lib/wp` in the backend
      first; the plugin repo cannot tell.

New for 1.0.14:

- [x] **Google Search Console API** (`searchconsole.googleapis.com`):
      sites and sitemaps.
- [x] **Google Site Verification API** (`siteverification.googleapis.com`):
      the META token and verify step, shared by GSC and the GMC homepage claim.
- [x] **Merchant API** (`merchantapi.googleapis.com`): `accounts_v1` and
      `datasources_v1`. Do **not** build on Content API for Shopping, which
      was sunset on 2026-08-18.

Next release (GBP). The basic setup guide says to enable these after §6 is
approved; enabling early is harmless, but they stay at 0 QPM until then.
Account Management and Business Information were enabled on 2026-11-13:

- [ ] Google My Business API
- [x] My Business Account Management API
- [x] My Business Business Information API
- [ ] My Business Verifications API
- [ ] My Business Notifications API
- [ ] My Business Place Actions API
- [ ] My Business Lodging API. Google lists all seven; enable it even if it
      goes unused.
- Ref: <https://developers.google.com/my-business/content/basic-setup>

---

## 3. Data Access: add the new scopes

- [ ] **Who:** Project Owner.
- **Where:** *Google Auth Platform* → **Data Access**
  (<https://console.cloud.google.com/auth/scopes>; confirm the path in the
  console) → *Add or remove scopes*.
- [ ] Before adding anything, write down the scopes that are already listed and
      approved (the base GTM/GA4/userinfo set). Compare them with the base list
      in backend `lib/wp/google-oauth.ts` and with any `WP_GOOGLE_SCOPES`
      override in Vercel. They must match. If the code requests a scope that is
      not on this page, that is an unapproved scope today.
- [ ] Add `.../auth/webmasters`
- [ ] Add `.../auth/siteverification`
- [ ] Add `.../auth/content`
- [ ] Do **not** add `business.manage` yet. It goes in a later round (§6).
- **Confirm:** the console groups each scope as non-sensitive, sensitive or
  restricted. All three new scopes are expected to show as **sensitive**, not
  restricted, which means no CASA security assessment. If any of them shows as
  *restricted*, stop and re-plan §4.
- **Depends on:** §2. Enable the APIs first; in our experience the picker
  only lists scopes for enabled APIs (confirm in console). You can also paste
  a scope URL in manually.

---

## 4. Incremental verification submission

- [ ] **Who:** Project Owner.
- **Where:** *Google Auth Platform* → **Verification Center**
  (<https://console.cloud.google.com/auth/verification>; confirm the path in
  the console) → *Prepare for
  verification* / *Submit*.
- [ ] Follow **`docs/GOOGLE_OAUTH_SCOPE_REVIEW.md`** for the justification text
      and the demo-video script. That doc is being written separately; this
      checklist only tracks the steps.
- [ ] Demo video (unlisted on YouTube). It must show the consent screen with the
      app name and **client ID** visible, and a working use of each new scope:
      the site list, the META verify, the sitemap submit, the merchant list,
      the homepage claim, and the feed data source plus fetch.
- [ ] Scope justifications: one per scope, explaining why a narrower scope
      won't do.
- **Confirm:** Verification Center shows *Verification in progress*, then
  *Verified*, for the added scopes. Sensitive-scope review can take **up to
  10 days**, and Google emails the project contacts with follow-up questions,
  so watch that inbox.
- **Depends on:** §1.1, §1.2, §3. Combine any branding edits from §1.2 into this
  same submission.
- Ref: <https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification>

---

## 5. Merchant API: one-time developer registration

Every Merchant API call from this Cloud project returns
**`401 UNAUTHENTICATED`**, *"GCP project with id {GCP_ID} and number
{GCP_NUMBER} is not registered with the merchant account."*, until the project
is registered **once**. The registration applies to the whole project, so it
covers every user and token the backend holds.

- [ ] **Who:** Merchant admin, working with the Project Owner.
- **Prerequisites:**
  - [ ] Adbot's **own primary** Merchant Center account. It must be a
        production account; **test accounts cannot register**.
  - [ ] That account has a **verified website**
        (<https://support.google.com/merchants/answer/11586344>).
  - [ ] The identity making the call has **ADMIN** access on that account. You
        cannot register a sub-account while authenticated through its parent
        or advanced account.
  - [ ] The Merchant API is enabled (§2).
- **How:**
  `POST https://merchantapi.googleapis.com/accounts/v1/accounts/{ACCOUNT_ID}/developerRegistration:registerGcp`
  with the `https://www.googleapis.com/auth/content` scope.
  - The method registers "the GCP used for the API call", which is the
    project behind the credentials making the request. Use an OAuth client
    that belongs to *this* production project. A default
    OAuth Playground client registers Google's project, not yours. The simplest
    route is a second, admin-only OAuth client in the same project, used with
    the Playground's "Use your own OAuth credentials" option (add
    `https://developers.google.com/oauthplayground` to that admin client only)
    or with a local script. Do not touch the production client.
  - While §4 is still pending, a project member may see the "unverified app"
    warning for `auth/content`. That is expected here.
  - `developerEmail` is optional. If you send it, it must be a real Google
    account, **not a service account**. An existing Merchant Center user gets
    the *API developer* role. A new user is invited and must **accept within
    14 days**, or registration is not complete.
- **Rules to know:**
  - A project registers with **one** Merchant Center account. A second attempt
    returns `ALREADY_REGISTERED`. Third-party apps register with their own
    primary account, **not** with each customer's account.
  - Once registered, the same project can manage any other Merchant Center
    account that the user's OAuth token can reach. No per-customer
    registration is needed.
- **Confirm:**
  - [ ] The call succeeds (a `DeveloperRegistration` resource comes back).
        Save the response. To re-check later, call
        `accounts.developerRegistration.getAccountForGcpRegistration`
        (returns the Merchant Center account this project is registered
        with) or `getDeveloperRegistration` on that account (same resource,
        <https://developers.google.com/merchant/api/reference/rest/accounts_v1/accounts.developerRegistration>). An
        `unregisterGcp` method also exists if the registration ever has to
        move to a different account.
  - [ ] *API developer* appears under *Merchant Center → Settings → People and
        access*.
  - [ ] After about 5 minutes, `GET /api/wp/gmc/accounts` succeeds for Adbot's
        own account.
  - [ ] **Multi-tenant test:** connect a WooCommerce site whose owner has a
        Merchant Center account that Adbot does **not** own (ask a friendly
        merchant, or use a separate Google account with its own production
        MC). Check that `gmc/accounts`, `claim`, `feed` and `feed/fetch` work
        without a 401. This is the only way to prove the third-party path.
- Refs: <https://developers.google.com/merchant/api/guides/quickstart/registration>,
  <https://developers.google.com/merchant/api/reference/rest/accounts_v1/accounts.developerRegistration/registerGcp>,
  <https://developers.google.com/merchant/api/guides/quickstart/faq>

---

## 6. Google Business Profile: apply for Basic API Access now

GBP APIs are access-gated. A new project has a quota of **0 QPM** on every GBP
API, which means not approved. An approved project gets **300 QPM**. Approval
is reviewed in roughly 14 days (approximate, from Google's FAQ/limits
pages), so apply now even though the feature ships next
release.

- [ ] **Who:** GBP manager. Apply from an email that is an **owner or manager**
      on the business profile, ideally an `@adbot.co.za` address that matches
      the website domain.
- **Eligibility:**
  - [ ] The applicant manages a Business Profile that has been **verified and
        active for 60+ days**.
  - [ ] That profile lists a **website** for the business (`adbot.co.za`).
- [ ] Get the **project NUMBER** (not the project ID) from Cloud console →
      *Dashboard* → *Project info*.
- [ ] Submit the GBP API contact form
      (<https://support.google.com/business/contact/api_default>) and choose
      **"Application for Basic API Access"**. Use the same project as the
      OAuth client.
- **Confirm:** an approval email arrives. *APIs & Services → Quotas* for the My
  Business APIs then shows 300 QPM instead of 0.
- **After approval only:**
  - [ ] Enable the seven GBP APIs (§2).
  - [ ] Add `https://www.googleapis.com/auth/business.manage` on Data Access
        and submit it as its **own** incremental verification round (§4
        process), with a GBP demo video, when the GBP release is ready.
  - [ ] Use the same contact form with **"Quota Increase Request"** only if
        real usage regularly reaches 300 QPM. Google's FAQ says increases
        may be denied when average usage is below 70% of the current limit.
- Refs: <https://developers.google.com/my-business/content/prereqs>,
  <https://developers.google.com/my-business/content/limits>,
  <https://developers.google.com/my-business/content/basic-setup>,
  <https://developers.google.com/my-business/content/faq>

---

## 7. Quotas and monitoring

**Who:** Project Owner. **Where:** *APIs & Services → Enabled APIs → (API) →
Quotas & System Limits*, and *Monitoring → Alerting*
(<https://console.cloud.google.com/monitoring/alerting>).

- [ ] **Tag Manager API.** This is the tightest limit: **10,000 requests per
      project per day** and **0.25 QPS (25 requests per 100 s)** per project,
      shared by **all** customer sites. If exceeded, calls return
      `403 Forbidden`. Raising the per-user limit does not lift the 0.25 QPS
      project cap. Request more through *Edit Quotas* / "apply for higher
      quota" before onboarding volume grows.
      Ref: <https://developers.google.com/tag-platform/tag-manager/api/v2/limits-quotas>
- [ ] **Analytics Admin API, Search Console API, Site Verification API,
      Merchant API.** Record the defaults shown in the console for this
      project, as each API reports its own quota units there.
- [ ] Create a Cloud Monitoring alert on quota exceeded, or on quota usage
      above 80%, for Tag Manager API and Merchant API, and send it to the
      backend on-call email. Pick the metric in the alert builder under
      *Consumed API* / quota metrics, and confirm in the console that Tag
      Manager quota is exposed there. If it is not, alert from backend logs on
      `403` quota responses instead.
- [ ] Optional: an alert on the 4xx/5xx error ratio per API (the *Consumed
      API* request-count metric grouped by response code class), to catch a
      burst of 401 "not registered" or 403 `insufficientPermissions`.
- **Confirm:** the alert policies are listed and a test notification reaches
  the inbox.
- **Depends on:** §2.

---

## Order at a glance

```
§1.1 domain ─► §1.2 authorized domains ─► §1.3 redirect URIs ─┐
§2 enable APIs ─► §3 add scopes ─────────────────────────────┴─► §4 submit verification ─► (approved) ─► G6 E2E ─► ship 1.0.14
§2 Merchant API ─► §5 registerGcp (Adbot's own production MC) ─► multi-tenant test ─┘
§6 GBP access form (now, independent) ─► (approved, ~14 days) ─► enable GBP APIs + business.manage round (next release)
§7 quotas/alerts (any time after §2)
§1.4 host switch (after 1.0.14 ships)
```
