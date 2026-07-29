# Aarvella Website — Project Status

## Document purpose

This document records the current implementation status, in-progress work, and pending next steps for the Aarvella public website, so work can resume from any session without re-deriving context. Claude must verify every item against the repository (and, where noted, against the live CRM API) before treating it as implemented — this file is a status tracker, not a substitute for checking the code.

For structural/architectural facts (how the site is built, file layout, request flows), see `docs/ARCHITECTURE.md` — that document is the source of truth for "how things work." This document is the source of truth for "what's done, what's in progress, and what's next."

Last manually reviewed: 2026-07-29.

---

## Brand assets: real logo + full favicon set added, 2026-07-27

The site previously had **no image logo anywhere** — nav and footer both used a CSS/text "AARVELLA" wordmark, and 3 blog articles' JSON-LD schema referenced `assets/images/aarvella-logo.png`, a file that had never existed (a silent broken link). This is now fixed:

* **`assets/images/aarvella-logo-white.svg`** — used in the shared nav (`assets/partials/navigation.html`, both desktop and mobile-drawer brand marks) and the shared footer (`assets/partials/footer.html`). Verified in a real browser: legible and correctly sized in the dark nav on both the black marketing pages and the light-background blog article pages (the nav floats as a dark bar over both), in the dark footer, and in the mobile drawer.
* **`assets/images/aarvella-logo-black.svg`** + a generated **`assets/images/aarvella-logo.png`** (1200px, rendered from the black SVG via ImageMagick) — used for the JSON-LD `logo` schema field on the 3 blog articles that already referenced this exact path, since that's a light-background context (search/social preview cards).
* The nav's previous "Hair · Skin · Makeup" tagline was dropped (user's explicit choice) since the new logo is a complete mark on its own.
* Full modern favicon set added at the repo root (`favicon.svg`, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png` [180×180], `android-chrome-192x192.png`, `android-chrome-512x512.png`, plus a new `site.webmanifest`), with the legacy `favicon.ico` kept as a fallback for very old browsers/crawlers. All sizes verified via `identify` before use — every file matched its expected dimensions exactly.
* **Every page now has a complete, consistent favicon link block** — previously only 10 of ~23 top-level pages referenced the favicon at all (none of the 4 blog articles did), and the customer-portal pages (`account/portal-layout.php`, `account-error.php`, `verify-email.php`, `verified.php`) had none either. All are now consistent.
* Verified in a real browser (Playwright + local `php -S`): favicon/logo files all resolve 200, nav/footer/mobile-drawer screenshots all confirmed visually correct.
* **Follow-up fix, same day**: the browser kept showing the old favicon after the first pass — browsers cache favicons independently of normal page cache and often ignore a hard refresh. Fixed by (1) merging 3 user-supplied ICO sizes (16/32/48px) into one proper multi-resolution `favicon.ico` via ImageMagick, replacing the single-size original, and (2) adding a `?v=20260727` cache-busting query string to every favicon-related link across all 27 pages. Verified all 6 favicon URLs resolve 200 with the new versioned hrefs, and confirmed the merged `.ico` actually contains the new gold-diamond artwork (not the stale one) by rendering and viewing it directly.
* **Committed and pushed** — `34e87c0` (logo + favicon set) and `2230845` (favicon cache-bust fix). Fully done.

---

## 🚧 Bookings are intentionally disabled — read this first

The salon is not open yet. `assets/js/booking.js` has `const BOOKING_TEMPORARILY_DISABLED = true;` near the top — while true, clicking any "Book Now" trigger anywhere on the site shows a "we're getting ready for you" notice with a WhatsApp contact link, instead of the real service/stylist/OTP/confirm flow. No CRM API calls happen at all in this state. The full flow (verified working end-to-end, see "Active work" below) is fully intact underneath and can be restored by flipping that one flag back to `false` — no other changes needed. **Do not flip it back without explicit confirmation that the salon is ready to take real bookings.**

## Active work — resume here

### Booking system: wiring the public site to the live CRM API

**Status: done, committed and pushed.** Committed as `2ea0254` ("Wire booking popup to live CRM API and polish UI") and pushed to `origin/main`. Confirmed working end-to-end in a real browser, not just via API testing — see below.

`assets/js/booking.js`, `assets/css/booking.css`, and `assets/partials/booking-popup.html` have been substantially rewritten (work done prior to 2026-07-20, in a session not captured in any saved memory — recovered by inspecting the uncommitted git diff) to replace the old WhatsApp-only popup with a full flow against a live external CRM API at `https://os.aarvella.com/api/v1` (the separate `aarvella-crm` Laravel repo). New flow: service search/category browse → live stylist + date + time-slot picker → phone number → OTP verification (6-digit code) → review → confirm → success, with every CRM-call failure degrading gracefully to the old WhatsApp hand-off rather than dead-ending the visitor.

**Verified against the real API on 2026-07-20:**

| Endpoint | Result |
|---|---|
| `GET /branches` | 200, 1 real branch (Aarvella Karanpur, Dehradun) |
| `GET /branches/1/services` | 200, 79 real services — shape matches `booking.js` exactly (`data[]` with `id`, `name`, `display_name`, `category.id/name`, `duration_minutes`, `price`) |
| `GET /branches/1/stylists?service_id=` | 200, shape matches. Stylist-to-service assignment is a known CRM-side placeholder (every stylist bulk-assigned to every service) — not a website bug, don't use today's per-service stylist lists as QA ground truth |
| `POST /auth/otp/request` | 200, `{"message":"OTP sent."}` — works. OTP currently goes to CRM server logs only (MSG91 DLT template still pending approval), not a real SMS |
| CORS preflight for `Origin: https://aarvella.com` | Confirmed allowed |
| `POST /availability` | 200, real slot data (this endpoint was 500ing until a fix deployed to production on 2026-07-20) |

**Resolved during verification:** `/availability` returns `starts_at`/`ends_at` labeled `+00:00` (UTC) but the underlying digits are actually Dehradun local time (confirmed by the user: should be `+05:30`) — a CRM-side serialization bug, **already relayed to the CRM session**. No fix needed in this repo: `booking.js`'s `normalizeSlots()` never parses the timestamp as a `Date`, it only regex-extracts the `HH:MM` digits and treats them as local time directly, and `handleConfirm()` independently reconstructs the submission timestamp using a hardcoded `+05:30`. The website code is correct regardless of whether/when the CRM fixes its label.

**`POST /auth/otp/verify` — confirmed working, 2026-07-20.** Tested end-to-end against test number `9999999999` (code `944719`, from CRM server logs). Real response: `{"token":"1|vpThl...","customer":{"id":1,"customer_code":"CUST-00001","full_name":"Customer 9999999999"}}`. `handleVerifyOtp()`'s first guess (`res?.token`) matches exactly — no code change needed. Incidental finding: the response also includes a `customer` object that `booking.js` currently ignores entirely — not a bug, just unused data available if useful later. This also means a valid Sanctum token (redacted — not stored in this repo, tied to CRM customer `CUST-00001`) existed at the time and was used to test `POST /appointments` immediately after.

**`POST /appointments` — confirmed working, 2026-07-20.** Tested end-to-end with explicit user go-ahead, using the real token from the OTP-verify test above. Request: `service_id=1` (Mens Cut and Styling), `stylist_id=1` (Rashmi Chandrakar), `starts_at="2026-07-22T10:50:00+05:30"` (a slot confirmed available moments earlier). Response: HTTP 201, `{"data":{"id":1,"status":"booked",...,"service":{"starts_at":"2026-07-22T10:50:00+00:00","ends_at":"2026-07-22T11:35:00+00:00","price":"600.00"}}}`. `handleConfirm()` doesn't inspect the response body — it only needs the call to not throw — so this confirms the full flow works with **no code changes needed in `booking.js`**.

**✅ Database confirmed shared, 2026-07-24.** Queried directly via cPanel phpMyAdmin/MariaDB: the CRM's data lives in `aarvyeqt_salon_db` — the same physical database `account/dashboard.php`/`appointments.php` read from directly via PDO (matches the `aarvyeqt` cPanel username seen in the private config path). The `appointments` table confirms:

| id | branch_id | customer_id | status | source | created_at |
|---|---|---|---|---|---|
| 1 | 1 | 1 | booked | website | 2026-07-20 16:55:43 |
| 2 | 1 | 2 | booked | website | 2026-07-20 18:23:18 |
| 3 | 1 | 3 | booked | website | 2026-07-20 19:07:29 |
| 4 | 1 | 4 | booked | website | 2026-07-20 19:29:33 |
| 5 | 1 | 5 | booked | website | 2026-07-24 05:28:53 |

id 1 is the `curl`-based API test from earlier. **ids 2-5 are confirmed to be the user's own successful full click-through tests in a real browser** — meaning the entire flow (service select → stylist/slot → OTP → confirm) works correctly end-to-end in practice, not just against raw API calls.

**⚠️ Cleanup needed on the CRM side**: all 5 of the above are real, permanent appointment records that should be cleaned up before real customer traffic. Not yet done as of this writing.

**Additional CRM-side finding**: the same `+00:00`-labeled-but-actually-local timezone issue seen in `/availability` also appears in `POST /appointments`'s response (`starts_at`/`ends_at` came back `+00:00` for the same local digits submitted as `+05:30`). Suggests this is a systemic serialization config issue across the CRM, not isolated to one endpoint — worth relaying the broader scope to the CRM session.

**All booking-API endpoints the website needs are now verified working against production, and confirmed via real browser use.** Remaining items:

1. ~~The changes need a full click-through test in an actual browser.~~ **Done** — confirmed via 4 successful real bookings (ids 2-5) created by the user testing the actual popup UI.
2. ~~Not yet committed to git.~~ **Done** — committed (`2ea0254`) and pushed to `origin/main`.
3. ~~Does `booking-popup.html` correctly handle every `data-book="..."` preselect string?~~ **Done** — see "Preselect wiring" below.
4. **Still open**: all 5 test appointments (ids 1-5, see the database table above) need cleanup on the CRM side.

**Preselect wiring — done, 2026-07-20.** Fetched the full live catalogue (79 services across 7 categories: Mens Hair, Womens Hair, Hair Coloring, Hair Texture, Hand and Foot Spa, Skin Care, Beauty and Wellness) and rewired every `data-book="..."` across the site to match real category/service names, replacing the old hardcoded/non-matching strings:

* 7 service pages (mens-hair, womens-hair, hair-coloring, hair-texture, hand-foot-spa, skin-care, beauty-essentials) → `data-book` set to their matching category name (14 buttons).
* `index.html`'s 3 service cards with a real catalogue match → `data-book="Balayage"` and `data-book="Hair Spa"` (exact service matches), `data-book="Skin Care"` for "Gold Infusion Facial" (no exact service name match, category fallback) (6 buttons). The 4th card ("Precision Haircut") and the 3 generic hero CTAs were left unset — no clean gender/service mapping exists for "Precision Haircut", and the hero buttons aren't tied to any specific service.
* 3 blog articles: the 2 hair-colour pieces → `data-book="Hair Coloring"` (5 buttons across both), the facial piece → `data-book="Skin Care"` (2 buttons).
* `services.html`'s stale `data-book="Services Consultation"` (matched nothing) removed — it's a general browse-everything page, no single category applies, so no preselect is correct there.
* **`makeup.html` (6 triggers) and `bridal.html` (5 triggers, pre-existing `data-book="Bridal Consultation"`) were left as-is** — the CRM catalogue currently has **no Makeup or Bridal category or services at all**. These preselects are harmless no-ops (fall through safely to the full service list) but won't actually preselect anything until the CRM adds that data. Worth flagging to the CRM session.

Every term was verified against the live API by replicating `applyPreselect()`'s exact matching logic (service-name substring match first, then category-name substring match) — all 9 distinct terms used resolve to exactly the intended category or service, no false-match collisions.

**✅ CORS localhost blocker resolved, 2026-07-20.** The CRM session added `http://localhost:8000` to `CORS_ALLOWED_ORIGINS` (alongside `https://aarvella.com`/`https://www.aarvella.com`). Independently re-verified with curl: both the preflight and an actual `GET /branches/1/services` now return `access-control-allow-origin: http://localhost:8000` and HTTP 200. **If the local dev server ever runs on a different port, that port needs to be added too — ask the CRM session, it's a one-line change on their end each time.**

**UI polish pass — done, 2026-07-21.** User ran the first real browser test and gave feedback on `assets/css/booking.css` and `assets/js/booking.js`:

1. Category order was incidental (followed API's raw services order, which happened to put "Beauty and Wellness" first) — fixed in `booking.js`'s `ensureCatalogue()` by sorting categories by `id` ascending, so order is now deterministic and matches the rest of the site (Beauty and Wellness, id 7, now lands last).
2. General spacing increased across the popup — search field, category row, service list/rows, stylist/date/slot pickers, form fields, OTP boxes, review card, and action buttons all got more breathing room (was feeling "cramped").
3. `.category-chip` restyled to reuse the exact same CSS custom properties as `.btn-gold`/`.btn-outline` (`--av-btn-gold-soft`, `--av-btn-gold-deep`, `--av-btn-ink` from `buttons.css`) instead of its own bespoke colors — selected chips now visually match the site's gold button, unselected chips match the transparent/glass secondary button.
4. Category chip font size increased (0.78rem → 0.9rem); it was already governed by one shared rule for both selected/unselected states, so no separate fix was needed there — just the size bump.
5. Service rows changed from `justify-content: space-between` (name and price/duration pushed to opposite edges) to `flex-start` with the name no longer flex-growing, so they now sit close together.
6. Background photo dimmed — opacity 0.82 → 0.55, darker gradient overlay, lower brightness filter — so it recedes instead of competing with the popup content.

**UI polish pass 2 — done, 2026-07-21.** Second round of feedback from continued browser testing:

1. Default OS scrollbars on `.popup-container`, `.category-row`, `.service-list`, `.stylist-row`, `.date-strip` were spilling outside the popup's rounded border — hidden via `scrollbar-width: none` + `::-webkit-scrollbar { display: none; }` (scrolling itself still fully works via wheel/touch/drag, only the visible track/thumb is gone). Popup size increased slightly (680px → 720px max width, 820px → 860px max height) to compensate for the lost visual padding.
2. Added slow, animated edge-hover auto-scroll (`attachEdgeAutoScroll()` in `booking.js`) to all 4 scrollable regions (3 horizontal strips + the vertical service list) — speed ramps up gently the closer the cursor gets to the edge, capped at a deliberately slow pace, so users can navigate without a visible scrollbar.
3. Time slots were showing duplicate entries when "Any Available" stylist was selected, because the CRM returns one slot per available stylist at that time. Added `dedupeSlotsByTime()` in `booking.js` — now shows each time once, preferring an available entry if only some of the duplicates were booked.
4. OTP boxes resized smaller and more portrait (38×46 → 30×44 at desktop, scaled proportionally at both mobile breakpoints).
5. **Corrected a regression from UI polish pass 1**: service rows had been changed from the original two-sided (`space-between`) name/price layout to a single-side `flex-start` layout to "bring them closer," which wasn't what was asked — reverted to `space-between`, and instead increased the row's horizontal padding (16px → 28px, 22px on the mobile breakpoint) to pull the two anchored elements closer together while keeping them on opposite sides, which was the actual request.

**Booking system is fully built and verified, but intentionally switched off, 2026-07-24.** Verified against real production data (79 services, real stylists, real availability), confirmed working through 4 real successful browser bookings, committed, and pushed. The salon is not open yet, so `BOOKING_TEMPORARILY_DISABLED = true` was added to `booking.js` — see the banner at the top of this document. Every "Book Now" trigger site-wide now shows a "we're getting ready for you" notice with a WhatsApp link instead of the real flow; no CRM calls happen while disabled. Restoring the real flow is a one-line flag flip when the salon is ready — nothing else needs to change.

Remaining items, none of which block turning bookings back on:

1. Clean up the 5 test appointments (ids 1-5) in `aarvyeqt_salon_db.appointments`.
2. CRM catalogue still has no Makeup or Bridal category/services — `makeup.html`/`bridal.html` booking buttons can't preselect anything meaningful until that data exists.
3. Confirm whether the user wants another look at the UI after the two polish passes (spacing, chip styling, scrollbars, OTP boxes, service-row layout) — no further feedback received yet as of this writing.

**Next session should**: do not re-enable `BOOKING_TEMPORARILY_DISABLED` without explicit confirmation the salon is ready for real bookings. Otherwise check whether the CRM-side cleanup and Makeup/Bridal catalogue gap have been addressed.

---

### Customer portal: login failure, booking-popup wiring, and appointment schema mismatch — 2026-07-27 to 2026-07-29

**Status: done, committed, deployed, and confirmed via live browser use, except item 4 (written, not yet committed).**

1. **Customer portal login was completely broken** — every attempt landed on `account-error.php?source=callback`, despite Auth0 itself reporting a successful login. Root cause: `customer_auth_identities` (the table linking an Auth0 identity to a `customers` row) did not exist on production (`aarvyeqt_salon_db`), so `sync-customer.php`/`require-auth.php` failed on every login. Diagnosed by adding explicit PHP error logging (`account/auth0-bootstrap.php`, commit `07fcbf7`) since the server's default `error_log` location couldn't be found. A follow-up `information_schema` sweep found 5 more DBv2 tables the portal code depends on that were also missing from production: `activity_logs`, `customer_portal_settings` (no tracked migration exists for this one at all — its schema was reconstructed from how the code reads/writes it, not sourced from a migration file), `loyalty_accounts`, `loyalty_programs`, `loyalty_tiers`. All 6 created directly against production by the user (tracked schema SQL, or the reconstructed statement for `customer_portal_settings`) — a missing-migration gap, not a code bug.
2. **Booking popup wasn't wired into the customer portal** — every "Book now"/"Book appointment" link just navigated to the homepage instead of opening the shared popup, because `account/portal-layout.php` never loaded it. Fixed (`58ad116`) by mounting the same shared component every public page uses (`#booking-popup-placeholder` + `assets/js/booking.js`) and adding the `js-book` trigger class to all 9 booking CTAs across `portal-layout.php`/`dashboard.php`/`appointments.php`. A follow-up fix (`b1ed15c`) added the missing `assets/css/booking.css` include — without it the popup mounted and opened correctly but had no styling, rendering as blank space at the end of the page (the browser auto-scrolled to it, which looked identical to the original "still just navigates" bug from the outside).
3. **Appointments not loading on the dashboard/appointments pages** — both pages assumed the DBv2 fresh-install schema (`appointment_code`, `appointment_start`/`end`, `total_price`, `advance_paid`, `payment_status`, `primary_stylist_id` on `appointments`; `service_name_snapshot` on `appointment_services`), none of which was ever actually deployed. The live `appointments`/`appointment_services` tables are a simpler, CRM-owned schema with per-service scheduling and no payment tracking at all (even `20260704_transaction_booking_api.sql`'s own `ALTER TABLE appointments ADD COLUMN lock_version` assumes columns that don't exist in production — this gap predates this session). Rewrote both pages' queries (`361c298`) against the real columns: appointment start/end derived via `MIN`/`MAX` of `appointment_services.scheduled_starts_at`/`scheduled_ends_at`, total price via `SUM(price_snapshot)`, service names via a `services` join. Dropped display fields with no live-schema equivalent (payment status, cancellation reason, customer note) instead of guessing at a mapping; replaced the fabricated "appointment code" badge with a cosmetic `Appointment #<id>`.
4. **`full_name` defaulting to the customer's email address** on first login — Auth0 defaults its `name` claim to the email itself when no display name is collected, and `register.php` never collects one (signup is 100% delegated to Auth0 Universal Login, no custom form). `sync-customer.php`'s existing empty-name fallback never caught this since `name` wasn't empty, just uselessly equal to the email. Fixed in `sync-customer.php` by treating an email-equal name the same as a missing one, falling through to the existing local-part-of-email fallback. **Written and lint-checked, not yet committed** — pending explicit go-ahead. A scoped one-off `UPDATE` was also provided (not run) to backfill existing rows where `full_name = email`.

Items 1-3 are deployed and were confirmed via live browser use on production by the user, along with the two "Current priorities" items below (booking-popup click-through, portal shell rendering after the query rewrite).

**Related finding, not a website bug**: a customer briefly appeared missing from the CRM's Customers tab after a portal login. Traced by reading the CRM's Filament resource directly (`app/Filament/Resources/Customers/Tables/CustomerTable.php` in the sibling `aarvella-crm` checkout at `d:\Projects\aarvella-crm`) — it has no filters and the `Customer` model has no global scopes, so a `customer_type = website_lead` row should never be hidden there. Most likely explanation: the CRM instance being checked was pointed at a different database (`aarvella_crm` on `localhost`, per that checkout's `.env`) than the production one the website writes to (`aarvyeqt_salon_db`). Resolved once checked against the right database — worth remembering if this recurs.

## Business and brand

* Business: Aarvella Unisex Salon
* Location: Dehradun, Uttarakhand
* Positioning: Mid-to-premium salon
* Primary domain: `aarvella.com`
* Brand direction: Premium black, gold and white visual system
* Design elements include frosted glass, subtle animation, premium typography and responsive layouts
* Brand tagline: "Become the version they can't ignore."

## Hosting and deployment

* Production is hosted on Namecheap shared hosting/cPanel.
* Cloudflare proxies the domain.
* The public website repository is stored in GitHub.
* Local development is performed through VS Code on Windows.
* GitHub-to-cPanel deployment has been configured, but no deployment automation (`.cpanel.yml`, webhook script, CI config) is version-controlled in this repository — see `docs/ARCHITECTURE.md` §12.
* A root `.htaccess` now exists (added 2026-07-07) with `Redirect 301` rules retiring `beauty.html`/`spa.html`/`skin.html` onto their current equivalents.
* Some historical fixes were made directly in cPanel and may need verification against GitHub — unverified, requires production access.

## Repository structure

Shared asset structure (`assets/css/`, `assets/js/`, `assets/images/`, `assets/partials/`) is established and documented in `docs/ARCHITECTURE.md` §3 and §5.

Shared systems status:

| System | Status |
|---|---|
| Navigation | Working, consistent across all top-level pages and blog articles. `hair.html` added to the mega-menu's Hair column (2026-07-07). |
| Footer | Working, consistent across all top-level pages. Blog articles still use a bespoke hand-rolled footer instead of the shared partial (not yet fixed). Duplicate footer-loader script (`footer-loader.js`) removed 2026-07-07 — `includes.js` is now the sole loader. |
| Buttons | Consistently shared everywhere, including the customer portal. |
| Booking popup | Fully built and verified against production, but **intentionally disabled** (`BOOKING_TEMPORARILY_DISABLED = true` in `booking.js`) since the salon isn't open yet — see the banner at the top of this document. Now also wired into the customer portal (2026-07-28, was previously public-pages-only). |
| Page animations | Mostly page-specific scroll-reveal (`IntersectionObserver`), duplicated per page rather than shared — not yet consolidated. |
| Customer authentication | Working, Auth0 Universal Login. See "Authentication" section below. |
| Customer dashboard | Working, real DB-backed data. `dashboard.php`/`appointments.php` render through the shared `account/portal-layout.php` shell (fixed 2026-07-07). Appointment queries rewritten 2026-07-28 to match the live CRM-owned schema (see "Active work"); browser-tested and confirmed 2026-07-29. |
| Newsletter subscription | Working, but see "Newsletter" section — duplicate submit-handler bug fixed 2026-07-07. |

## Public pages

| Page | Status |
|---|---|
| Home (`index.html`) | Live. Still has a large undocumented inline `<style>` block (~1,000 lines) not yet moved to `assets/css/` — outstanding debt. |
| Services (`services.html`) | Live. |
| Men's Hair, Women's Hair, Hair Colouring, Hair Texture, Hand and Foot Spa, Skin Care, Beauty Essentials | Live, all hardcode pricing directly in HTML (no shared source of truth) — outstanding debt. |
| Makeup, Bridal, Stylists, About, Blog, Find Us, Careers | Live. |
| `hair.html` | Confirmed as a genuine category hub (not a duplicate) — kept, now linked from primary navigation (2026-07-07). |
| `skin.html`, `beauty.html`, `spa.html` | Retired 2026-07-07 — confirmed as dead-end/orphaned duplicates of `skin-care.html`/`beauty-essentials.html`/`hand-foot-spa.html`, removed and redirected via `.htaccess` `Redirect 301`. |
| Individual blog articles | Live, 4 articles under `blog/`. Use a bespoke footer instead of the shared partial (not yet fixed). |
| Customer authentication and account pages | Live — see "Authentication" and "Customer portal" sections. |
| Customer portal/dashboard | Live, real data — see "Customer portal" section. |

## Navigation

Working. Verified in the 2026-07 audit: shared partial (`assets/partials/navigation.html`) + `navigation.js`, mounted on every top-level page and blog article. Mobile menu, hover menus, and profile dropdown are implemented and functional in code (not separately browser-tested).

Still unverified (no browser test performed): mobile menu height/footer overlap, profile dropdown readability/opacity, cross-browser typography consistency, z-index layering edge cases.

## Footer

Working on all top-level pages via the shared partial. Duplicate footer-loader bug (two competing scripts both injecting the footer, causing a double newsletter-submit) — **fixed 2026-07-07**, see Newsletter section.

Outstanding: blog articles (`blog/*.html`) still hand-roll their own footer instead of using the shared partial — not yet fixed.

## Booking system

See "Active work" section at the top of this document for current status. Summary of the architectural direction, now largely realized:

* ~~The final booking system should use a transactional backend API.~~ **Done** — wired to the live `os.aarvella.com` CRM API, every endpoint verified end-to-end including real appointment creation (2026-07-20).
* ~~It should ultimately consume services, branches, prices, durations, professionals, skills and availability from the CRM.~~ **Done** — confirmed working against real data.
* CRM operational data has been created and confirmed populated (79 real services, 1 branch, multiple real stylists) — no longer placeholder.
* ~~All pages containing booking triggers should pass the correct service.~~ **Done 2026-07-20** — every `data-book="..."` site-wide rewired to match real CRM category/service names; see "Active work" for the full list.
* Remaining gap: stylist-to-service assignment in the CRM is still a bulk-applied placeholder (every stylist on every service), not real skill data — CRM-side, not a website concern, but worth knowing before treating any specific stylist/service pairing as correct.
* Remaining gap: the CRM catalogue has no Makeup or Bridal category/services yet, so booking buttons on `makeup.html`/`bridal.html` can't preselect anything meaningful until that data exists.
* Only remaining website-side task: a real browser click-through test — everything so far has been verified via `curl`/API calls, not the actual UI.

## Authentication

Working, more complete than earlier documentation suggested. Verified in the 2026-07 audit: Auth0 Universal Login (no custom signup/login form — delegated entirely to Auth0's hosted page), full callback/session/CSRF handling in `account/*.php`, all portal writes use prepared statements and output escaping.

* `account/account-error.php` was previously an empty file (blank page on 3 different auth-failure redirect paths) — **fixed 2026-07-07**, now renders a proper source-aware error page.
* Mobile number / OTP-based *login* is not implemented (this is separate from the booking flow's OTP, which is a phone-verification step for the CRM booking API, not a site login mechanism).
* Not independently verified: whether cPanel's live deployment matches this repository's auth code.

Do not expose Auth0 secrets while working in this area.

## Newsletter

Working, with a bug fixed. Verified flow: footer form → `assets/js/newsletter.js` → `POST /api/newsletter/subscribe.php` → prepared-statement insert into `newsletter_subscribers` (idempotent via `ON DUPLICATE KEY UPDATE`).

**Fixed 2026-07-07**: a duplicate submit-handler bug — both `newsletter.js` and the now-removed `footer-loader.js` were independently attached to the same form, causing every real submission to fire two POST requests. `footer-loader.js` has been deleted; `newsletter.js` is now the sole handler.

No Brevo integration exists in code — the endpoint only writes to the local database table.

The original historical bug ("console submission worked, form submission didn't") could not be explained by any code-level cause found during the audit — most likely a Cloudflare/browser caching issue as originally suspected, not a logic bug. Now moot given the duplicate-handler fix regardless.

## Customer portal

Working, genuinely data-driven (not placeholder content) — confirmed via the 2026-07 audit. `dashboard.php` and `appointments.php` query real DBv2 tables (`appointments`, `appointment_services`, `branches`, `stylists`, `loyalty_accounts`) scoped to the authenticated customer.

**Fixed 2026-07-07**: `dashboard.php` and `appointments.php` previously hand-rolled their own duplicate sidebar/topbar markup instead of using the shared `account/portal-layout.php` shell that `profile.php`/`settings.php` already used. Both now render through `portalRenderShellStart()`/`portalRenderShellEnd()` (which was extended with an optional extra-CSS-class parameter to preserve page-specific styling). Side effect: both pages now correctly load `buttons.css`/`buttons.js`, and `customer-portal-pages.css`/`.js`, which they were previously missing.

**Browser-tested and confirmed working, 2026-07-29** — see "Active work" above for the login, booking-popup, and appointment-query fixes that were required to get here (login was completely broken and appointments failed to load until those fixes landed; the shell markup itself was fine). Confirmed: shell rendering, sidebar/mobile-nav, responsive layout on a narrow window, and the booking popup opening from every portal booking CTA.

Still unverified: cross-page font consistency, hover styling — no dedicated check performed on these specifically.

## Services pages

Dedicated pages exist for all planned service lines. Verified in the 2026-07 audit:

* **Pricing is hardcoded** directly in HTML on 9 pages (mens-hair, womens-hair, hair-coloring, hair-texture, hand-foot-spa, skin-care, beauty-essentials, plus the now-retired beauty/spa) — no shared source of truth. Not yet fixed.
* Design tokens (gold/black `:root` CSS variables) are independently redefined in at least 7 stylesheets and several inline `<style>` blocks — no shared token source. Not yet fixed.
* Booking-button preselect strings (`data-book="..."`) on `bridal.html` and `services.html` do not match the *old* booking popup's 8 canonical service labels — this needs re-checking against the *new* catalogue-driven popup (see "Active work" item 5 above), since the matching logic has changed.
* All links/image paths were spot-checked during the audit and found working; not exhaustively verified page by page.

## Blog

Live, 4 long-form articles under `blog/`. FAQ accordions and scroll-reveal implemented per-article (not shared). Blog articles use their own bespoke footer instead of the shared partial — not yet fixed. Not independently browser-tested for FAQ animation smoothness or cross-article consistency.

## Database and APIs

Two separate, structurally different schemas were found in active use — documented in full in `docs/ARCHITECTURE.md` §10:

* **"DBv2"**, used by `account/*.php` — real, in production use for the customer portal. **Confirmed 2026-07-24**: this is the `aarvyeqt_salon_db` MariaDB database on the cPanel account, and it is the same database the CRM (`os.aarvella.com`) reads from and writes to — verified by querying `appointments` directly via phpMyAdmin and finding the exact test bookings created through the CRM API and the live booking popup.
* **Legacy**, used by `api/*.php` (newsletter, contact, and an unused set of `services`/`stylists`/`appointments` endpoints) — these three unused endpoints (`api/appointments/create.php`, `api/services/list.php`, `api/stylists/list.php`) are now superseded in practice by the direct `os.aarvella.com` CRM integration described in "Active work" above. Their fate (delete vs. keep as a fallback) has not been decided. Whether *this* legacy connection path (`api/config/db.php`) also points at `aarvyeqt_salon_db` or a different database is still unverified.

The CRM (`os.aarvella.com`) is now confirmed live and is the operational source of truth for services, stylists, branches and availability, per the direction this section originally called for.

**Production DBv2 schema drift discovered, 2026-07-28** — the tracked schema files under `backup_pages/User Portal/db/aarvella_db_v2_fresh_install/` do not fully match what's actually deployed to `aarvyeqt_salon_db`:
* 6 tables the portal code depends on (`customer_auth_identities`, `activity_logs`, `customer_portal_settings`, `loyalty_accounts`, `loyalty_programs`, `loyalty_tiers`) were missing entirely — see "Active work" above. All since created; `customer_portal_settings` has no tracked migration at all (its schema was reconstructed from code usage, not sourced from a file).
* `appointments`/`appointment_services` (real, populated, working) turned out to be a different, simpler, CRM-owned schema than the one the tracked DBv2 SQL describes — `20260704_transaction_booking_api.sql` itself assumes columns (`appointment_start`, `primary_stylist_id`) that don't exist in production, meaning that migration was written against a design that was never actually the one deployed. Treat the tracked schema files as aspirational/unreliable for these two tables specifically; the live `DESCRIBE` output is authoritative. Other untested tables in that schema folder may have the same problem — not yet audited.

## Current priorities

1. ~~Browser-test the booking popup end-to-end~~ **Done, 2026-07-29** — the real transactional flow was already verified 2026-07-20 (4 successful bookings); confirmed today that the current intentionally-disabled state (added 2026-07-24) also opens and renders correctly in the browser.
2. ~~Browser-test the customer-portal shell refactor (`dashboard.php`/`appointments.php`)~~ **Done, 2026-07-29** — confirmed correct in a real browser, including after the appointment-query rewrite (see the new "Customer portal" section under "Active work" above).
3. Decide the fate of the unused legacy booking endpoints (`api/appointments/create.php` etc.) now that the CRM integration supersedes them.
4. Centralize hardcoded config (WhatsApp number, salon address, business hours, geo-coordinates) into shared partials — still duplicated across many files.
5. Consolidate CSS design tokens into one shared source.
6. Move `index.html`'s remaining ~1,000-line inline `<style>` block into `assets/css/`.
7. Give blog articles the shared footer partial instead of their bespoke one.
8. Add deployment automation (`.cpanel.yml` or equivalent) to version control.
9. Commit the pending work once verified (nothing from the 2026-07-07 cleanup pass or the booking-CRM work has been committed yet).

## Known risks

* **Work done in one session is not automatically preserved for the next** — nothing is written to persistent memory unless explicitly saved, and uncommitted git changes are the only record of in-progress work between sessions. This document exists specifically to mitigate that; keep it current.
* Direct cPanel edits may not exist in Git — unverified, requires production access.
* Cloudflare and browser caching may obscure deployments; cache-busting query strings are applied inconsistently across pages.
* Credentials live outside the repository (`/home/aarvyeqt/private/aarvella-auth0.php`, `api/config/db.php`) and must not be exposed.
* The CRM's OTP flow currently has no real SMS delivery (MSG91 DLT template pending approval) — fine for programmatic testing, not for real-device QA yet.
* The CRM's stylist-to-service assignment data is a known placeholder (bulk-applied), not real skill data — don't use it for QA sign-off.
* `POST /appointments` against the live CRM creates real, permanent records — a test appointment (`id: 1`, 2026-07-22, `CUST-00001`) was created with explicit user confirmation on 2026-07-20 and still needs CRM-side cleanup. Any further testing of this endpoint should stay mindful of the same risk.
* Public website and CRM responsibilities may still overlap in places not yet fully audited (e.g., the unused legacy booking endpoints).
* ~~The CRM's CORS allow-list only includes the production domain...~~ **Resolved 2026-07-20** — `http://localhost:8000` added and verified. If the local dev server port ever changes, that new origin needs to be added on the CRM side too.
* **`git push` does not deploy** — GitHub-to-cPanel deployment is a separate manual step (`git pull` on the server; see "Hosting and deployment"). This caused real confusion twice in the 2026-07-27/28 session (a fix was reported "still broken" after being pushed but before being deployed) — always confirm the server's `git log -1` matches `origin/main` before debugging a "fix didn't work" report as a code issue.
* **The tracked DBv2 schema files may not match production** — see "Database and APIs" above (6 missing tables found 2026-07-28, plus `appointments`/`appointment_services` turning out to be a different schema entirely). Don't trust the schema SQL under `backup_pages/` as ground truth for any table without checking `DESCRIBE`/`information_schema` against production first.

## Reference: 2026-07 audit deliverables

A full read-only architecture audit was completed and is preserved in `docs/ARCHITECTURE.md`, covering: repository tree, technology/dependency inventory, route inventory, shared-component inventory, API endpoint inventory, authentication/booking/newsletter flow maps, database-access inventory, duplicate/obsolete file findings, security concerns, deployment concerns, and a prioritized remediation roadmap. Refer there for full file:line-level detail rather than duplicating it here.
