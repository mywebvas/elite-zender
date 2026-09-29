# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added — account security, life above the plan ceiling, and leaving

- **Security alerts.** Changing the account email, changing the password,
  resetting it, and switching two-factor on or off all happened in complete
  silence. That is the recipe for account takeover: get a session, change
  the address, then reset the password to one you control — every step a
  legitimate action by an authenticated user, so nothing else objects. An
  email change now warns the **old** address, because that is the only inbox
  the real owner still controls. None of these can be switched off.
- **Plan-limit overages.** A downgrade leaves a workspace holding more than
  the new tier allows — ten seats on a one-seat plan, twenty thousand
  contacts on a five-hundred plan. Nothing deleted it, which is correct, and
  nothing mentioned it, which is not: customers met the limit as a silent
  refusal months later. Now named on every page and in one email a month,
  with an explicit promise that nothing will be deleted.
- **Data export (GDPR Art. 15).** A streamed zip of contacts, lists,
  campaigns, engagement history, team and relay configuration as UTF-8 CSV
  with a BOM so Excel renders it correctly. Credentials are deliberately
  excluded — an export travels, and a bearer credential against a customer's
  sending domain has no business in one. Served behind the session, never a
  public link, and shredded automatically after seven days.
- **Workspace deletion (GDPR Art. 17).** Owner-only, re-authenticated,
  requires typing the workspace name, and *scheduled* with a seven-day
  cooling-off window that one click cancels. `suppression_entries` are
  detached rather than cascaded: they hold one-way hashes and no addresses,
  and they exist because a recipient asked never to be emailed again — a
  promise made to them, not to the workspace.
- **`elitesender:process-data-requests`**, hourly, with `--dry-run`.


### Added — team seats, and the lifecycle gaps that survived the first pass

- **Team members.** Every plan sold seats — Free 1, Starter 3, Growth 10,
  Scale 25, Enterprise unlimited — the billing page rendered a usage meter
  against that limit, and the plan cards advertised "10 team members" as a
  headline feature. There was no route, no controller and no view: a Growth
  customer paid $59 a month for nine seats that could not exist. Invitations
  (hashed, single-use, expiring), role management, revocation, resend, and a
  seat count that includes pending invitations so the number on the pricing
  page means something.
- **Offboarding is a security event.** Removing a member revokes their
  sessions and API tokens and tells them it happened; the last owner cannot
  be removed or demoted, and nobody can demote themselves.
- **Activation drip.** A workspace that signs up and never connects a relay
  got one welcome email and then silence forever — the biggest leak in the
  funnel. Two nudges, at day 2 and day 6, naming the single blocking step,
  both stopping the moment it is done.
- **Card-expiry warnings.** Both gateways return the expiry on the first
  charge and it was being discarded, so involuntary churn was only ever
  discovered as a decline. Captured now, and warned 14 days out.
- **Campaign reports.** A performance summary when a campaign finishes
  delivering — the retention loop for a product whose value is measurement,
  and event-driven rather than a digest that sometimes has nothing to say.
- **Email preferences.** Setup nudges and campaign reports are opt-out.
  Billing and security notices are not, by construction rather than by a
  check somebody can forget.
- Verification and password-reset emails now use the product's own shell
  instead of stock Laravel markdown.

### Fixed — three features that only pretended to work

- **The SMTP "Test connection" button never opened a socket.** It waited 1.5
  seconds and toasted "Connection test successful!". Customers learned the
  truth when their first campaign silently failed, by which point the relay
  was in rotation and the failures looked like a deliverability problem. It
  now performs a real handshake and records the result on the relay.
- **The IMAP settings form had no action.** The save button fired a success
  toast and wrote nothing, so `ScanBounces` skipped every workspace on every
  15-minute run and the entire Bounce Shield feature was unreachable — while
  telling the customer it was configured. The form now saves, the password is
  encrypted at rest, and the mailbox can be disconnected.
- An architecture test now fails the build on any client-side success toast.
  This codebase shipped that same lie three times.


### Added — the customer lifecycle

Before this the product could take money but could not talk to anyone. Outside
password reset, the only email it had ever sent was a customer's own campaign.

- **Transactional email foundation.** A branded, table-based, dark-mode-aware
  email shell with an automatically derived plain-text alternative (a missing
  `text/plain` part is a textbook spam signal, and hand-written ones rot).
  Content is data, not twelve near-identical Blade files, so the whole set
  cannot drift apart.
- **Thirteen lifecycle messages**, half event-driven from `BillingService`
  (invoice issued, payment received, payment failed, suspended, reinstated,
  cancelled, welcome) and half time-driven from the new
  `elitesender:lifecycle` command (trial ending, abandoned checkout, invoice
  due, renewal reminder, suspension warning, usage thresholds, win-back).
- **Exactly-once delivery.** Every message is claimed by a unique insert into
  `lifecycle_messages` before it is sent, so concurrent schedulers, an hourly
  cadence and an accidental re-run all produce one email. Operators can see
  what a workspace was told, and when, on the tenant page.
- **Abandoned-checkout recovery.** An invoice raised and left unpaid is
  chased twice — once at day 1 with a direct link back to the payment page,
  once at day 3 — and never again.
- **Advance notice of every charge.** Recurring debits are announced three
  days out, which is what card-network rules expect and the cheapest
  chargeback prevention available.
- **Usage alerts at 80% and 100%** of the monthly allowance, so a customer
  finds out before recipients stop receiving mail rather than after.
- **Cancellation reason capture** with a fixed vocabulary plus an optional
  note, a "compare plans instead" save step, one-click undo, and a single
  win-back check-in a week after the drop to free.
- **Email verification, as a send gate rather than a login wall.** The whole
  product stays open to an unverified owner; only the send button waits. For
  a platform that sends on a customer's behalf, an unverified account is how
  IP ranges get blocklisted.
- **`elitesender:lifecycle`**, scheduled hourly with a `--dry-run` that
  reports without claiming anything.

### Fixed — activation and operator controls

- **The first-run wizard was a mock.** `testSmtp()` waited one second and
  toasted "SMTP Connected Successfully"; `importContacts()` waited 1.5s and
  toasted "Contacts imported". Neither issued a single request — no relay was
  created, no contact imported, nothing persisted. A new customer was
  congratulated three times and landed on an empty dashboard unable to send.
  Replaced with a checklist whose every tick is derived from real workspace
  state, surfaced on both the onboarding page and the dashboard.
- **"Allow new signups" did nothing.** The operator console wrote the setting
  to the database and registration stayed open however it was set — the kind
  of control an operator reaches for mid-incident.
- **`platform.name` and `platform.support_email` had nothing to override.**
  There was no `config/platform.php`, so both resolved to null wherever they
  were read.
- **A suspended workspace got a bare 403** with no reason, no contact and no
  indication that the data still existed. It now gets a page that says so.
- **The billing banner always pointed at the same generic page.** It now
  links to the specific invoice that needs paying.


### Added — the operator console becomes a real control plane

- **Audit trail.** Every destructive operator action is written to
  `admin_activity_log`: who, what, which workspace, the before/after, the IP,
  and a free-text reason. Previously these went to a log *file* — unqueryable,
  and rotated away long before anyone asks "who suspended this customer?".
  Filterable by operator, action, severity and date, and exportable as CSV.
  Entries are immutable and secrets are redacted before they are written.
- **Platform settings.** Trial length, grace period, default currency, product
  name, signups open/closed and every payment-gateway credential are now
  editable from the console and take effect immediately. Rotating a leaked
  Stripe key or closing registration during an incident no longer needs a
  deploy. Settings are an *override layer*: anything unset falls through to
  `config()`, so an empty table behaves exactly like today.
- **API keys.** Scoped, expiring, IP-restrictable platform keys. Only a
  SHA-256 is stored, so a database dump cannot be replayed as API access, and
  the plaintext is shown exactly once.
- **Operator management.** Create, re-role, deactivate and reset operators from
  the UI. Self-elevation is blocked, and the last active super admin cannot be
  deactivated — that state is unrecoverable without shell access.
- **Plan lifecycle.** Plans can now be created (private until published) and
  archived. A plan with subscribers is never hard-deleted, because invoices
  reference it.
- **System health.** Database, cache, storage, scheduler heartbeat, failed
  jobs, stuck campaigns and paused automations, plus queue depth and the
  schedule — with retry and discard for failed jobs.
- **Cross-tenant support tools.** Find any user by email, reset their password
  and end every session in one action, change a role to recover a locked-out
  owner, and look up or manage suppressions.
- **Global search** across workspaces, users and invoices.
- Console shell rebuilt: grouped navigation, super-admin-only sections hidden
  rather than 403ing, one-time secret reveal with copy-to-clipboard, and a
  "needs attention" band above the metrics.

### Fixed

- `SuppressionController::store` read `$validated['tenant_id']` directly; a
  nullable field that is not submitted has no key at all, so the common path
  was an undefined-index 500.

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
