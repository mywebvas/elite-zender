# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added — automations, recurring billing and a design-system pass

- **Automations execute.** `app/Automations` adds enrolment, a step runner for
  all seven types, and a per-minute scheduler. Enrolments are claimed under a
  row lock with the due time pushed forward before the step runs, so concurrent
  workers cannot send one person the same email twice. Triggers are wired to
  contact creation, public lead capture, opens and clicks; bulk CSV import
  deliberately does not enrol.
- **Recurring subscriptions.** Paystack (`charge_authorization`) and Stripe
  (off-session `PaymentIntent`) both store a credential at checkout and renew
  without the customer returning. Stripe is live the moment its keys are set.
- **Lifecycle**: trials convert on their own, renewals anchor to the previous
  period end, dunning retries on a widening 1/3/5-day schedule, lapsing
  suspends *sending only*, cancellation is undoable in one click, upgrades are
  prorated and downgrades scheduled for period end.
- **Design system**: consolidated the two conflicting `@theme` blocks into one,
  added a single focus-visible treatment, a coherent elevation and motion
  scale, tabular figures on every statistic, and `<x-banner>` / `<x-stat>`
  components. Billing state is resolved once per request and shared with every
  view so notices cannot contradict each other between pages.

### Fixed — found by the new tests

- A wait step parked the enrolment without advancing the cursor, so the same
  wait ran again when it came due: an automation that waits for ever.
- `Contact::create()` without an explicit status left the attribute absent in
  memory, so the subscribe trigger read no status and declined to enrol.
- `recordPayment()` stored the reusable card credential *before* `settle()`,
  but `settle()` is what creates the subscription on a first purchase — so no
  new customer's card was ever saved and their first renewal would have failed.
- `resources/css/app.css` defined the brand palette and surfaces twice with
  different values; whichever block loaded last silently won.

### Fixed — production blockers

- **Campaign sending was impossible.** `CampaignEmail::content()` passed a
  `textString:` argument to `Illuminate\Mail\Mailables\Content`, which has no
  such parameter; every real send raised
  `Error: Unknown named parameter $textString`. `Mail::fake()` never renders a
  mailable, so the test suite reported green.
- **Dependency floor was wrong.** `composer.json` declared `php: ^8.3` while
  `composer.lock` pins Symfony 8 (requires ≥ 8.4.1). `composer install` failed
  on the PHP version used by CI and by the Docker image.
- **Schema was invalid on PostgreSQL.** `automations`, `automation_steps`,
  `contact_automations`, `tags` and `contact_tag` used bigint keys pointing at
  uuid parents. Accepted by SQLite, rejected by PostgreSQL 17.
- **Every authenticated API route returned 500.** `config/auth.php` defined no
  `sanctum` guard.
- **SMTP relays were handed ciphertext.** The controller encrypted a value the
  model already encrypts through its `encrypted` cast.
- **`smtp_accounts.username`/`password` were NOT NULL** while validation
  allowed them to be omitted, so a valid submission 500'd.
- **Two-factor auth and passkeys were dead.** Both Fortify features were
  enabled but `User` used neither `TwoFactorAuthenticatable` nor
  `PasskeyAuthenticatable`.
- **CSV import blocked a web worker.** It parsed inline inside a single
  transaction and ran a live `checkdnsrr()` MX lookup per row.
- **The documented per-tenant Redis prefix was a no-op.** `Redis::setPrefix()`
  does not exist; the resulting exception was swallowed by an empty `catch`.
- **Tenant context never applied to the API.** `ResolveTenant` was registered
  on the web stack only, leaving the `HasTenant` global scope inert for every
  `auth:sanctum` request.
- **Redis throttling was never enabled in production.** The check read
  `env('APP_ENV')` from `bootstrap/app.php`, which returns `null` once
  `config:cache` has run.
- **Smart retargeting excluded nobody** while telling the operator it had.
- **Nothing was scheduled.** Daily SMTP quotas never reset, campaigns never
  left `sending`, bounces were never scanned, audit logs grew without bound.
- **CI could not have caught any of this**: no asset build (so `@vite` threw),
  no Pint, no PHPStan, no PostgreSQL job, no dependency audit.

### Added

- Authorization layer: `app/Policies` with a shared `TenantResourcePolicy`,
  an owner → admin → member → viewer role hierarchy, and Form Requests that
  actually authorise.
- Signed, non-expiring unsubscribe links; GET confirms, POST opts out
  (RFC 8058 one-click supported and exempted from CSRF).
- Bounce pipeline: `BounceClassifier` (RFC 3463 enhanced codes with phrase
  fallback), `BounceProcessor`, and a hashed `suppression_entries` table.
- `SmtpPool`: deterministic health- and quota-weighted relay rotation that
  honours `daily_limit`, `status` and `health_score`, with atomic usage commit.
- Streaming, batched, queued CSV importer with a status endpoint.
- Tenant-scoped REST API v1 with JsonResource transformers.
- Lead-capture forms authenticated by an opaque public key, with an origin
  allow-list and a honeypot.
- Real dashboard metrics, persisted workspace settings, delivery counters.
- Scheduled commands: quota reset (per-workspace midnight), campaign
  finalisation, bounce scanning, audit-log retention purge.
- `pint.json`, `phpstan.neon` (level 6, clean), a six-job CI pipeline,
  Dependabot, `.dockerignore`, `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`.
- Regression suite under `tests/Feature/Regression/`.

### Changed

- `Dockerfile` rebuilt as three stages (assets → vendor → runtime); the Node
  toolchain no longer ships to production, the build no longer runs
  migrations, and the sqlite/array defaults that contradicted the documented
  PostgreSQL + Redis topology are gone.
- Spin syntax is seeded per recipient, so a retried chunk produces the same
  variant.
- Audit entries truncate long values and can be suppressed for bulk imports.
- `TenantContext::run()` restores the previous tenant in a `finally`, removing
  the context leaks in jobs and tracking endpoints.

### Fixed — second pass (red-team review)

- **Password reset and the two-factor challenge were unreachable.** Four
  Fortify features were enabled with no view bound, so `/forgot-password`,
  `/reset-password`, `/two-factor-challenge` and `/user/confirm-password` all
  returned 500. Enabling 2FA locked a user out of their own workspace. The
  login form's "Forgot password?" link was a dead `#` that popped a toast
  claiming the feature did not exist.
- `/onboarding` returned 500: `:disabled="testingSmtp"` on a Blade component is
  evaluated as PHP, so the Alpine identifier parsed as an undefined constant.
- `/settings` returned 500 reading a two-factor attribute the model had not
  loaded; `campaigns/show` referenced `from_name`/`from_email`, columns that do
  not exist on `campaigns`.
- `trustProxies(at: '*')` trusted `X-Forwarded-For` from any client — forgeable
  source IPs, defeating every IP-keyed limit and poisoning the audit trail.
- `config/octane.php` had an empty `flush` list while the tenant is bound as a
  container instance, so it could outlive a request and scope the next one.
- The layout loaded Turbo from a CDN that the CSP blocked; Turbo never ran.
- `manifest.json` referenced three icon files that did not exist.
- Eight files carried a UTF-8 BOM, including Blade templates where it emits
  stray bytes before `<!DOCTYPE`. `.gitattributes` now enforces LF and no BOM.
- Flashed validation errors were never rendered, so a refused action (e.g.
  "only draft campaigns can be sent") looked like a silent no-op.

### Added — second pass

- `config/cors.php` (the framework default allowed `*` on every `api/*` path)
  plus a separate credential-free policy for the embeddable capture endpoint,
  which previously had no CORS at all and therefore could not be embedded.
- Rate limiters on password reset and registration; content-negotiated throttle
  responses.
- `tests/Feature/SmokeTest.php` — renders every page for a signed-in owner;
  it found four of the 500s above on its first run.
- `tests/Architecture/` — rules encoding each defect class (no `env()` outside
  config, no debug helpers, jobs are queued, services are final, …).
- Structured JSON logging with a tenant/user/request processor.
- A two-workspace seeder: with one tenant, a missing `where tenant_id = ?`
  looks exactly like a correct query.
- `/sitemap.xml`, `<x-seo>` (canonical, Open Graph, Twitter card, JSON-LD),
  skip links on both shells.
- `deploy/README.md`; rewritten nginx and supervisor configs.

### Security

- Cross-tenant write via `Api\LeadCaptureController` (`tenant_id` was read
  from the request body) — closed.
- Cross-tenant attachment via unscoped `exists:contact_lists,id` validation —
  closed.
- Unsubscribe forgery and scanner-triggered mass opt-out — closed.
- SSRF through the click relay: `SafeRedirect` now resolves hostnames and
  rejects private, loopback and link-local targets, and URLs with embedded
  credentials.
- Per-tenant SMTP credentials no longer persist in the shared config
  repository after a job finishes (Octane leak).
