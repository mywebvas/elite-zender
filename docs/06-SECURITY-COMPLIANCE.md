# 06 — Security & Compliance

**Status:** Draft | **Posture:** Bank-grade from day 1 — not a roadmap item, a launch gate.

---

## 1. Security Checklist (launch gate — all must be ✅)

### Data protection
- [x] Encryption at rest: Railway-managed PG volume encryption; R2/B2 server-side encryption
- [x] TLS 1.3 in transit everywhere; HSTS `max-age=63072000; includeSubDomains; preload`
- [x] SMTP/IMAP credentials encrypted via Laravel `Crypt` (AES-256-GCM, `APP_KEY`) — never stored or logged plaintext, never returned by API (write-only fields)
- [x] Suppression lists store `email_hash` (SHA-256) — never plaintext emails
- [x] Secrets only in Railway env vars / local `.env` — never in git, never in logs
- [x] `APP_KEY` rotation runbook (re-encrypt command for credential fields)

### Identity & access
- [x] Password hashing: Argon2id (bcrypt fallback per PHP defaults)
- [x] Password policy: ≥12 chars, breached-password check (HaveIBeenPwned k-anonymity API)
- [x] 2FA via TOTP (Fortify) — optional v1, enforced for owner role v2
- [x] Sessions: HTTP-only + Secure + SameSite=Lax cookies, 30-min idle timeout, regenerate on login
- [x] API tokens: hashed at rest (Sanctum), scoped abilities, per-token last-used tracking
- [x] Login throttling: 5 attempts/min/IP, exponential lockout, alert on distributed attempts

### RBAC
| Capability | Owner | Admin | Editor | Viewer |
|-----------|:-----:|:-----:|:------:|:------:|
| Billing, delete tenant | ✅ | | | |
| Manage users, SMTP accounts | ✅ | ✅ | | |
| Campaigns, contacts, lists CRUD | ✅ | ✅ | ✅ | |
| Send/pause campaigns | ✅ | ✅ | ✅ | |
| View analytics, exports | ✅ | ✅ | ✅ | ✅ |

Enforced via policies + middleware; tested per endpoint (see Coding Standards §tenant-isolation suite).

### Application security
- [x] CSRF on all state-changing routes (Laravel built-in)
- [x] XSS: Blade auto-escaping everywhere; `{!! !!}` forbidden in review; CSP `default-src 'self'`
- [x] SQL injection: Eloquent parameterized only — raw SQL banned by static analysis (PHPStan rule)
- [x] Mass assignment: `$fillable` whitelists only; `$guarded = []` forbidden
- [x] Security headers: CSP, HSTS, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` minimal
- [x] Rate limiting per plan (see API Contract §3) + per-IP pre-auth
- [x] Idempotency-Key dedupe on mutations — replay-safe
- [x] Tracking URLs HMAC-signed — no open-redirect or pixel forgery
- [x] File uploads (CSV, attachments): MIME sniffing, size caps per plan, stored in R2 (never web root), served via signed URLs
- [x] SSRF guard: IMAP/SMTP hosts validated against private-IP blocklists (RFC 1918, link-local, metadata IPs)

### Audit
- [x] `audit_logs` append-only: every mutation (who, what, before/after diff, IP)
- [x] No update/delete on audit table — DB-level permissions
- [x] 24-month retention → B2 archive

---

## 2. Tenant Isolation (the crown jewel)

| Layer | Mechanism | Failure mode prevented |
|-------|-----------|------------------------|
| Middleware | `ResolveTenant` binds context before controllers | Unscoped request processing |
| Eloquent | `HasTenant` global scope on every tenant model | Cross-tenant reads/writes — returns 404 |
| Redis | Per-tenant prefix `t:{short}:` set by middleware | Cache/lock/rate-limit bleed |
| Queue | `tenant_id` in payload; worker re-binds + verifies match | Cross-tenant job execution |
| Routes | Implicit model binding resolves through scope | IDOR via UUID guessing |
| Lazy loading | `Model::preventLazyLoading()` globally | Scope-bypass via unscoped lazy relations |
| **Tests** | Automated suite attempts cross-tenant access on **every model + every endpoint** | Regressions — runs in pre-commit + CI |

**Pentest checklist (pre-launch + annually):**
- [ ] IDOR: swap UUIDs across two test tenants on every GET/PATCH/DELETE
- [ ] Scope bypass: `?tenant_id=` injection, relation traversal (`campaign.contact.list`), API token from tenant A against tenant B routes
- [ ] Queue poisoning: forged job payload with mismatched tenant_id (worker must reject)
- [ ] Cache collision: verify prefix enforcement under concurrent tenants
- [ ] Tracking forgery: tampered HMAC signatures on `/t/*` routes

---

## 3. Redis Security

| Control | Detail |
|---------|--------|
| Unix socket only (local) | `port 0`, socket file perms `770` — no network surface |
| TCP on Railway | Private network only, `requirepass` set (defense in depth) |
| Dangerous commands disabled | `rename-command FLUSHALL ""`, `rename-command KEYS ""`, `rename-command CONFIG ""` in production |
| Tenant prefix enforcement | `TenantAwareRedis` middleware — unprefixable from app code |
| TTL mandatory | No orphan keys; static analysis flags `Cache::forever` outside `global:` namespace |

---

## 4. Compliance

### CAN-SPAM (US)
- [x] One-click unsubscribe auto-injected into every campaign (RFC 8058 header + footer link) — cannot be disabled
- [x] Tenant physical mailing address required before first send (onboarding gate)
- [x] Opt-outs honored immediately (suppression write is synchronous), well under the 10-day legal window
- [x] No false/misleading header enforcement: `from_email` must belong to a verified SMTP account

### GDPR (EU)
- [x] Lawful basis recorded per contact (`metadata.consent` with source + timestamp)
- [x] Right to access: full tenant data export (async job → signed R2 URL)
- [x] Right to erasure: contact PII deleted; `email_hash` retained in suppression (legitimate interest: honoring opt-outs) — documented in privacy policy
- [x] DPA template available for tenants (processors)
- [x] Data processors inventory: Railway (hosting), Stripe (billing), R2/B2 (storage), Sentry (errors, PII-scrubbed)

### CCPA (California)
- [x] "Do not sell" — we never sell data; stated in privacy policy
- [x] Disclosure + deletion requests via support runbook (30-day SLA)

### Anti-abuse (platform protection)
- [x] Sending velocity limits per plan (hard caps, cannot be disabled by tenant)
- [x] Content scanning: phishing URL heuristics, deceptive-subject flags on campaign save (warn, not block, v1)
- [x] New-tenant warm-up: Free tier capped at 100/mo until first successful bounce cycle processed
- [x] Bounce-rate circuit breaker: tenant-level >8% hard bounce rate → sending paused + review notice
- [x] `abuse@` intake webhook → auto-ticket + tenant suspension workflow
- [x] Disposable-domain detection on registration (blocklist)

---

## 5. specimen1 Security Debt — Purge List

| Item | Risk | Action |
|------|------|--------|
| `elite_core_unlocked.php` — hardcoded `ELITE-GOD-MODE-ACTIVE` / `Vault-Bypass` | Full license bypass; backdoor-class liability | **Deleted from all distributions; never ported; git history scrubbed before any public mirror** |
| Plaintext DB credentials in `config.php` | Credential exposure | Env vars only (Railway secrets) |
| Installer accepts any 16-char license key | Auth bypass | Replaced by Stripe subscription state |
| SQLite single-file DB | No concurrency, no access control | PostgreSQL 17 + least-privilege DB role |
| License ping to `tools.mywebvas.com` on cron | Third-party dependency + privacy leak | Removed; no external license server exists |
| Sequential `fsockopen` SMTP | Blocking, no TLS verification defaults | Symfony Mailer with enforced TLS + cert verification |
| No RBAC (single user) | Any user = full control | 4-role RBAC + policies |

---

## 6. Incident Response (one-page playbook)

1. **Detect** — Sentry alert, BetterStack downtime, abuse report, or anomaly (bounce spike, auth failures).
2. **Triage (≤15 min)** — Severity: S1 data breach/service down, S2 security-control failure, S3 limited impact.
3. **Contain** — Suspend affected tenant/keys; revoke tokens; scale worker to 0 if sending-related; snapshot evidence.
4. **Eradicate** — Patch, rotate secrets (`APP_KEY`, DB, Stripe, R2), force session/token invalidation if auth-related.
5. **Recover** — Staged redeploy, health checks, monitored ramp.
6. **Notify** — S1: affected tenants ≤72h (GDPR), plain-language postmortem. S2/S3: internal log + fix notes.
7. **Postmortem (≤5 days)** — Blameless, written, action items tracked to issues.

---

## 7. Future-Proof Notes

- **SOC 2 readiness**: audit logs, RBAC, encryption, incident playbook cover ~80% of common criteria from day 1; formal pursuit at Enterprise traction.
- **SSO/SAML**: Sanctum + pluggable guards keep Enterprise SSO an additive module.
- **IP allowlisting** (v2): per-tenant API allowlist — middleware hook reserved.
- **Data residency** (v3): tenant `settings.region` key reserved; UUID v7 + tenant_id schema makes regional sharding additive.
- **Key management**: `APP_KEY` rotation path documented now; envelope encryption (KMS) evaluated at SOC 2.
