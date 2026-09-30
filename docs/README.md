# EliteSender SaaS — Governance Documentation

Single source of truth for the EliteSender SaaS platform. All architecture, product, security, and engineering decisions live here, version-controlled with the code.

---

## Document Index

| # | Document | Purpose | Status |
|---|----------|---------|--------|
| 01 | [Product Requirements](01-PRD.md) | Vision, personas, features, pricing, UX philosophy, user stories | Draft |
| 02 | [Technical Architecture](02-ARCHITECTURE.md) | System design, components, data flows, tech stack, tenancy, Redis | Draft |
| 03 | [Database Schema](03-DATABASE.md) | ER diagram, tables, columns, indexes, partitioning, UUID v7 | Draft |
| 04 | [Infrastructure & Deployment](04-INFRASTRUCTURE.md) | Docker, Railway, CI/CD, monitoring, backup, $0 → revenue path | Draft |
| 05 | [API Contract](05-API-CONTRACT.md) | Endpoints, auth, rate limits, envelopes, webhooks, OpenAPI | Draft |
| 06 | [Security & Compliance](06-SECURITY-COMPLIANCE.md) | Bank-grade security, tenant isolation, CAN-SPAM/GDPR/CCPA | Draft |
| 07 | [PWA Specification](07-PWA-SPEC.md) | Mobile app-feel, design system, service worker, notifications | Draft |
| 08 | [Migration Plan](08-MIGRATION-PLAN.md) | specimen1 → Laravel 13 feature parity, data migration, build order | Draft |
| 09 | [Coding Standards](09-CODING-STANDARDS.md) | "20-not-200" rules, local-first testing, PHPStan 9, conventions | Draft |

**Reading order:** 01 → 02 → (03, 04, 05, 06, 07 in any order) → 08 → 09.

---

## Decision Log

All decisions below are locked. Any change requires an ADR (`docs/adr/`) and doc updates.

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Multi-tenancy model | **Row-scoped** — single DB, `tenant_id` on every tenant table | Simple ops, cross-tenant analytics, right for MVP; DB-per-tenant possible later |
| 2 | Frontend | **Blade + Alpine.js 3 + Tailwind CSS 4** (server-rendered) | Matches specimen1's Alpine pattern, zero SPA complexity, fast TTFB |
| 3 | Backend | **Laravel 13+ / PHP 8.5** | Latest features, "20-not-200" expressiveness |
| 4 | Database | **PostgreSQL 17** | JSONB, partitioning, MVCC, managed on Railway |
| 5 | Cache/Queue | **Redis 7 via Unix socket** (`/var/run/redis/redis.sock`) | Zero TCP overhead, ~30% lower latency, no network exposure |
| 6 | Redis isolation | **Per-tenant key prefix** `t:{uuid7_short}:` | Impossible cross-tenant cache/queue/lock bleed |
| 7 | Primary keys | **UUID v7** (time-ordered) | No sequence enumeration, k-sortable, globally unique |
| 8 | Object storage | **Cloudflare R2** (hot) + **Backblaze B2** (archive) | Zero egress fees, free 10GB each |
| 9 | Local dev | **Docker Desktop + Laravel Sail** | One-command stack, matches prod |
| 10 | Deployment | **Railway** — free tier → scale with revenue | $0 until first ~100 users, Git-push deploy, managed PG/Redis |
| 11 | Testing strategy | **Local-first** (Pint + PHPStan 9 + Pest + tenant isolation); GitHub CI as meaningful gate only | Elite quality bar locally; CI confirms, doesn't duplicate |
| 12 | Security posture | **Bank-grade from day 1** | Encryption everywhere, RBAC, 2FA, audit log, tenant isolation tests |
| 13 | UX bar | **$1M premium feel** — zero-tutorial, guided, mobile app-like | Differentiation; frictionless onboarding = conversion |
| 14 | Code philosophy | **"20 lines, not 200"** — Laravel magic maximized | Maintainable, scannable, minimal boilerplate |
| 15 | Docs location | **`/docs/` in-repo** | Version-controlled with code |

### Pricing Tiers (locked)

| Tier | Price | Emails/mo | SMTP Accounts | Contacts | Users |
|------|-------|-----------|---------------|----------|-------|
| Free | $0 | 100 | 1 | 500 | 1 |
| Starter | $15/mo | 5,000 | 3 | 5,000 | 2 |
| Growth | $59/mo | 25,000 | 10 | 25,000 | 5 |
| Scale | $159/mo | 100,000 | 25 | 100,000 | Unlimited |
| Enterprise | Custom | Unlimited | Unlimited | Unlimited | Unlimited |

---

## Glossary

| Term | Definition |
|------|-----------|
| **specimen1** | The archived original EliteSender codebase (single-tenant PHP/SQLite), frozen in `/specimen1/` as reference |
| **Tenant** | A customer workspace; all data is scoped to one tenant |
| **UUID v7** | Time-ordered UUID (RFC 9562); sortable like auto-increment but globally unique |
| **Tenant prefix** | Redis key prefix `t:{uuid7_short}:` isolating all tenant-scoped cache/queue/locks |
| **Spin syntax** | `{hello|hi|hey}` template syntax for content variation per recipient |
| **SMTP pool** | Tenant's set of SMTP accounts with health scoring and rotation |
| **Bounce mailbox** | IMAP inbox polled for delivery failure notifications |
| **Suppression list** | Emails that must never be sent to (bounces, complaints, unsubscribes) |
| **Hard bounce** | Permanent delivery failure (invalid address) → immediate suppression |
| **Soft bounce** | Temporary failure (mailbox full) → retry, suppress after N occurrences |
| **HasTenant** | Eloquent trait auto-applying tenant global scope + setting `tenant_id` on create |
| **Invokable Action** | Single-responsibility class with one `__invoke()` method — the "20-not-200" unit of business logic |

---

## Contributing to Docs

1. Create branch `docs/{topic}`
2. Update affected documents (keep decision log in sync)
3. Cross-check: versions, pricing, UUID v7, Redis strategy must be consistent everywhere
4. PR with summary of changed decisions (if any — requires ADR)

---

## Addendum — implementation audit (2026-09-28)

A full audit was run against this decision log. The decisions below were
documented but **not implemented**; each is now either built or explicitly
re-scoped. Nothing in the locked list above changed.

| # | Decision | Audit finding | Status |
| --- | --- | --- | --- |
| 3 | Laravel 13 / PHP 8.5 | `composer.json` declared `^8.3` while the lock pinned Symfony 8 (needs ≥ 8.4.1). CI and the Docker image could not install. | Floor raised to `^8.4`; CI matrix runs 8.4 and 8.5. |
| 4 | PostgreSQL 17 | Five tables used bigint keys against uuid parents — valid on SQLite, rejected by PostgreSQL. | Migrations corrected; CI now migrates and rolls back against `postgres:17`. |
| 5/6 | Redis 7, per-tenant key prefix `t:{uuid7_short}:` | Implemented as `Redis::setPrefix()`, which does not exist. The resulting exception was swallowed by an empty `catch`, so the control was a no-op and tenants shared cache keys. | Replaced by `App\Tenancy\TenantCache`, which namespaces the cache store and restores it in a `finally`. |
| 9 | Bank-grade security | No policies, no RBAC, `authorize()` always true, unsigned unsubscribe links, `tenant_id` accepted from a request body, `trustProxies(at: '*')`. | Policy layer, role hierarchy, signed opt-out links, capability-keyed capture forms, configurable proxy trust. |
| 10 | Local-first testing (Pint + PHPStan 9 + Pest) | Neither Pint nor PHPStan was configured or run anywhere; CI ran neither. | `pint.json` and `phpstan.neon` added; PHPStan is clean at level 6 and enforced in CI. |

### Deviations from `docs/08-MIGRATION-PLAN.md`

| Planned artefact | Outcome |
| --- | --- |
| `SmtpPool::nextAccount()` health-weighted rotation | Built as `App\Services\SmtpPool`, deterministic so a replayed chunk distributes identically. |
| Seeded-per-recipient spin syntax | Built; `SpinSyntaxService::compile()` takes a seed. |
| `suppression_entries` hash-only table | Built, keyed HMAC-SHA256 so opt-outs survive GDPR erasure. |
| `App\Actions\*` invokables | **Not adopted.** The logic lives in `app/Services` with the same boundaries. Introducing a second convention mid-audit would have been churn, not clarity. |
| Horizon lanes high/default/low | Lanes exist and jobs declare them; **Horizon itself is not installed** (it is not in the lock file, and packages cannot be added without a dependency-resolution pass). Supervisor runs the lanes directly. |

### Known gaps, tracked openly

1. **No scoped API tokens.** A Sanctum token carries its owner's full rights.
2. **No per-tenant encryption key.** All workspaces share `APP_KEY`.
3. **`'unsafe-eval'` remains in the CSP** because Alpine compiles `x-*`
   expressions at runtime. Removing it means adopting Alpine's CSP build.
4. **Automation branching is linear.** A `condition` step ends the journey when
   it does not match, rather than following a second branch. True A/B
   branching needs a second edge on `automation_steps`.
5. **Campaigns paused by a worker cannot be resumed from the UI.** When a
   relay pool or a plan allowance runs out mid-send the campaign is paused
   and the operator is told why, but restarting it needs a new campaign:
   re-dispatching would re-fan-out the whole list and double-send everyone
   already delivered. A per-recipient delivery ledger (or `Bus::batch`)
   closes this properly.
6. **Automation `send_email` does not record a CampaignEvent for the send
   itself**, so automation open/click rates are measured against the
   broadcast denominator.

### Closed since the last audit

- **Automations now execute** (`app/Automations`): enrolment, seven step types,
  a per-minute scheduler, and row-locked claims so concurrent workers cannot
  double-send.
- **Billing is fully integrated**: Paystack and Stripe both save a credential
  at checkout and re-charge it off-session, with a 1/3/5-day dunning schedule,
  prorated upgrades, period-end downgrades, one-click resume, and suspension
  that stops sending without touching customer data.
- **Dunning is now emailed, not only logged.** Gap #5 above is closed by the
  customer-lifecycle module below.

---

## Addendum — customer lifecycle audit (2026-10-03)

The product could take a customer's money but could not talk to them. Outside
password reset, the only email it had ever sent was a customer's own campaign:
no welcome, no trial warning, no invoice, no receipt, no dunning notice, and
nothing at all before sending was suspended.

| Stage | Finding | Status |
| --- | --- | --- |
| Guest → signup | The operator console's "Allow new signups" switch was read by nothing; registration stayed open however it was set. `platform.name` and `platform.support_email` had no config file to override, so both resolved to null. | `config/platform.php` added; `EnsureRegistrationIsOpen` enforces the switch. |
| Signup → activation | The first-run wizard was a mock. `testSmtp()` waited a second and toasted "SMTP Connected Successfully"; `importContacts()` waited 1.5s and toasted "Contacts imported". Neither made a request. New customers were congratulated three times and landed on an empty dashboard. | Replaced by `App\Services\ActivationChecklist` — five steps, each derived from real workspace state, surfaced on both the onboarding page and the dashboard. |
| Abuse control | Anyone could open a workspace with any address, unverified, and send. For a platform that sends on a customer's behalf that is how IP ranges get listed. | Email verification enabled as a **send gate**, never a login wall: the whole product stays open, only the send button waits. |
| Trial → paid | Nothing warned a trial was ending. | `TrialEnding`, 3 days out. |
| Checkout | An invoice raised and abandoned was never mentioned again. | `InvoiceIssued` on creation; `InvoiceReminder` at day 1 (recovery) and day 3 (due soon) — two nudges, never more. |
| Renewal | Cards were charged with no advance notice, the top chargeback trigger. | `RenewalReminder`, 3 days out, only when a charge will genuinely be attempted. |
| Dunning | Declines were logged and retried silently. | `PaymentFailed` per attempt, escalating, with the next retry date. |
| Suspension | Sending stopped with no warning and no notice. | `SuspensionWarning` 2 days out, `WorkspaceSuspended` on the day, `WorkspaceReinstated` when payment lands. A suspended workspace now gets a page explaining that nothing was deleted, not a bare 403. |
| Usage | Customers discovered their monthly cap when recipients stopped receiving mail. | `UsageThresholdReached` at 80% and 100%. |
| Churn | Cancellation captured nothing and confirmed nothing. | Reason vocabulary + optional note, captured on the subscription and surfaced to support; `SubscriptionCancelled` with a one-click undo; `WinBackOffer` once, a week after the drop to free. |

Two properties are enforced by construction rather than by care:

- **Exactly once.** Every message is claimed by a unique insert into
  `lifecycle_messages` *before* it is sent, so concurrent schedulers, an
  hourly cadence and an accidental re-run all produce one email. A duplicate
  "your card was declined" reads as a second decline.
- **Never fatal.** `LifecycleMessenger` reports and swallows its own failures.
  A mail outage cannot roll back a settled payment or stop a suspension.

Operators can see exactly what a workspace was told, and when, on the tenant
page — because "did they get the warning?" is the first question support asks
about any billing complaint.
