# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
