# Launch Readiness — first production release

Status of the pre-launch QA, security and lifecycle review. Everything listed
as **fixed** has a regression test named after the defect; everything listed
as **open** is a deliberate decision with a reason.

---

## Quality gates

| Gate | Command | Result |
| --- | --- | --- |
| Code style | `composer lint` | pass — 281 files |
| Static analysis | `composer analyse` (PHPStan 6) | pass — 0 errors |
| Tests | `composer test` | **632 passing**, 2 skipped, 1,786 assertions |
| Front-end build | `npm run build` | pass, deterministic |
| Schema | migrate + rollback + re-migrate | pass |
| Production image | `docker build .` | now built in CI |

---

## P0 — security

### Tenant isolation was absent on the public API

`ResolveTenant` asked `auth('web')` for the acting user. A Bearer-token
request has no session, so that returned `null`, no tenant was bound, and the
`HasTenant` global scope silently became inert.

Reproduced live: a token belonging to one workspace returned **every**
workspace's contacts, campaigns, lists, and the hostnames and quotas of other
customers' SMTP relays. `POST /api/v1/contacts` also wrote rows with a null
`tenant_id`.

Fixed by resolving the acting user from whichever tenant-facing guard proved
the request (`web`, then `sanctum`), type-checked so an operator on the
`admin` guard can never satisfy it. `tests/Feature/Regression/ApiTenantScopeTest.php`
fails 8 of 9 cases against the old code.

### Other security fixes

| Defect | Consequence | Fix |
| --- | --- | --- |
| Test-send registered a mailer and never removed it, contradicting its own docblock | One click left a customer's SMTP username and password in the shared config for the life of an Octane worker | Register/forget pair in a `finally` |
| `X-Powered-By` removed from the response bag only | PHP emits it from the SAPI, so `PHP/8.4.x` shipped on every response regardless | `header_remove()` as well |
| Import status keyed only by import id | Isolation rested on cache prefixing, which silently no-ops on the array and file stores | Tenant is part of the key |
| `ImportContactsJob` used `TenantContext::set()` / `set(null)` | Clears the caller's context instead of restoring it — the exact pattern AGENTS.md forbids | `run()`, plus an architecture test that fails the build if it returns |
| Payment currency never compared to invoice currency | ₦1,000 and $10.00 are both `1000` minor units by the time `settle()` compares them | Refused, logged, and surfaced as 422 rather than a retry storm |

---

## P1 — correctness, money, deliverability

| Defect | Consequence | Fix |
| --- | --- | --- |
| Quota exhaustion mid-chunk called `release()` | Replays the **whole** chunk — everyone already delivered receives the campaign twice | Remainder re-dispatched as a new job |
| `FinaliseCampaigns` required `sent >= recipients` | One suppressed address made that unreachable; campaigns showed "Sending" forever — the exact ticket the command exists to prevent | `sent + failed + skipped`, with a new `skipped_count` |
| No plan enforcement in the send workers | A large list sent, and billed, past the monthly allowance | Workers stop at the allowance and pause the campaign |
| Automation email rendered with `MergeTags::sampleData()` | Subscribers with no first name were greeted, in a real inbox, as **"Ada"**, with `[Email]` resolving to `ada@example.com` | Real contact data only |
| Automation email had no visible unsubscribe link | CAN-SPAM expects one in the body, not only a header | Shared footer with the broadcast path |
| Automation email had no tracking | Journey sends were invisible in reporting while the docs claimed parity | Shared `CampaignTracking` service |
| API rate limits read `settings.plan`, which nothing writes | Every workspace got the same limit while the 429 body advised upgrading for headroom that upgrading could not deliver | Read from the subscription |
| "Total Sent" read a `stats_cache` key nothing writes | Untouched drafts reported five-figure sends; open rates divided by audience size, not deliveries | Real `sent_count`; also removes an N+1 on the index |
| Unresolved `[brackets]` were deleted | `[URGENT]`, `[Webinar]`, `[New]` vanished silently between composer and inbox | Known tags resolve, author copy survives |
| Operator health page listed no scheduled tasks | `routes/console.php` loads via `afterResolving(ConsoleKernel)`, which a web request never triggers — the one screen that confirms cron is alive asserted it was not | Bootstrap the console kernel before reading the schedule |

---

## P2 — build and deploy

- **The Docker assets stage could not see `vendor/`**, which
  `resources/css/app.css` declares as a Tailwind `@source`. Pagination
  shipped to production with its classes tree-shaken away — silently, because
  Tailwind does not warn about an unresolvable `@source`. The stage now copies
  the pagination views in, and the build **fails** if the classes are missing.
- **Builds were not reproducible**: `@source storage/framework/views` meant
  the CSS depended on which pages happened to have been rendered on the build
  machine. Removed.
- CI now installs Composer dependencies before building assets, asserts the
  stylesheet is complete, and builds the production image.

---

## The customer lifecycle

Before this work the product could take money but could not talk to anyone.
Outside password reset, the only email it had ever sent was a customer's own
campaign.

### Activation

**The first-run wizard was a mock.** `testSmtp()` waited one second and
toasted "SMTP Connected Successfully". `importContacts()` waited 1.5 seconds
and toasted "Contacts imported". Neither issued a single request. A new
customer was congratulated three times and landed on an empty dashboard,
unable to send, with no idea why.

Replaced with `App\Services\ActivationChecklist`: five steps, each derived
from real workspace state, surfaced both on the onboarding page and as the
dashboard's lead card until the first campaign goes out.

### Email verification — a send gate, never a login wall

This product sends on a customer's behalf, so an account opened with an
unverified address is how IP ranges get blocklisted. Verification is enforced
at `PlanGate::sendBlockReason()` — the single choke point every send path
already passes through. The whole product stays open to an unverified owner;
only the send button waits, with a one-click resend on every page.

### The messages

Event-driven, from `BillingService`: welcome, invoice issued, payment
received, payment failed (per attempt), suspended, reinstated, cancelled.

Time-driven, from `elitesender:lifecycle` (hourly, `--dry-run` supported):
trial ending, abandoned checkout, invoice due, renewal reminder, suspension
warning, usage at 80% and 100%, win-back.

| Moment | What now happens |
| --- | --- |
| Trial ending | 3 days' notice, leading with what they have built |
| Checkout abandoned | Day 1, with a direct link back to the invoice; day 3 as a due-soon nudge; never a third |
| Renewal | 3 days' notice of the exact amount and card — what card-network rules expect, and the cheapest chargeback prevention available |
| Card declined | Named, escalating, with the next retry date. Most failed renewals are an expired card: a thirty-second fix, if somebody says so |
| Before suspension | 2 days' notice with the exact date and, in bold, that nothing is deleted |
| Suspension | Announced; a suspended workspace gets a page explaining itself, not a bare 403 |
| Payment lands | Receipt, and the all-clear if they were suspended |
| 80% of allowance | Warned early rather than blocked late — the difference between an upgrade and a complaint |
| Cancellation | Reason captured against a fixed vocabulary, confirmed in writing, one-click undo, one win-back a week later |

### Two properties enforced by construction

- **Exactly once.** Every message is claimed by a unique insert into
  `lifecycle_messages` *before* it is sent. Concurrent schedulers, an hourly
  cadence and an accidental re-run all produce one email.
- **Never fatal.** `LifecycleMessenger` reports and swallows its own
  failures. A mail outage cannot roll back a settled payment or stop a
  suspension from applying.

Operators see exactly what a workspace was told, and when, on the tenant page
— because "did they get the warning?" is the first question support asks
about any billing complaint.

### Dead operator controls, now live

"Allow new signups" was written to the database and honoured by nothing.
`platform.name` and `platform.support_email` had no `config/platform.php` to
override, so both resolved to null wherever they were read. All three now work.

---

## Second pass — the lifecycle gaps that survived the first

### Seats were sold and could not be filled

Every plan advertised team members (Free 1 → Scale 25, Enterprise
unlimited), the billing page rendered a usage meter against that limit, and
the plan cards listed it as a headline feature. There was no route, no
controller and no view. A Growth customer paid $59 a month for nine seats
that could not exist, and the limit was never enforced because there was
nothing to enforce it against.

Built: invitations (SHA-256 hashed, single-use, 7-day expiry), role
management, revoke, resend, and seat accounting that counts **pending
invitations** — otherwise a three-seat workspace can issue thirty that each
pass the check individually.

Offboarding is treated as a security event: removing a member revokes their
sessions and API tokens and notifies them. The last owner cannot be removed
or demoted, and nobody can demote themselves — both are states you can only
escape with a support ticket.

### Three features that only pretended to work

| Feature | What it actually did | Consequence |
| --- | --- | --- |
| First-run wizard | `setTimeout` then "SMTP Connected Successfully" / "Contacts imported" | Fixed in pass one |
| SMTP "Test connection" | `setTimeout(1500)` then "Connection test successful!" | Customers learned the truth when their first campaign silently failed, with the relay already in rotation |
| IMAP settings form | No `action`; button fired "IMAP Settings saved successfully" | `ScanBounces` skipped every workspace on every 15-minute run — the entire Bounce Shield feature was unreachable while claiming to be configured |

All three are now real. An architecture test fails the build on any
client-side success toast, because a success message is a statement about
server state and this codebase made that same mistake three times.

### Revenue leaks closed

| Leak | Fix |
| --- | --- |
| Signed up, never activated — one welcome email then silence forever | Two nudges (day 2, day 6) naming the single blocking step, both stopping the moment it is done |
| Card expiry discarded by both gateways | Captured on first charge, warned 14 days out. Involuntary churn is the cheapest kind to prevent and the most infuriating to lose |
| Nothing to come back for | A performance report when a campaign finishes — the retention loop for a product whose value is measurement |
| No opt-out on non-transactional mail | Setup nudges and reports are opt-out; billing and security are mandatory by construction |
| Verification and reset emails looked like a different product | Both now use the product's own shell |

---

## Third pass — security, the plan ceiling, and leaving

### Account changes happened in silence

Changing the email, changing the password and switching off two-factor
produced no notification at all. That is the standard account-takeover
recipe: obtain a session, change the address, reset the password to one you
control. Every step is a legitimate action by an authenticated user, so
nothing else in the stack objects.

An email change now warns the **old** address — the only inbox the real
owner still controls — and password changes, resets and two-factor changes
all confirm to the account. None of them are opt-out-able.

### A downgrade left customers above the ceiling, silently

Dropping from Growth to Free leaves ten seats on a one-seat plan and twenty
thousand contacts on a five-hundred plan. Nothing deleted the excess, which
is right — silently destroying a contact list to fit a cheaper plan is the
one unforgivable behaviour here — but nothing *said* anything either, so the
limit was met as a silent refusal months later.

`PlanGate::overages()` now names exactly what is over and by how much; it
appears on every page and in one email a month, and leads with the promise
that nothing will be deleted.

### Leaving was a favour, not a right

GDPR Articles 15 and 17 were an email to an operator. Both are now buttons.

- **Export** streams a zip of contacts, lists, campaigns, engagement, team
  and relay configuration as UTF-8 CSV with a BOM. Credentials are excluded
  by design — an export travels through a mail server, a downloads folder, a
  laptop. Served behind the session, never a public link, shredded after
  seven days.
- **Deletion** is owner-only, re-authenticated, requires typing the
  workspace name, and is *scheduled* with a seven-day window that one click
  cancels. A cooling-off period turns an angry Friday click into a Monday
  decision.
- **Suppression survives erasure.** Those rows are one-way hashes with no
  addresses in them, and they exist because a recipient asked never to be
  emailed again. That promise was made to them, not to the workspace, so
  they are detached rather than cascaded.

---

## Open, deliberately

1. **No scoped API tokens** — a Sanctum token carries its owner's full rights.
2. **No per-tenant encryption key** — all workspaces share `APP_KEY`.
3. **`'unsafe-eval'` in the CSP** — Alpine compiles `x-*` at runtime.
4. **Automation branching is linear** — a false `condition` ends the journey.
5. **A worker-paused campaign cannot be resumed from the UI.** Re-dispatching
   would re-fan-out the list and double-send everyone already delivered. A
   per-recipient delivery ledger (or `Bus::batch`) closes this properly.
6. **Automation sends record no `CampaignEvent`**, so automation open rates
   use the broadcast denominator.
7. **One account belongs to one workspace.** `users.email` is globally
   unique, so the same person cannot be a member of two workspaces. Agencies
   will hit this; fixing it means a join table and a workspace switcher.
8. **No "new sign-in from an unrecognised device" alert.** The other
   security events are covered; this one needs device fingerprinting and a
   trusted-device store to avoid alerting on every browser restart.
9. **Annual billing is not offered.** `plans.interval` exists and is unused.
   Annual plans are the standard lever for both conversion and retention,
   but they need proration across intervals and renewal maths that deserve
   their own pass.

---

## Before you turn it on

1. **`TRUSTED_PROXIES`** still defaults to `*`. Narrow it to your
   load-balancer range the moment you know it — `*` lets a client forge
   `X-Forwarded-For` and walk past every IP-keyed rate limit.
2. **Run the scheduler.** Without it nothing renews, nothing is warned, and
   no campaign leaves "Sending". The console's heartbeat exists to catch this.
3. **Set `PLATFORM_SUPPORT_EMAIL`.** Every lifecycle email points at it.
4. **Verify the webhook secrets** for Paystack and Stripe; the signature is
   the entire security boundary on those endpoints.
5. **Leave `LIFECYCLE_ENABLED=true`.** Off means customers are never warned
   before they are charged or suspended.
