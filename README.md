<h1 align="center">EliteSender</h1>

<p align="center">
  <strong>Multi-tenant email marketing platform.</strong><br>
  SMTP-pool rotation · deliverability tooling · automations · real-time analytics
</p>

<p align="center">
  <img alt="PHP 8.4+" src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="PostgreSQL 17" src="https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white">
  <img alt="Tests" src="https://img.shields.io/badge/tests-609%20passing-3FB950">
  <img alt="PHPStan level 6" src="https://img.shields.io/badge/PHPStan-level%206-2A6FDB">
</p>

---

## What this is

EliteSender lets a workspace import an audience, compose a campaign, and send
it through **its own pool of SMTP relays** — rotating across them by health and
daily quota so a single burnt relay cannot take the whole sending domain with
it. Opens, clicks, bounces, complaints and unsubscribes all feed back into the
same audience so the next send is cleaner than the last.

| Capability | Status |
| --- | --- |
| Multi-tenant workspaces (row-scoped, UUID v7 keys) | ✅ |
| Auth: Fortify, TOTP two-factor, passkeys (WebAuthn) | ✅ |
| RBAC: owner / admin / member / viewer, enforced by policies | ✅ |
| Contact lists, tags, streaming CSV import (queued) | ✅ |
| Campaign builder, spin syntax, merge tags | ✅ |
| SMTP pool with health- and quota-weighted rotation | ✅ |
| Open / click tracking, signed one-click unsubscribe (RFC 8058) | ✅ |
| Bounce + complaint classification, hashed suppression list | ✅ |
| Smart retargeting (exclude previous openers) | ✅ |
| REST API v1 (Sanctum) with resource transformers | ✅ |
| Automations: builder, runtime engine, 7 step types | ✅ |
| Billing: plans, invoices, usage limits, refunds | ✅ |
| Payments: Paystack (NGN + USD), offline bank transfer | ✅ |
| Payments: Stripe (cards saved for automatic renewal) | ✅ |
| Recurring billing, dunning, proration, cancel/resume | ✅ |
| Operator console: separate guard, impersonation, plan control | ✅ |
| Operator audit trail, settings, API keys, health, team management | ✅ |
| Email verification as a send gate (never a login wall) | ✅ |
| Guided activation checklist, derived from real workspace state | ✅ |
| Lifecycle email: welcome, trial, invoice, renewal, dunning, suspension | ✅ |
| Abandoned-checkout recovery and usage-threshold alerts | ✅ |
| Cancellation reason capture, one-click undo, win-back check-in | ✅ |
| Team seats: invitations, roles, revocation, seat-limit enforcement | ✅ |
| Activation drip, card-expiry warnings, post-campaign reports | ✅ |
| Per-user email preferences (billing and security always on) | ✅ |

---

## Architecture at a glance

```
Browser ──► nginx ──► PHP-FPM (or Octane/RoadRunner)
                         │
      ┌──────────────────┼────────────────────┐
      ▼                  ▼                    ▼
 PostgreSQL 17       Redis 7             Object storage
 (row-scoped        (cache, queue,       (R2 / B2 —
  tenancy)           session, per-        imports, assets)
                     tenant prefix)
                         │
                         ▼
                  Queue workers ──► tenant SMTP relays
```

**Tenancy is row-scoped, not schema- or database-per-tenant.** Every
tenant-owned table carries `tenant_id`; `App\Tenancy\HasTenant` adds a global
scope and stamps the column on create, `App\Http\Middleware\ResolveTenant`
binds the workspace for both the web *and* API stacks, and policies in
`app/Policies` re-check ownership for anything resolved outside a scoped query
(jobs, tracking pixels, webhooks).

Key building blocks:

| Path | Responsibility |
| --- | --- |
| `app/Tenancy/` | Tenant context, global scope, UUID v7 keys, audit trail |
| `app/Policies/` | Isolation + RBAC (`TenantResourcePolicy` is the base) |
| `app/Services/SmtpPool.php` | Deterministic health/quota-weighted relay rotation |
| `app/Services/ContactCsvImporter.php` | Streaming, batched CSV ingestion |
| `app/Services/BounceClassifier.php` | RFC 3463 DSN classification |
| `app/Support/UnsubscribeLink.php` | Signed, non-expiring opt-out URLs |
| `app/Jobs/` | Campaign fan-out, chunked sending, bounce ingestion, imports |
| `app/Automations/` | Enrolment engine and step runner |
| `app/Billing/` | Gateways, invoicing state machine, plan limits, dunning |
| `app/Http/Controllers/Admin/` | Operator console (separate `admin` guard) |
| `app/Platform/` | Audit logger, runtime settings, health checks |
| `docs/` | Decision log and module specs — **read `docs/README.md` first** |

---

## Requirements

- **PHP 8.4+** (the dependency set pins Symfony 8, which requires ≥ 8.4.1)
- Composer 2
- Node 22 + npm
- PostgreSQL 17 and Redis 7 for anything beyond the test suite

---

## Getting started

```bash
git clone git@github.com:mywebvas/elite-zender.git
cd elite-zender

composer install
npm ci

cp .env.example .env
php artisan key:generate

# Point DB_* at your PostgreSQL instance first, then:
php artisan migrate --seed

npm run build      # or: npm run dev
php artisan serve --port=8085
```

Or simply:

```bash
make setup   # install, migrate, build
make dev     # dev server + Vite watcher
make check   # style + static analysis + tests (what CI runs)
```

### Creating the first operator

There is no self-service admin signup and no seeded default credentials — a
well-known default admin account is how a platform gets owned on day one.

```bash
php artisan elitesender:make-admin
```

Then sign in at `/admin`. Operators live in their own table behind their own
guard, so a customer session can never reach the console (and vice versa).

From there everything else is self-service: further operators, plans and
pricing, payment-gateway credentials, API keys, suppressions, system health and
the full audit trail. The only thing that still needs shell access is creating
the very first operator — deliberately.

### Background processing

Sending, imports and bounce ingestion all run on the queue. In any environment
where you intend to actually send mail you need both of these:

```bash
php artisan queue:work --queue=high,default,low
php artisan schedule:work       # in production: a single cron entry
```

Without the scheduler, automations never advance, subscriptions never renew,
daily SMTP quotas never reset, campaigns never move off "Sending", audit logs
grow without bound, and **every customer-lifecycle email stops** — no trial
warning, no renewal notice, no dunning, no warning before suspension. Treat a
dead scheduler as a production incident; the operator console's health page
shows its heartbeat for exactly that reason.

---

## Quality gates

Everything below runs in CI on every push, and `composer check` runs the same
gates locally:

| Gate | Command |
| --- | --- |
| Code style | `composer lint` (`vendor/bin/pint --test`) |
| Static analysis | `composer analyse` (PHPStan level 6) |
| Tests | `composer test` (Pest) |
| Front-end build | `npm run build` |
| PostgreSQL schema | migrate + rollback against `postgres:17` |
| Dependency audit | `composer audit`, `npm audit` |
| Production image | `docker build .` — the artefact Railway deploys |

The PostgreSQL job is not ceremony: SQLite's loose typing will happily accept a
`bigint → uuid` foreign key that PostgreSQL rejects outright, so the schema is
proven on the engine production actually uses.

---

## Testing

```bash
composer test                              # whole suite
vendor/bin/pest tests/Feature/Regression   # regression suite only
vendor/bin/pest --filter=unsubscribe
```

`tests/Feature/Regression/` documents defects that reached `main` once and must
never return — each file explains the original failure in its header.

Tests run against an in-memory SQLite database (`phpunit.xml`), with
`RefreshDatabase` applied by `Tests\TestCase` itself so a plain PHPUnit class
cannot accidentally run without migrations.

---

## Deployment

The image is built by a three-stage `Dockerfile` (assets → vendor → runtime)
and deployed to Railway (`railway.json`, health check `/up`).

Configuration is injected at run time — the image deliberately does **not**
bake `config:cache`, because `env()` returns `null` once the config cache is
built and every credential arrives from the platform.

Required at run time: `APP_KEY`, `DB_*`, `REDIS_*`, `SESSION_SECURE_COOKIE=true`,
`APP_URL`. See `.env.example` for the full list.

---

## Documentation

| Document | Contents |
| --- | --- |
| [`docs/README.md`](docs/README.md) | **Locked decision log** — read before changing anything structural |
| [`docs/01`–`docs/09`](docs/) | Module specs: architecture, database, API contract, security, migration plan, coding standards |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Workflow, conventions, definition of done |
| [`SECURITY.md`](SECURITY.md) | Vulnerability disclosure and security posture |
| [`CHANGELOG.md`](CHANGELOG.md) | Notable changes |

---

## License

Proprietary — © MyWebVas. All rights reserved. See [`LICENSE`](LICENSE).
