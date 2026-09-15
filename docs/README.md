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
