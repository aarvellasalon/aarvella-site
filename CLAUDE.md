# Aarvella Website — Claude Instructions

## Project identity

This repository contains the public website and customer-facing portal for Aarvella Unisex Salon, Dehradun.

The public website is separate from the Laravel CRM repository named `aarvella-crm`.

## Production architecture

* Primary domain: `aarvella.com`
* Hosting: Namecheap shared hosting/cPanel
* CDN and proxy: Cloudflare
* Source control: GitHub
* Local development: VS Code on Windows
* Production deployment should originate from committed GitHub code
* Do not treat cPanel as the primary development environment

## Technology

Inspect the repository before making assumptions.

The existing website primarily uses:

* HTML and PHP pages
* CSS
* Vanilla JavaScript
* PHP API endpoints
* MySQL/MariaDB
* Composer dependencies
* Auth0 for customer authentication
* Brevo for newsletter and customer communication

Do not introduce React, Vue, Angular, Next.js, Laravel, Tailwind, Bootstrap, jQuery, or another framework into this repository unless explicitly approved.

## Repository rules

1. Inspect existing conventions before adding files.
2. Reuse shared components instead of copying code into pages.
3. Keep shared CSS under `assets/css/`.
4. Keep shared JavaScript under `assets/js/`.
5. Keep reusable HTML/PHP partials in the existing partials structure.
6. Keep page-specific styles and scripts separate from global files.
7. Avoid inline CSS and inline JavaScript unless there is a documented reason.
8. Preserve responsive behaviour for desktop, tablet, and mobile.
9. Preserve the Aarvella black, gold, white, frosted-glass and premium design system.
10. Do not silently rename URLs or public files because existing search indexing and links may depend on them.

## Shared components

Where applicable, use and preserve the existing shared systems for:

* Navigation
* Footer
* Buttons
* Booking popup
* Authentication
* Customer dashboard
* Newsletter subscription
* Common animations

Before modifying one of these components, identify every page that consumes it.

## Backend and database safety

* Do not expose database credentials, Auth0 secrets, Brevo keys, API keys, session secrets, or production configuration.
* Never print secrets in terminal output, documentation, commits, or chat responses.
* Do not modify the production database directly.
* Database changes must be provided as reviewable migrations or SQL scripts.
* Preserve compatibility with the current MariaDB/MySQL environment.
* Validate and sanitize all user-controlled input.
* Use prepared statements for database queries.
* Consider authentication, authorization, CSRF, XSS, session security, rate limiting, and error handling.

## Git workflow

Before making changes:

1. Run `git status`.
2. Identify the current branch.
3. Inspect recent commits.
4. Confirm that relevant files are tracked.
5. Present an implementation plan.

Do not commit, push, deploy, reset, force-push, delete branches, or rewrite Git history without explicit approval.

Prefer a feature branch for non-trivial changes.

## Required workflow

For every development task:

1. Inspect the relevant implementation.
2. Trace the complete user flow.
3. Identify shared dependencies.
4. Explain the root cause or existing architecture.
5. Present a file-by-file plan.
6. Wait for approval before significant edits unless explicitly told to implement.
7. Make the smallest coherent change.
8. Validate syntax.
9. Run available tests or practical checks.
10. Summarize changed files, validation performed, risks, and deployment steps.

## Current project records

Read the following files before proposing architectural changes:

* `docs/PROJECT_STATUS.md`
* `docs/ARCHITECTURE.md`, when present
* `README.md`
* `.gitignore`
* Composer and package configuration files

Treat the repository implementation as authoritative when it conflicts with outdated documentation. Report the discrepancy instead of silently choosing one.

## Initial session behaviour

At the beginning of the first session:

* Perform a read-only audit.
* Do not edit files.
* Build a repository map.
* Identify entry points, shared components, APIs, authentication flow, customer portal, booking implementation, newsletter implementation, dependencies and deployment-related files.
* Report duplicate, obsolete, risky and untracked patterns.
