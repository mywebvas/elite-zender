# EliteSender — working agreement for AI agents

This replaces the generic Laravel Boost stub. Read it before changing anything.

## What this application is

A **multi-tenant** email marketing platform. Every customer ("workspace" /
tenant) imports an audience, composes campaigns, and sends them through **its
own pool of SMTP relays**. Two facts shape almost every decision:

1. **Tenant isolation is the product's integrity guarantee.** A query that
   forgets `tenant_id` is not a bug, it is a data breach.
2. **Sending affects a customer's real-world reputation.** Ignoring a daily
   quota, re-mailing an unsubscribed address, or mis-classifying a bounce gets
   a sending domain blocklisted. There is no "just retry" for that.

## Hard requirements

- **PHP 8.4+.** The lock file pins Symfony 8. `composer.json` says `^8.4`
  for a reason — do not lower it.
- **Read `docs/README.md` first.** It is a *locked* decision log: row-scoped
  tenancy, Blade + Alpine + Tailwind 4, PostgreSQL 17, Redis 7, UUID v7 primary
  keys. Reopening one needs a decision record, not a quiet contradiction.
- **Verify on PostgreSQL.** SQLite's loose typing accepts a `bigint → uuid`
  foreign key that PostgreSQL rejects outright. This exact defect shipped once.

## Before you are done

```bash
composer lint      # Pint
composer analyse   # PHPStan level 6 — must stay at zero errors
composer test      # Pest
npm run build      # if resources/ changed
```

`composer check` runs all three. CI runs the same plus a PostgreSQL 17
migrate/rollback job and a dependency audit.

## Rules that exist because they were broken before

| Rule | What went wrong |
| --- | --- |
| Tenant-scope every `exists:` / `unique:` rule | A bare `exists:contact_lists,id` let a request attach a contact to another workspace's list. |
| Resolve models through the scoped query, then `authorize()` | There were no policies at all; any member could do anything. |
| Use `TenantContext::run()`, never `set()` / `set(null)` | The manual pair leaked the tenant on any exception — and under Octane the container survives the request. |
| Never `config()->set()` credentials without restoring | Per-tenant SMTP credentials persisted in the shared config between jobs. |
| `Mail::fake()` does not render a mailable | A fatal in `CampaignEmail::content()` passed the whole suite. Render mailables in at least one test. |
| Don't call `env()` outside `config/` | It returns `null` after `config:cache`. An architecture test now enforces this. |
| `:attr="…"` on a Blade component is PHP | An Alpine binding written that way fatals the page. Escape it as `::attr`. |
| Model casts already encrypt | Encrypting again at the call site stored double-wrapped ciphertext. |
| No UTF-8 BOMs | A BOM before `<!DOCTYPE` breaks rendering. `.gitattributes` enforces LF. |
| Lifecycle email goes through `LifecycleMessenger::sendOnce()`, never `Notification::send()` | The unique claim in `lifecycle_messages` is the only thing stopping a duplicate "your card was declined" — which reads as a second decline. |
| An onboarding step must assert against real state, never a toast | The first-run wizard faked three successes with `setTimeout` and persisted nothing; new customers were congratulated and then could not send. |
| An operator setting must be read by something | "Allow new signups" was written to the database and honoured by nothing; `platform.name` had no config key to override. |
| A security-relevant account change must notify the account | Email, password and two-factor changes were silent — the exact recipe for account takeover. An email change warns the *old* address. |
| Never delete customer data to enforce a limit | A downgrade leaves a workspace over its ceiling. Surface it; refuse additions; never trim. |
| A price or product name shown to a customer must come from the row an operator edits | The marketing page hardcoded a "$79 lifetime" plan that did not exist, and contradicted the schema.org offers on the same page. |
| A field the code reads must have a field the customer can write | `settings.country` drove the billing currency and had no input anywhere, so every workspace was invoiced in a currency Paystack could not charge. |

## Layout

| Path | What lives there |
| --- | --- |
| `app/Tenancy/` | Tenant context, global scope, UUID v7, audit trail, cache namespacing |
| `app/Policies/` | Isolation + RBAC; `TenantResourcePolicy` is the base |
| `app/Services/` | Domain logic (SMTP pool, CSV import, bounce classification, metrics) |
| `app/Support/` | Small stateless helpers (signed links, redirect allow-list) |
| `app/Lifecycle/` | Exactly-once customer messaging (`LifecycleMessenger`) |
| `app/Notifications/Security/` | Mandatory account-security alerts; never opt-out-able |
| `app/Notifications/Lifecycle/` | The thirteen lifecycle emails; content is data, one shared template |
| `app/Jobs/` | Queued work; every job declares its lane (`high` / `default` / `low`) |
| `tests/Feature/Regression/` | One file per defect that reached `main`; the header explains the original failure |
| `tests/Architecture/` | Rules that encode the table above |

## Local commands

```bash
make setup     # install, migrate, seed, build
make dev       # dev server + Vite watcher
make check     # everything CI runs
make queue     # a worker across all lanes
make fresh     # rebuild the database with two seeded workspaces
```

The seeder creates **two** workspaces on purpose: with only one tenant in the
database, a missing `where tenant_id = ?` looks exactly like a correct query.

Sign in as `demo@elitesender.app` or `rival@elitesender.app`, password
`password`.

## Style

- Fully typed: parameters, returns, and generics on collections and relations.
- Comments explain *why*, never *what*. A comment restating the next line is
  noise; one recording why the obvious implementation is wrong earns its place.
- Conventional Commits, with the reasoning in the body.
