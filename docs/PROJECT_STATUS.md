# Aarvella Website — Project Status

## Document purpose

This document records the current implementation status, significant architectural decisions, known issues and pending work for the Aarvella public website.

Claude must verify every item against the repository before treating it as implemented.

Last manually reviewed: July 2026.

## Business and brand

* Business: Aarvella Unisex Salon
* Location: Dehradun, Uttarakhand
* Positioning: Mid-to-premium salon
* Primary domain: `aarvella.com`
* Brand direction: Premium black, gold and white visual system
* Design elements include frosted glass, subtle animation, premium typography and responsive layouts
* Brand tagline: “Become the version they can’t ignore.”

## Hosting and deployment

* Production is hosted on Namecheap shared hosting/cPanel.
* Cloudflare proxies the domain.
* The public website repository is stored in GitHub.
* Local development is performed through VS Code on Windows.
* GitHub-to-cPanel deployment has been configured.
* Some historical fixes were made directly in cPanel and may need verification against GitHub.
* The intended deployment direction is local repository to GitHub to cPanel.

## Repository structure

The intended shared asset structure includes:

* `assets/css/`
* `assets/js/`
* `assets/images/`
* `assets/partials/`

Shared systems have been developed or partially developed for:

* Navigation
* Footer
* Buttons
* Booking popup
* Page animations
* Customer authentication
* Customer dashboard
* Newsletter subscription

Claude must inspect the actual repository and identify which pages still contain duplicate or inline versions.

## Public pages

The website contains or is intended to contain:

* Home
* Services
* Men’s Hair
* Women’s Hair
* Hair Colouring
* Hair Texture
* Hand and Foot Spa
* Skin Care
* Beauty Essentials
* Makeup
* Bridal
* Stylists
* About
* Blog
* Individual blog articles
* Find Us
* Careers
* Customer authentication and account pages
* Customer portal/dashboard

Claude must produce an inventory of actual routes and files and compare them with this intended list.

## Navigation

Work completed or attempted:

* Reusable desktop and mobile navigation
* Transparent navigation over hero areas
* Frosted navigation styling
* Responsive sizing for desktop, laptop, tablet and mobile
* Desktop hover menus
* Mobile hamburger menu
* Customer profile dropdown

Areas requiring verification:

* Mobile menu height and footer overlap
* Profile dropdown readability
* Consistent typography
* Menu layering and z-index
* Behaviour on article pages
* Whether every page uses the shared navigation implementation

## Footer

Work completed or attempted:

* Shared footer styling
* Social media links
* Footer partial loading
* Newsletter form
* Responsive layout

Areas requiring verification:

* Whether all pages use the same footer partial
* Differences between local and live rendering
* Newsletter JavaScript binding
* Input field styling
* Social icons and X icon visibility
* Deployment/cache issues

## Booking system

Work completed or attempted:

* Central booking popup
* Shared booking CSS and JavaScript
* Buttons using a common booking trigger
* Customer-facing booking entry points

Current architectural direction:

* The final booking system should use a transactional backend API.
* It should be accessible from the public website and customer portal.
* It should ultimately consume services, branches, prices, durations, professionals, skills and availability from the CRM DBv2 schema.
* CRM operational data should be created before the full production booking flow is finalized.

Claude must identify:

* Existing booking files
* Current front-end-only behaviour
* Existing API calls
* Missing server-side validation
* Any mock or hard-coded service/stylist data
* All pages containing booking triggers

## Authentication

Work completed or attempted:

* Auth0 tenant and application setup
* Customer email/password signup
* Email verification
* Login callback processing
* Logout handling
* Customer dashboard integration
* Branded Auth0 emails and domain configuration

Areas requiring verification:

* Exact signup and callback flow
* Session creation
* Callback and redirect handling
* Account error handling
* Duplicate password confirmation requirement
* Mobile number and OTP work, which may not yet be implemented
* Auth0 environment configuration and secret handling
* Authorization checks for protected customer pages

Do not expose Auth0 secrets while auditing.

## Newsletter

Work completed or attempted:

* Newsletter database table
* PHP endpoint at or around `/api/newsletter/subscribe.php`
* JSON subscription request
* Successful direct console test that inserted a database record
* Brevo integration planning
* Shared newsletter JavaScript
* Footer form integration

Known historical issue:

* Direct JavaScript console submission worked, but submitting through the visible website form did not.
* Updated `newsletter.js` and `footer.css` did not immediately appear on cPanel.
* Cloudflare, browser cache or deployment synchronization may have contributed.

Claude must trace:

1. Footer loading
2. DOM initialization
3. Form selector
4. Submit handler
5. Request URL
6. Request headers and body
7. PHP response
8. Database insert
9. Brevo synchronization
10. User-facing success/error state

## Customer portal

Work completed or attempted:

* Customer dashboard
* Profile navigation
* My Appointments
* Upcoming and recent appointment displays
* Mobile navigation
* Quick action cards

Areas requiring verification:

* Responsive appointment tables
* Consistent fonts across portal pages
* Mobile sidebar scrolling
* Profile dropdown opacity
* Hover styling
* Protected-route checks
* Connection to real appointment data versus placeholder content

## Services pages

Dedicated pages and pricing layouts have been built or planned for:

* Men’s Hair
* Women’s Hair
* Hair Colouring
* Hair Texture
* Hand and Foot Spa
* Skin Care
* Beauty Essentials

The services landing page was redesigned around responsive full-width service cards and scroll animations.

Claude must verify:

* Which pages are complete
* Which prices are hard-coded
* Whether shared design tokens are used
* Whether booking buttons pass the correct service
* Whether all links and image paths work
* Accessibility and mobile rendering

## Blog

Work completed or attempted:

* Blog landing page
* Individual long-form blog templates
* SEO-oriented content
* Responsive article layout
* FAQ accordions
* Article images
* Blog hero images

Areas requiring verification:

* FAQ animation smoothness
* Only one FAQ item open at a time
* Text contrast on dark sections
* Shared navigation and booking popup loading
* Hero image sizing and border artefacts
* Consistency between articles

## Database and APIs

Existing or historical tables include:

* Customers
* Services
* Stylists
* Appointments
* Newsletter subscribers

A larger DBv2 schema is being developed for the CRM.

Important boundary:

* The public website should consume controlled APIs.
* It should not duplicate CRM business logic.
* The CRM should become the operational source of truth for branches, services, professionals, availability, customers and appointments.

Claude must not redesign the database without first inspecting the separate CRM repository and DBv2 documentation.

## Current priorities

1. Establish an accurate repository and route inventory.
2. Confirm whether local, GitHub and cPanel code match.
3. Remove or document duplicate shared components.
4. Stabilize navigation, footer and button systems.
5. Verify Auth0 authentication and protected routes.
6. Repair and validate the newsletter form end to end.
7. Document the current booking implementation.
8. Prepare the website to consume the future CRM booking API.
9. Improve testing, logging and deployment validation.
10. Avoid introducing large frameworks without architectural approval.

## Known risks

* Direct cPanel edits may not exist in Git.
* Some pages may use inline or copied CSS/JavaScript.
* Shared partial loading may behave differently when pages are opened locally using `file://`.
* Cloudflare and browser caching may obscure deployments.
* Credentials may exist in local configuration files and must not be exposed.
* Customer authentication and booking flows require security review.
* Public website and CRM responsibilities may currently overlap.
* Some features may be placeholders rather than production integrations.

## Required first audit output

Claude should produce:

1. Repository tree summary
2. Technology and dependency inventory
3. Route/page inventory
4. Shared component inventory
5. API endpoint inventory
6. Authentication flow map
7. Booking flow map
8. Newsletter flow map
9. Database-access inventory
10. Duplicate and obsolete file candidates
11. Security concerns
12. Deployment concerns
13. Recommended remediation order

The first audit must be read-only.
