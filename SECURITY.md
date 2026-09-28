# Security Policy

EliteSender sends mail on behalf of its customers and stores their audience
data. A defect here is not an inconvenience — it is someone else's reputation,
inbox placement and personal data. We treat security reports accordingly.

## Reporting a vulnerability

**Do not open a public issue.**

Email **security@elitesender.app** with:

- a description of the issue and its impact,
- the steps (or a proof of concept) needed to reproduce it,
- the affected version, commit or environment.

You will receive an acknowledgement within **2 business days** and a
remediation plan within **10 business days**. We will credit you in the
changelog unless you ask us not to.

Please give us a reasonable window to ship a fix before any public disclosure.

## Supported versions

Only the current `main` branch and the currently deployed production release
receive security fixes.

## Security posture

| Control | Implementation |
| --- | --- |
| Tenant isolation | Row-scoped `tenant_id` + Eloquent global scope (`App\Tenancy\HasTenant`), re-checked by policies in `app/Policies` |
| Tenant context | `ResolveTenant` on both the web and API stacks; restored in a `finally` so it cannot leak between Octane requests |
| Cache isolation | `App\Tenancy\TenantCache` namespaces the cache store per workspace |
| Authentication | Laravel Fortify: password, TOTP two-factor, passkeys (WebAuthn) |
| Authorization | Role hierarchy owner → admin → member → viewer, enforced by policies and Form Requests |
| Transport | `ForceHttps` + HSTS (2 years, `includeSubDomains`, `preload`) |
| Response headers | CSP with a per-request nonce, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` |
| Secrets at rest | SMTP credentials encrypted with Laravel's `encrypted` cast (AES-256); never serialised by the API |
| Audit trail | Immutable `audit_logs` (no update/delete on the model), values truncated and secret fields stripped, 24-month retention |
| Suppression list | Addresses stored as a keyed SHA-256 HMAC so opt-outs survive GDPR erasure without retaining personal data |
| Open redirect / SSRF | `App\Support\SafeRedirect` rejects non-HTTP schemes, credentials in URLs, loopback, private and link-local ranges, and hostnames that resolve into private space |
| Unsubscribe integrity | Signed URLs (`App\Support\UnsubscribeLink`); GET only confirms, POST mutates |
| Rate limiting | Per-tenant plan-tiered API limits plus dedicated limiters for tracking, imports and dispatch |
| Dependency hygiene | `composer audit` and `npm audit` in CI; Dependabot for weekly updates |

## Known gaps

Tracked openly rather than hidden:

- The API has no scoped tokens yet — a Sanctum token currently carries the
  full permissions of its owner.
- There is no per-tenant encryption key; all tenants share `APP_KEY`.
- Automations are stored but not yet executed by a runtime engine.
