# 01 — Product Requirements Document (PRD)

**Status:** Draft | **Owner:** Product | **Last decision sync:** See [README Decision Log](README.md#decision-log)

---

## 1. Vision

**EliteSender is deliverability infrastructure for teams who send.**

Bring-your-own-SMTP campaign sending with multi-provider rotation, automatic bounce suppression, and real-time analytics — wrapped in an experience so frictionless that sending your first campaign takes under five minutes, no tutorial required.

**What we are NOT:** a spam tool. specimen1's "bypass spam filters" positioning is dead. We sell deliverability discipline: rotation, suppression hygiene, reputation protection.

---

## 2. Problem Statement

- Small teams and agencies outgrow single-SMTP sending fast: one provider throttles or suspends them and their entire operation stops.
- Existing multi-SMTP tools are either self-hosted scripts (specimen1: single-tenant, insecure, unmaintainable) or enterprise platforms priced for companies 100× their size.
- Bounce handling is manual everywhere below the enterprise tier — lists decay, sender reputation collapses, deliverability drops silently.

**The painful truth:** sending email at scale is an operations problem disguised as a marketing problem. EliteSender solves the operations.

---

## 3. Target Personas

| Persona | Context | Core Need | Plan Fit |
|---------|---------|-----------|----------|
| **SaaS Founder** | Sends onboarding/lifecycle campaigns from their own SES/SMTP | Reliable sending, zero ops, cheap start | Free → Starter |
| **Agency Operator** | Manages campaigns for 5–20 clients | Multi-SMTP rotation, per-client workspaces, suppression hygiene | Growth → Scale |
| **E-commerce Operator** | Promotional blasts to 10k–100k lists | High throughput, bounce protection, click analytics | Growth → Scale |
| **Newsletter Publisher** | Weekly sends to engaged list | Deliverability consistency, open/click tracking | Starter → Growth |

---

## 4. UX Philosophy — The $1M Bar

Non-negotiable design principles. Every screen, every flow, every word is judged against these.

### 4.1 Less-is-more copy
- Every word earns its place. No jargon. Action verbs.
- Button labels say what happens: "Send test" not "Execute delivery verification".
- Settings explain themselves in one line or don't ship.

### 4.2 Zero-tutorial onboarding
- Progressive disclosure: show the next step, nothing else.
- Smart defaults everywhere (port 587 + TLS preselected, throttling preconfigured).
- Contextual micro-copy at the exact moment of doubt — never a docs link as the first answer.
- **Target: signup → first test email sent in < 5 minutes.**

### 4.3 Clean empty states
- Every empty state is an invitation, not a dead end.
- Pattern: custom SVG illustration + one sentence + one primary CTA.
- Examples:
  - Campaigns: *"No campaigns yet"* → **[Create your first campaign]**
  - Contacts: *"Your list is empty"* → **[Import contacts]**
  - SMTP: *"Connect a sender to start"* → **[Add SMTP account]**

### 4.4 Guided UI
- Inline validation on blur — errors at the field, not in a banner after submit.
- Success micro-animations (checkmark draw, subtle scale) on completions.
- Skeleton shimmer loaders on every data fetch — **never spinners**.
- Optimistic UI for mutations (toggle, rename, archive): update instantly, reconcile in background.
- Toast system: success auto-dismisses in 3s; errors persist until dismissed; max 3 stacked.

### 4.5 Mobile-first app-feel
- Touch targets ≥ 44px.
- Bottom navigation on mobile: Dashboard · Campaigns · Contacts · Settings.
- Swipe gestures on list rows (archive/delete).
- Pull-to-refresh on dashboard and lists.
- 150ms ease-out transitions; no jank, no layout shift (CLS < 0.05).

### 4.6 Premium aesthetics
- 8px spacing grid, strictly applied.
- Typography: Inter (UI), Geist Mono (stats/code) — system-fallback safe.
- Color: slate neutrals, indigo brand, emerald success, rose danger.
- Elevation: 3-level shadow system (`sm` cards, `md` popovers, `lg` modals).
- Dark mode: `dark:` variants everywhere, system-preference default, manual toggle persisted per user.

---

## 5. Core Features (v1)

Feature parity with specimen1's real value, rebuilt as multi-tenant SaaS.

### 5.1 Onboarding Wizard (3 steps)
1. **Connect SMTP** — host/port/credentials → live connection test → done.
2. **Import contacts** — CSV upload → column mapping → validation summary.
3. **Send first campaign** — subject + body → test send → schedule/send.
- Progress persisted; user can leave and resume.
- `GET /api/v1/onboarding/status` drives the checklist UI.

### 5.2 Campaign Builder
- WYSIWYG editor + raw HTML toggle + plain-text part.
- **Spin syntax** `{a|b|c}` with live preview of N variations.
- Device preview (desktop/mobile) before send.
- Attachments via R2 (plan-gated size limits).
- Test send to any address.
- Status lifecycle: `draft → queued → sending → paused/completed/failed`.

### 5.3 SMTP Pool Management
- CRUD with connection test on save.
- **Health score** (0–100): rolling success/failure, throttling detection, daily-limit proximity.
- **Rotation strategies**: round-robin, weighted-by-health, least-used-today.
- **Auto-failover**: account exceeding error threshold is paused; pool continues.
- Per-account daily limits with midnight reset (tenant timezone).

### 5.4 Contact Management
- CSV import (async queue job, row-level error report, progress bar).
- Column mapping UI (email required, name/custom fields optional → `metadata` JSONB).
- Lists/segments (static lists v1; dynamic segments v2).
- **Auto-suppression**: hard bounces, complaints, unsubscribes excluded from all future sends.
- Export (async, download link expires 24h).

### 5.5 Bounce Processing
- IMAP bounce mailboxes (CRUD + "check now").
- Scheduled polling (every 15 min per tenant, queue job).
- Classification: hard / soft / complaint → auto-suppress hard+complaint, increment soft counter (suppress at 3).
- Raw bounce messages archived to R2; metadata in `bounce_logs`.

### 5.6 Analytics
- Real-time open/click tracking (Redis-buffered counters → batched PG writes).
- Campaign stats: sent, delivered, opens (unique/total), clicks, bounces, complaints.
- **Deliverability score** per tenant: bounce rate, complaint rate, list hygiene.
- Dashboard: 30-day trend, top campaigns, recent events feed.

### 5.7 Team & Billing
- RBAC: owner / admin / editor / viewer (see [Security doc](06-SECURITY-COMPLIANCE.md#rbac)).
- Stripe Cashier subscriptions, plan enforcement at middleware level.
- Usage meter: emails sent this cycle vs plan limit, visible in nav.
- Self-serve: upgrade/downgrade/cancel, invoice history, Stripe portal.

---

## 6. Pricing (locked)

| Tier | Price | Emails/mo | SMTP | Contacts | Users | Extras |
|------|-------|-----------|------|----------|-------|--------|
| Free | $0 | 100 | 1 | 500 | 1 | Basic analytics |
| Starter | $15/mo | 5,000 | 3 | 5,000 | 2 | Bounce processing |
| Growth | $59/mo | 25,000 | 10 | 25,000 | 5 | Full analytics, API access |
| Scale | $159/mo | 100,000 | 25 | 100,000 | Unlimited | Priority queue, webhooks |
| Enterprise | Custom | Unlimited | Unlimited | Unlimited | Unlimited | SSO, SLA, dedicated support |

Over-limit behavior: sends pause gracefully with upgrade prompt (never hard-fail mid-campaign without notice).

---

## 7. Success Metrics

| Metric | Target |
|--------|--------|
| Time to first test send | < 5 min |
| Onboarding completion rate | > 70% |
| Platform deliverability (delivered/sent) | > 95% |
| Free → paid conversion | > 5% |
| NPS | ≥ 50 |
| Lighthouse (PWA/Perf/A11y) | ≥ 95 / ≥ 90 / ≥ 95 |
| Support tickets per 100 active users | < 2/mo |

---

## 8. Non-Goals (v1)

- No transactional email API (v2)
- No automation/drip flows (v2)
- No built-in ESP — BYO-SMTP only, always
- No dynamic segments (v2)
- No template marketplace (v2)
- No white-label (v3)

---

## 9. Future Roadmap (pre-architected, see Architecture doc)

| Version | Feature | Architectural readiness |
|---------|---------|------------------------|
| v2 | Automation flows (trigger sequences) | Domain events already emitted on all mutations |
| v2 | Transactional sending API | Same queue/pool pipeline, new endpoint + key type |
| v2 | Inbound webhooks from ESPs | `webhook_endpoints` table + HMAC verification ready |
| v2 | Dynamic segments | `metadata` JSONB on contacts + query builder |
| v2 | Template marketplace | Campaign body storage already abstracted |
| v3 | AI subject-line optimizer | A/B fields in campaigns schema |
| v3 | White-label for agencies | Tenant `settings` JSONB branding keys reserved |
| v3 | Native apps (Capacitor wrapper) | PWA from day 1 = 90% of the work done |
| v3 | Inbox-placement testing | Seed-list module, new bounded context |

---

## 10. Key User Stories

**Onboarding**
1. As a new user, I sign up with email+password and land in the wizard, so I never see an empty dashboard wondering what to do.
2. As a new user, I paste my SMTP credentials and see a live "Connected ✓" within seconds, so I trust the system before importing anything.
3. As a new user, I upload a CSV and map columns visually, so import errors never surprise me after the fact.

**Campaigns**
4. As a sender, I write one subject with spin syntax and preview 5 variations, so my sends look human.
5. As a sender, I send a test to myself before launching, so broken HTML never reaches my list.
6. As a sender, I pause a sending campaign in one click, so a mistake never completes.
7. As an agency operator, I duplicate a campaign across client workspaces, so I don't rebuild proven sends.

**SMTP Pool**
8. As a sender, I add 3 SMTP accounts and the pool rotates automatically, so one provider's throttle never stops me.
9. As a sender, I see a health score per account, so I know which provider is degrading before it fails.

**Hygiene**
10. As a sender, hard bounces are suppressed automatically, so my list stays clean with zero manual work.
11. As a sender, I see why each address was suppressed, so I can fix data quality at the source.

**Analytics**
12. As a sender, I watch opens arrive in real time during a send, so I know immediately if engagement is normal.
13. As an admin, I see my deliverability score with one-line explanations, so I know exactly what to fix.

**Billing**
14. As an owner, I see my usage vs limit in the nav bar, so overage never surprises me.
15. As an owner, I upgrade mid-cycle and the new limit applies instantly, so a growth moment is never blocked.
