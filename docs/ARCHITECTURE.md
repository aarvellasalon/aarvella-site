# Aarvella Website — Architecture

## Document purpose

This document describes the *as-built* architecture of the Aarvella public website and customer portal, based on a full repository audit (July 2026). It records what the code actually does, not what is planned. Where the implementation is incomplete, inconsistent, or diverges from `docs/PROJECT_STATUS.md`, that is called out explicitly under "Known architecture debt" rather than smoothed over.

This document should be re-verified against the repository before being relied on for major architectural decisions — treat the code as authoritative if the two ever disagree, and update this file rather than trusting a stale copy.

---

## 1. Runtime architecture

The site is a static/PHP hybrid with no build step in production:

- **Public marketing pages** (`*.html` at the repo root, `blog/*.html`) are plain static HTML, served as-is. There is no templating engine and no server-side rendering for these pages.
- **Shared UI regions** (navigation, footer, booking popup, AI chat) are not templated at build time — they are separate HTML partial files fetched client-side with `fetch()` at page load and injected into placeholder elements. See §5.
- **Customer portal pages** (`account/*.php`) are server-rendered PHP, executed per-request, gated behind Auth0 authentication, and query MySQL/MariaDB directly via PDO.
- **Backend APIs** (`api/*.php`) are independent, stateless PHP scripts, one file per endpoint, each including its own CORS/DB/helper setup rather than sharing a framework or router.
- **AI stylist chat** is the one dynamic backend not hosted on the PHP server: a Node.js serverless function deployed separately to Vercel (`aarvella-ai-backend/`), called from the browser over HTTPS.
- There is no JavaScript framework (no React/Vue/Angular) and no bundler wired into production, despite a Vite devDependency in `package.json` — no `vite.config.*` exists and no page loads a Vite-built bundle.

## 2. Production hosting architecture

- **Primary domain**: `aarvella.com`
- **Web/app hosting**: Namecheap shared hosting (cPanel), serving PHP directly from the document root.
- **CDN / proxy**: Cloudflare in front of the domain.
- **AI backend hosting**: Vercel, hosting `aarvella-ai-backend/api/ai-stylist.js` as a serverless function at `https://aarvella-site.vercel.app/api/ai-stylist` — a separate deployment target and separate codebase root from the cPanel site.
- **Source control**: GitHub, `aarvellasalon/aarvella-site` (this repository, `main` branch).
- **Secrets storage**: kept outside the web root and outside git, in a single file at the fixed path `/home/aarvyeqt/private/aarvella-auth0.php` on the cPanel account, containing both the Auth0 SDK config and the MySQL connection parameters used by `account/*.php`. A second, independent credential path (`api/config/db.php`) is expected by the `api/*.php` endpoints — see §10 and §13.
- **Local development**: VS Code on Windows; `vendor/` (Composer) and `node_modules/` are gitignored and not present in a fresh checkout until installed.

## 3. Directory structure

```
/                       Public marketing pages (*.html)
├── account/            Auth0-gated customer portal (PHP)
├── api/                Stateless PHP API endpoints, one per concern
│   ├── config/         Shared CORS/DB/helper includes (no page output)
│   ├── appointments/  services/  stylists/  customer/  newsletter/  contact/
├── assets/
│   ├── css/             Shared + page-family stylesheets
│   ├── js/               Shared + page-family scripts
│   ├── partials/       HTML fragments fetched client-side (nav, footer, booking popup, AI chat)
│   ├── images/  videos/  uploads/   Static media
├── blog/                Long-form article pages (own footer, see §5)
├── aarvella-ai-backend/ Separate Vercel project (Node.js, OpenAI wrapper)
├── backup_pages/        Untracked local archive of superseded page versions (gitignored)
├── docs/                 Project documentation (this file, PROJECT_STATUS.md)
├── composer.json / composer.lock   PHP dependencies (Auth0 SDK, PSR-7, HTTP client)
├── package.json                     Declares an unused Vite devDependency
└── CLAUDE.md                        AI-assistant working agreement for this repo
```

## 4. Page routing

There is no router — every page is a literal file served at its own path. Routing is entirely a function of the filesystem plus manually-maintained `<a href>` links in the navigation partial, footer partial, and individual pages.

- Top-level pages: `index.html`, `services.html`, one page per service line (`mens-hair.html`, `womens-hair.html`, `hair-coloring.html`, `hair-texture.html`, `hand-foot-spa.html`, `skin-care.html`, `beauty-essentials.html`, `makeup.html`, `bridal.html`), plus `stylists.html`, `about.html`, `blog.html`, `find-us.html`, `careers.html`, `privacy.html`, `terms.html`.
- `hair.html` is a genuine category hub above the four hair-specific pages (consultation guidance, a card grid linking to `mens-hair.html`/`womens-hair.html`/`hair-coloring.html`/`hair-texture.html`, its own FAQ) — kept, and now also linked from the primary navigation mega-menu's Hair column (previously footer/blog-only).
- Blog: `blog.html` (index) links to individual articles under `blog/`.
- Customer portal: `account/login.php`, `register.php`, `callback.php`, `logout.php`, `dashboard.php`, `appointments.php`, `profile.php`, `settings.php`, `verify-email.php`, `verified.php`, `account-error.php`.
- `skin.html`, `beauty.html`, `spa.html` were orphaned/dead-end duplicates of `skin-care.html`/`beauty-essentials.html`/`hand-foot-spa.html` (no canonical tags, no outbound links, built on the older `services.css` template). Retired via a root `.htaccess` (`Redirect 301 /skin.html /skin-care.html`, etc.) rather than a silent deletion, per the URL-preservation rule in `CLAUDE.md`; the physical files were removed and the footer/blog links that pointed at `skin.html` now point directly at `skin-care.html`.

## 5. Shared component loading

Shared UI is implemented as HTML partials fetched at runtime, not as a templating/include system:

| Component | Partial | Loader | Mount point |
|---|---|---|---|
| Navigation | `assets/partials/navigation.html` | `assets/js/navigation.js` | `[data-navigation-partial]` |
| Footer | `assets/partials/footer.html` | `assets/js/includes.js` | `#footer-placeholder` |
| Booking popup | `assets/partials/booking-popup.html` | `assets/js/booking.js` | `#booking-popup-placeholder` |
| AI chat widget | `assets/partials/ai-chat.html` | `assets/js/ai-chat-loader.js` (lazy-loads `assets/js/ai-chat.js` on demand) | `#ai-chat-root` |

Coverage is not uniform:
- **Navigation** is mounted on every top-level page and every blog article.
- **Footer** is mounted on every top-level page but **not** on the 4 blog articles, which instead hand-roll their own `<footer class="article-footer">` markup with a reduced link set (no social icons, no newsletter form, no legal links).
- **Customer portal shell**: `account/portal-layout.php` provides shared `portalRenderShellStart()`/`portalRenderShellEnd()` sidebar/topbar markup, extended with an optional per-page `$extraMainClasses` parameter. All four authenticated pages (`dashboard.php`, `appointments.php`, `profile.php`, `settings.php`) now render through it.

Buttons (`assets/js/buttons.js` + `assets/css/buttons.css`) are the one component consistently shared everywhere, including inside the customer portal.

## 6. Authentication flow

Authentication is entirely delegated to **Auth0 Universal Login** — there is no custom-built signup/login form in this repository.

1. `account/login.php` → calls `$auth0->login()`, redirecting to Auth0's hosted login page.
2. `account/register.php` → calls `$auth0->signup()` with a hardcoded production callback URL (`https://aarvella.com/account/callback.php`).
3. `account/callback.php` → exchanges the authorization code, explicitly detects an `access_denied` + "unverified email" response and redirects to `verify-email.php`; otherwise calls `syncAuth0Customer()` and redirects to `dashboard.php`. Failures are logged server-side and redirect to `account-error.php`.
4. `account/sync-customer.php` → upserts the authenticated identity into `customers` + `customer_auth_identities` inside a DB transaction with row locking (`FOR UPDATE`), and rejects attempts to link one Auth0 identity to an email already owned by a different identity.
5. `account/require-auth.php` → `requireAuthenticatedCustomer($auth0)` is the gate every protected portal page calls before rendering; it resolves the Auth0 session into a DBv2 `customers` row, creating one via `syncAuth0Customer()` on first login if none exists yet.
6. `account/logout.php` → calls `$auth0->logout()`.
7. Session cookies are configured `httponly`, `samesite=Lax`, and conditionally `secure` (`account/portal-common.php:portalStartSession()`); CSRF tokens are issued per-session and verified on every portal POST action (`portalCsrfToken()` / `portalVerifyCsrf()`).
8. Auth0 SDK config (domain, client ID/secret, cookie secret, redirect URI) and the MySQL credentials used by `account/*.php` are both loaded from one file outside the repository, `/home/aarvyeqt/private/aarvella-auth0.php` (`account/auth0-bootstrap.php`, `api/config/database.php`).

**Not implemented**: mobile number / OTP-based login. Authentication is email/password (via Auth0) only.

## 7. Newsletter flow

1. Entry point: the newsletter form inside the shared footer partial (`assets/partials/footer.html`, `#newsletterForm`).
2. Client-side handling: `assets/js/newsletter.js` attaches a single `submit` listener to `#newsletterForm` and POSTs to the endpoint below. (A duplicate handler previously lived in `assets/js/footer-loader.js`, which caused every signup to fire twice — that file has been removed; `assets/js/includes.js` is now the sole footer loader.)
3. Request: `POST /api/newsletter/subscribe.php` with `{ email, source }` as JSON.
4. `api/newsletter/subscribe.php` validates the email format, allow-lists the `source` value, and performs an `INSERT ... ON DUPLICATE KEY UPDATE` into `newsletter_subscribers` via a prepared statement (idempotent re-subscription).
5. Response is a JSON `{ success, message }`, rendered into `#newsletterMessage`.
6. **No Brevo integration exists in code** — the endpoint only writes to the local database table. `brevo_newsletter.html` at the repo root is a standalone third-party embed test page, not linked from the site's navigation.

## 8. Booking flow

**Live/public flow** (what a visitor actually experiences):

1. Any `.js-book` / `[data-booking-trigger]` element opens the shared popup (`assets/partials/booking-popup.html`) via `assets/js/booking.js`.
2. The popup walks the visitor through service selection → optional date/time → name/phone (validated client-side as an Indian mobile number) → confirmation.
3. On confirm, `booking.js` builds a pre-filled WhatsApp message and opens a `wa.me/919142351661` deep link. **No network request is made to any backend** — nothing is written to a database at this point. This is the entirety of the current production booking flow.
4. Pricing shown on each service page is hardcoded directly in HTML, not sourced from a database or API.

**Built but unused backend** (exists in the codebase, not wired to the UI):

- `api/appointments/create.php` — a complete, transactional booking API: validates the service and stylist, checks for a double-booking overlap, upserts the customer by phone number, records marketing consent, inserts the appointment, and logs status history.
- `api/services/list.php`, `api/stylists/list.php` — read endpoints for the same schema.
- These three endpoints are never called by any front-end code, and they query a different, older database schema than the one the customer portal uses (see §10). Connecting the public booking flow to a backend requires resolving that schema mismatch first, not simply adding a `fetch()` call.

## 9. Customer portal flow

The portal (`account/*.php`) is genuinely data-driven, not placeholder content:

- `account/dashboard.php` — queries `appointments`/`appointment_services`/`branches`/`stylists`/`loyalty_accounts` for the authenticated customer's upcoming and recent appointments, and computes a real profile-completion percentage from actual customer fields.
- `account/appointments.php` — full read-only appointment history/detail view, scoped to the authenticated customer's ID (never a client-supplied ID).
- `account/profile.php` — edits identity fields, avatar (validated upload with MIME/dimension checks and an `.htaccess` hardening file written into the upload directory), booking preferences (preferred branch/stylist/day/time window), and a "beauty profile" (hair/skin type, goals).
- `account/settings.php` — communication/marketing consent, booking defaults, personalisation, appearance (compact mode / reduced motion / high contrast), and privacy-request creation/cancellation (correctly scoped: cancellation is constrained to `id + customer_id + status='pending'` and checks the affected row count before treating it as a success).
- All portal writes go through `account/portal-common.php` helpers: CSRF verification before any DB write, prepared statements throughout, and output escaping via `e()`/`portalE()` on every dynamic value.
- Feature areas not yet built are honestly marked `data-coming-soon` in the UI (online reschedule/cancel, loyalty rewards, referrals, saved addresses, payment methods) rather than faked.
- `account/account-error.php`, the redirect target used by three different failure paths (`callback.php`, `require-auth.php`, `register.php`), now renders a proper error page (source-aware heading/copy, sign-in retry, WhatsApp fallback) instead of the blank page it previously showed.

## 10. Database access

Two separate, structurally different schemas are in active use, reached through two separate connection paths:

| | "DBv2" (customer portal) | Legacy (public API) |
|---|---|---|
| Used by | `account/*.php` | `api/*.php` |
| Connection | `getDatabase()` in `api/config/database.php` | `$pdo` from `api/config/db.php` (gitignored, not present in this checkout) |
| Credentials from | `/home/aarvyeqt/private/aarvella-auth0.php` | unknown/unverified — separate file |
| Key tables | `customers` (`customer_code`, `account_status`), `customer_auth_identities`, `appointment_services` (many services per appointment), `branches`, `staff_branch_assignments`, `stylists` (`public_name`, `specialty`), `customer_preferences`, `customer_portal_settings`, `customer_consents` (`consent_type`/`consent_status` rows), `loyalty_accounts`, `activity_logs` | `customers` (unique by `phone`), `services` (`price`, `sale_price`, `duration_minutes`), `stylists` (`slug`, `bio`, `experience_years`), `stylist_services`, `booking_sources`, `appointment_status_history`, `contact_enquiries`, `newsletter_subscribers`, `customer_consents` (boolean columns — a **different shape** from the DBv2 table of the same name) |

`api/config/db.example.php`, the checked-in template for the legacy connection file, now constructs and returns a `$pdo` PDO instance matching how every `api/*.php` endpoint actually consumes it (it previously only defined loose `$DB_HOST`/`$DB_USER`/... variables with no PDO construction).

Whether the two connection paths ultimately point at the same physical database cannot be verified from this repository, since both credential files live outside git. This should be confirmed before any work connects the legacy booking API to the live UI.

## 11. External integrations

- **Auth0** — customer authentication (Universal Login), via `auth0/auth0-php` (Composer). Config outside the repo (§6).
- **OpenAI** (via Vercel) — `aarvella-ai-backend/api/ai-stylist.js` calls the OpenAI Responses API using an `OPENAI_API_KEY` environment variable that never reaches the browser; CORS is locked to `https://aarvella.com`. The public site's `assets/js/ai-chat.js` talks to this function at a hardcoded Vercel URL.
- **WhatsApp** (`wa.me` deep links) — the de facto booking-confirmation and contact-salon channel, used in place of a transactional backend (§8). The business WhatsApp number is hardcoded independently in at least 9 separate files rather than sourced from one shared value.
- **Cloudflare** — CDN/proxy in front of `aarvella.com`; also a known contributor to stale-asset symptoms during deploys (per `docs/PROJECT_STATUS.md`).
- **Brevo** — referenced in project planning as the intended newsletter/CRM communication provider, but **no integration exists in code**; `newsletter_subscribers` is a local-only table today.
- **Google Fonts, Font Awesome, Google Maps embed** — loaded from public CDNs on most pages.

## 12. Deployment process

- Intended direction (per `docs/PROJECT_STATUS.md`): local repository → GitHub → cPanel.
- **No deployment automation is version-controlled in this repository** — there is no `.cpanel.yml`, no `.github/workflows`, and no deploy script checked into git. `.gitignore` reserves entries for `deploy-webhook.php` and `deploy_log.txt`, implying a webhook-based deploy exists on the live cPanel account, but its contents are not visible here and cannot be reviewed or reproduced from this checkout.
- A root `.htaccess` now exists (previously none did) with `Redirect 301` rules consolidating the retired `beauty.html`/`spa.html`/`skin.html` onto their current equivalents — see item 13 in §13.
- Cache-busting query strings (`?v=YYYYMMDD-N`) are applied to most `<link>`/`<script>` tags but not uniformly (e.g. `careers.html`, `terms.html` omit them on some assets) — inconsistent cache-busting is a plausible contributor to the stale-asset-after-deploy symptoms already noted in `docs/PROJECT_STATUS.md`.
- The AI backend (`aarvella-ai-backend/`) deploys independently to Vercel and is not part of the cPanel deployment at all.
- PHP dependencies (Auth0 SDK, PSR-7, Symfony HTTP client) are managed via Composer; `vendor/` is gitignored, so a fresh checkout requires `composer install` against credentials this repository does not contain.

## 13. Known architecture debt

Ordered roughly by impact; see the July 2026 audit for full file:line citations. Items marked **Resolved** were fixed in a follow-up cleanup pass; everything else is still outstanding and mostly requires a product/business decision before it can be safely implemented (not just a mechanical code fix).

1. **Booking is WhatsApp-only in production**; a complete transactional booking API (`api/appointments/create.php` + supporting endpoints) exists but is never called, and targets a different schema than the customer portal uses. *Outstanding — needs a decision on whether to migrate these endpoints onto the DBv2 schema or retire them (see item 2).*
2. **Two incompatible database schemas** are live simultaneously (§10) — highest-risk item if booking work resumes without reconciling them first. *Outstanding — requires production access to confirm whether the two credential files even point at the same database.*
3. ~~Duplicate footer loaders (`includes.js` + `footer-loader.js`); duplicate newsletter submit handlers (`footer-loader.js` + `newsletter.js`).~~ **Resolved** — `footer-loader.js` removed; `includes.js` is the sole footer loader, `newsletter.js` the sole submit handler.
4. ~~Duplicate booking/nav wiring hand-copied inline into `index.html`, `privacy.html`, `terms.html`, `bridal.html`, and `skin.html`.~~ **Resolved** — dead/duplicate menu, booking-wizard and ripple code removed from all five pages; `bridal.html` now also loads `buttons.js` (previously missing entirely).
5. ~~`account/account-error.php` is empty.~~ **Resolved** — now renders a proper source-aware error page.
6. ~~`account/dashboard.php` and `account/appointments.php` bypass the shared `portal-layout.php` shell.~~ **Resolved** — both now render through `portalRenderShellStart()`/`portalRenderShellEnd()`, which was extended with an optional extra-CSS-class parameter to preserve `appointments.php`'s page-specific styling hook. As a side effect, `appointments.php` now correctly loads `buttons.css`/`buttons.js`/`customer-portal-pages.css` (previously missing), and `dashboard.php` now loads `customer-portal-pages.js` (previously missing despite loading its CSS).
7. **Hardcoded pricing** across 9 service pages, with no shared source of truth; booking-popup service preselection is attempted on 3 pages and does not work on any of them (string mismatches against the popup's 8 canonical service labels). *Outstanding.*
8. **Hardcoded, duplicated config values** — WhatsApp number (9 locations), salon address (inconsistent between footer/nav/`find-us.html`), business hours (correct values exist server-side in `portal-common.php` but the public `find-us.html` still says "to be finalized"), and geo-coordinates (copied into 4 files). *Outstanding.*
9. **No shared design-token source** — the gold/black `:root` CSS variables are independently redefined in at least 7 stylesheets and 5+ inline `<style>` blocks. *Outstanding.*
10. **Undocumented inline `<style>`/`<script>` blocks** on `index.html` (~1,000 lines of CSS; its inline `<script>` has been trimmed to only page-specific logic, see item 4) and several other top-level pages, contrary to the shared-asset convention used elsewhere in the codebase. *Outstanding for the CSS; the JS portion was cleaned up as part of item 4.*
11. **No deployment automation in version control**, and two separate database credential files living entirely outside the repository, making both impossible to verify from a checkout alone. *Outstanding.*
12. ~~Minor: `api/customer/me.php` empty stub; `assets/js/blog.js` unused; `api/config/db.example.php` stale template; `time-check.php` leftover debug script.~~ **Partially resolved** — `assets/js/blog.js` and `time-check.php` removed; `db.example.php` now matches real `$pdo` usage. `api/customer/me.php` remains an empty, unimplemented stub (no callers found, so left in place pending a decision on whether it's still needed).
13. ~~Legacy/orphaned pages (`hair.html`, `skin.html`, `beauty.html`, `spa.html`) still exist alongside their current equivalents with inconsistent footer/nav linking.~~ **Resolved** — `hair.html` confirmed as a genuine category hub (not a duplicate) and added to the primary navigation mega-menu. `skin.html`, `beauty.html`, `spa.html` confirmed as dead-end/orphaned duplicates of `skin-care.html`/`beauty-essentials.html`/`hand-foot-spa.html` (no canonical tags, no outbound links) and retired via `.htaccess` 301 redirects rather than silent deletion; the footer and blog links that pointed at `skin.html` now point directly at `skin-care.html`.
