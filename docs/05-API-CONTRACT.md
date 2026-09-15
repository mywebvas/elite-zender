# 05 — API Contract

**Status:** Draft | **Base:** `/api/v1/` | **Format:** JSON

---

## 1. Principles

- RESTful resources, plural nouns, kebab-case paths: `/api/v1/smtp-accounts`
- UUID v7 identifiers in all URLs — never sequential integers
- Versioned from day 1 (`v1`); breaking changes → `v2` namespace, never silent mutation
- Cursor pagination for collections; `limit` default 25, max 100
- All mutations accept `Idempotency-Key` header (safe retries; 24h dedupe window in Redis)
- SPA uses cookie auth (Sanctum); external clients use Bearer tokens

---

## 2. Authentication

| Method | Use |
|--------|-----|
| Sanctum SPA cookie | Blade frontend (same-origin, CSRF-protected) |
| Sanctum personal access token | API clients — `Authorization: Bearer {token}` |

Token abilities mirror roles: `owner`, `admin`, `editor`, `viewer`.

---

## 3. Rate Limiting (Redis, sliding window, tenant-prefixed)

| Plan | API requests |
|------|--------------|
| Free | 30/min |
| Starter | 60/min |
| Growth | 180/min |
| Scale | 360/min |
| Enterprise | Custom |

Pre-auth endpoints (login, register, tracking) limited per-IP via `global:rate:ip:{ip}`. Tracking endpoints (`/t/*`): 600/min per recipient ID — generous but abuse-capped.

`429` responses include `Retry-After` and `X-RateLimit-*` headers.

---

## 4. Envelopes

**Success (single):**
```json
{ "data": { "id": "0192ab3c-…", "name": "Launch Blast" }, "message": "Campaign created" }
```

**Success (collection):**
```json
{ "data": [ … ], "meta": { "next_cursor": "0192ab…", "per_page": 25 } }
```

**Error:**
```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The subject field is required.",
    "details": { "subject": ["The subject field is required."] }
  }
}
```

**Error codes:** `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `VALIDATION_FAILED`, `PLAN_LIMIT_REACHED`, `RATE_LIMITED`, `SMTP_CONNECTION_FAILED`, `IDEMPOTENCY_CONFLICT`, `SERVER_ERROR`.

---

## 5. Endpoints

### 5.1 Auth (pre-tenant)
| Method | Path | Notes |
|--------|------|-------|
| POST | `/auth/register` | creates tenant + owner atomically |
| POST | `/auth/login` | rate-limited 5/min/IP |
| POST | `/auth/logout` | |
| POST | `/auth/forgot-password` / `/auth/reset-password` | |
| POST | `/auth/email/verify` | |
| POST | `/auth/two-factor` | TOTP challenge |

### 5.2 Onboarding
| Method | Path | Notes |
|--------|------|-------|
| GET | `/onboarding/status` | `{ steps: { smtp: true, contacts: false, campaign: false } }` |
| POST | `/onboarding/complete-step` | `{ step: "contacts" }` |

### 5.3 Campaigns
| Method | Path | Role | Notes |
|--------|------|------|-------|
| GET | `/campaigns` | viewer+ | cursor paginated, `?status=` filter |
| POST | `/campaigns` | editor+ | creates draft |
| GET | `/campaigns/{id}` | viewer+ | includes `stats_cache` |
| PATCH | `/campaigns/{id}` | editor+ | drafts only |
| DELETE | `/campaigns/{id}` | editor+ | soft delete, drafts only |
| POST | `/campaigns/{id}/send` | editor+ | `{ "scheduled_at": null }` |
| POST | `/campaigns/{id}/pause` / `/resume` | editor+ | |
| POST | `/campaigns/{id}/duplicate` | editor+ | |
| POST | `/campaigns/{id}/test` | editor+ | `{ "email": "me@x.com" }` |
| GET | `/campaigns/{id}/stats` | viewer+ | real-time: PG `stats_cache` + Redis HLL counters |
| POST | `/campaigns/{id}/preview-variations` | editor+ | `{ "count": 5 }` → spin syntax expansions |

### 5.4 Contacts & Lists
| Method | Path | Notes |
|--------|------|-------|
| GET/POST | `/contacts` | list / create |
| GET/PATCH/DELETE | `/contacts/{id}` | |
| POST | `/contacts/import` | async — `{ file, mapping }` → `{ job_id }`; poll `/imports/{job_id}` |
| GET | `/contacts/export` | async — signed R2 URL, 24h expiry |
| POST | `/contacts/{id}/suppress` | manual suppression |
| GET/POST | `/lists` · GET/PATCH/DELETE `/lists/{id}` | |
| POST/DELETE | `/lists/{id}/contacts` | bulk add/remove `{ contact_ids: [] }` |
| GET | `/suppressions` | list with reason/source |

### 5.5 SMTP Accounts
| Method | Path | Notes |
|--------|------|-------|
| GET/POST | `/smtp-accounts` | |
| GET/PATCH/DELETE | `/smtp-accounts/{id}` | password write-only, never returned |
| POST | `/smtp-accounts/{id}/test` | live connection check → `{ ok, latency_ms }` |
| POST | `/smtp-accounts/{id}/pause` / `/resume` | |
| GET | `/smtp-accounts/health` | pool overview: scores, sent_today, limits |

### 5.6 Bounce Mailboxes
| Method | Path | Notes |
|--------|------|-------|
| GET/POST | `/bounce-mailboxes` | |
| PATCH/DELETE | `/bounce-mailboxes/{id}` | |
| POST | `/bounce-mailboxes/{id}/test` | IMAP connection check |
| POST | `/bounce-mailboxes/{id}/check-now` | enqueue immediate poll |
| GET | `/bounce-logs` | filtered by `type`, `date` |

### 5.7 Analytics
| Method | Path | Notes |
|--------|------|-------|
| GET | `/analytics/dashboard` | 30-day trends, usage vs plan, deliverability score |
| GET | `/analytics/campaigns/{id}/events` | cursor paginated event feed |
| GET | `/analytics/deliverability` | score + contributing factors |

### 5.8 Billing
| Method | Path | Notes |
|--------|------|-------|
| GET | `/billing/subscription` | plan, status, usage, period end |
| POST | `/billing/subscribe` | `{ price_id, payment_method }` (Stripe) |
| POST | `/billing/change-plan` | prorated via Cashier |
| POST | `/billing/cancel` / `/billing/resume` | |
| GET | `/billing/invoices` | |
| GET | `/billing/portal` | Stripe customer portal URL |
| GET | `/billing/usage` | `{ emails_sent, emails_limit, resets_at }` |

### 5.9 Settings & Team
| Method | Path | Notes |
|--------|------|-------|
| GET/PATCH | `/settings/tenant` | name, timezone, sending defaults |
| GET/POST | `/settings/users` · PATCH/DELETE `/settings/users/{id}` | admin+ |
| GET/PATCH | `/settings/notifications` | per-channel toggles |
| GET/POST | `/settings/api-tokens` · DELETE `/settings/api-tokens/{id}` | |
| GET/POST | `/settings/webhooks` · PATCH/DELETE `/settings/webhooks/{id}` | Scale plan+ |

### 5.10 Tracking (public, no auth — signed URLs)
| Method | Path | Notes |
|--------|------|-------|
| GET | `/t/o/{message_id}.gif` | open pixel — Redis only, <50ms |
| GET | `/t/c/{message_id}` | click redirect — 302 to `?url=` target |
| GET | `/t/u/{message_id}` | one-click unsubscribe (RFC 8058) |

Signed with HMAC; tampering → 404. Never touches PG synchronously (see Architecture §4.3).

### 5.11 Webhooks (outbound, Scale plan+)

Events: `campaign.completed`, `campaign.failed`, `bounce.hard`, `contact.suppressed`, `subscription.changed`.

```json
{
  "id": "evt_0192ab…",
  "type": "campaign.completed",
  "created_at": "2026-09-15T12:00:00Z",
  "data": { "campaign_id": "…", "sent": 4800, "failed": 12 }
}
```

- **Signature:** `X-EliteSender-Signature: t={ts},v1={hmac_sha256(ts.payload, secret)}`
- Retries: 5 attempts, exponential backoff (1m → 16h), then dead-letter visible in dashboard.

---

## 6. OpenAPI 3.1 Skeleton (key endpoints)

```yaml
openapi: 3.1.0
info: { title: EliteSender API, version: 1.0.0 }
servers: [{ url: https://api.elitesender.app/api/v1 }]
paths:
  /campaigns:
    post:
      summary: Create campaign draft
      security: [{ bearerAuth: [] }]
      parameters:
        - { name: Idempotency-Key, in: header, schema: { type: string } }
      requestBody:
        content:
          application/json:
            schema:
              type: object
              required: [name, subject, body_html, list_id]
              properties:
                name:      { type: string, maxLength: 120 }
                subject:   { type: string, maxLength: 200 }
                body_html: { type: string }
                body_text: { type: string }
                list_id:   { type: string, format: uuid }
      responses:
        '201': { description: Created, content: { application/json: { schema: { $ref: '#/components/schemas/Campaign' } } } }
        '422': { description: Validation failed, content: { application/json: { schema: { $ref: '#/components/schemas/Error' } } } }
  /campaigns/{id}/send:
    post:
      summary: Queue campaign for sending
      parameters: [{ name: id, in: path, required: true, schema: { type: string, format: uuid } }]
      responses:
        '202': { description: Queued }
        '409': { description: Already sending/completed }
components:
  securitySchemes:
    bearerAuth: { type: http, scheme: bearer }
  schemas:
    Campaign:
      type: object
      properties:
        id:     { type: string, format: uuid }
        name:   { type: string }
        status: { type: string, enum: [draft, queued, sending, paused, completed, failed] }
    Error:
      type: object
      properties:
        error:
          type: object
          properties:
            code: { type: string }
            message: { type: string }
            details: { type: object }
```

Full spec is generated from code (attributes/annotations) once endpoints ship — this skeleton is the contract of intent.

---

## 7. Future-Proof Notes

- **Batch endpoint** (`POST /batch`) reserved for v2 — multi-op transactional groups.
- **GraphQL gateway** (v3): resource/field naming chosen to map 1:1 from REST Resources.
- **SDK generation**: OpenAPI-first discipline keeps auto-generated TS/PHP/Python clients viable.
- **Idempotency + signed webhooks from day 1** — payment-grade API hygiene that enterprise customers will demand later.
