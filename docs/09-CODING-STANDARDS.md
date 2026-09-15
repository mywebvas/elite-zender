# 09 — Coding Standards & Conventions

**Status:** Draft | **Philosophy:** *If Laravel already does it, don't rebuild it. If a one-liner works, don't write ten.*

---

## 1. The "20-not-200" Rules

1. **No repository pattern.** Eloquent IS the data layer. Reusable constraints → query scopes.
2. **No service classes for CRUD.** Controllers do: FormRequest → `Model::create($validated)` → Resource. Done.
3. **Invokable Actions** for complex operations. One class, one `__invoke()`, one test file.
4. **Form Requests** own all validation. Zero `$request->validate()` in controllers.
5. **API Resources** own all output shaping. Zero manual arrays in controllers.
6. **Route model binding** + implicit tenant scope. Zero `findOrFail`, zero `where('tenant_id', …)`.
7. **Global tenant scope** via `HasTenant` trait. Set once at boot, never repeated.
8. **Events for side effects.** `Model::created` chains in controllers are a review blocker.
9. **Enums for status fields.** `CampaignStatus::Sending`, never `'sending'` strings.
10. **Config over constants.** Behavior knobs live in `config/`, not class constants.

**Canonical shape of a feature (~20 lines of logic):**

```php
// Request — validation         // Controller — orchestration         // Model — data
class StoreCampaignRequest       public function store(               class Campaign extends Model
  extends FormRequest              StoreCampaignRequest $request)     {
{                                  {                                      use HasTenant, HasUuids;
    public function rules(): array     $campaign = Campaign::create(          protected $fillable = [
    {                                    $request->validated());               'name','subject',
        return [                                                             'body_html','list_id'];
            'name'    => ['required',      CampaignCreated::dispatch(      }
            'string','max:120'],               $campaign);
            'subject' => ['required',      return new CampaignResource(   // Listener builds the
            'string','max:200'],               $campaign);                 // recipient queue — not
            'body_html'=> ['required', }                                     // the controller.
            'string'],
            'list_id'  => ['required',
            'exists:contact_lists,id'],
        ];
    }
}
```

---

## 2. PHP 8.5 / Laravel 13 Standards

- `declare(strict_types=1);` — **every** PHP file, first line
- Constructor property promotion + `readonly` wherever legal
- Parameter + return types on **everything**; no docblock-only types
- `match` over `switch`; nullsafe `?->` over null-check pyramids; named arguments when >2 params
- Enums: `BackedEnum` with `label()` method for UI; never raw status strings
- Collections over array loops where it reads better (`->map`, `->filter`, `->groupBy`)
- Carbon immutable (`CarbonImmutable`) for all date math

## 3. Naming

| Thing | Convention | Example |
|-------|-----------|---------|
| Models | Singular PascalCase | `Campaign`, `SmtpAccount`, `BounceMailbox` |
| Tables | Plural snake_case | `campaigns`, `smtp_accounts` |
| Actions | Verb+Noun, invokable | `SendCampaignBatch`, `ProcessBounces`, `SpinContent` |
| Routes | kebab-case resources | `smtp-accounts.test`, `bounce-mailboxes.check-now` |
| Events | Past tense | `CampaignCreated`, `HardBounceDetected` |
| Listeners | Action phrase | `BuildRecipientQueue`, `NotifyTenantOwner` |
| Jobs | Imperative | `ImportContactsChunk`, `FlushTrackingBuffer` |
| Scopes | `scope` + Adjective | `scopeActive`, `scopeHealthy`, `scopeUnsuppressed` |
| Redis keys | `t:{short}:{domain}:{key}` | `t:0192ab3c:cache:dashboard` |
| Config | kebab file, dot keys | `elitesender.php` → `elitesender.sending.batch_size` |

## 4. Database & Query Rules

- UUID v7 PKs everywhere; models use a `HasUuid7` trait (`creating` → `Str::uuid7()`)
- **Eloquent only.** Raw SQL / `DB::statement` / `whereRaw` are PHPStan-blocked (rule in `phpstan.neon`)
- `Model::preventLazyLoading()` + `Model::preventSilentlyDiscardingAttributes()` in `AppServiceProvider` — N+1 and mass-assignment accidents crash in dev, not prod
- Every query hitting >25 rows uses cursor pagination or chunking
- Migrations: reversible (`down()` mandatory), zero data loss, additive-only after launch (no column drops without a shipped deprecation cycle)

## 5. Redis Standards

- Tenant prefix set by middleware — **app code never builds raw keys**; use `TenantCache::get('dashboard', …)` helper that applies prefix
- **TTL on every key** — `Cache::forever()` allowed only in `global:` namespace (plans, config)
- Right structure for the job: HyperLogLog for unique counts, sorted sets for leaderboards/recent items, lists for buffers, plain strings for scalars
- Locks: `Cache::lock($name, $ttl)` with owner tokens; never ad-hoc `SETNX` without expiry
- See Architecture §4 for socket/transport config

## 6. Frontend Standards

- Blade components in `resources/views/components/` — reusable pieces are components, not `@include` soup
- Alpine.js: minimal `x-data` state; cross-component comms via `$dispatch`/events; no business logic in the browser
- Tailwind 4: utility-first; `@apply` only inside component base classes; **zero inline styles** (`style=` is a review blocker)
- Dark mode: `dark:` variant on every component — part of "done"
- Skeleton states + empty states ship with every list view (see PWA spec)
- No inline `<script>` blocks; all JS in Vite-built modules
- Fonts: Inter + Geist Mono, self-hosted via Vite (no external font CDN dependency)

## 7. Testing Standards — Local-First Elite Bar

**Philosophy:** the pre-commit hook is the real gate. CI confirms; it does not replace local verification.

### 7.1 The local gate (pre-commit, < 60s)

```bash
vendor/bin/pint --test                          # style
vendor/bin/phpstan analyse --memory-limit=1G    # level 9, zero baseline
php artisan test --parallel --stop-on-failure   # full suite
```

Husky/Captain Hook enforces on `git commit`. No commit lands red.

### 7.2 Test stack

| Layer | Tool | Requirement |
|-------|------|-------------|
| Unit | Pest | Every Action has a test; 90%+ coverage on `app/Actions` |
| Feature | Pest | **Every API endpoint** has at least one happy-path + one failure test |
| Tenant isolation | Pest (custom expectations) | Every tenant-scoped model + endpoint gets a cross-tenant access test — **auto-required in review** |
| Security | Feature tests | IDOR attempts, scope bypass, CSRF, rate-limit, unauthenticated access per endpoint |
| Performance | Query assertions | `DB::listen`-based: ≤5 queries per dashboard/list endpoint |
| Browser | Dusk (Chromium in Sail) | Critical paths only: onboarding wizard, campaign send, billing upgrade |

### 7.3 Conventions

```php
it('prevents cross-tenant campaign access', function () {
    $campaign = Campaign::factory()->create();               // tenant A
    actingAsTenant($otherTenant);                            // tenant B

    getJson("/api/v1/campaigns/{$campaign->id}")->assertNotFound();
});
```

- Test names read as sentences (`it('…')`)
- `RefreshDatabase` + factories with states (`->bounced()`, `->paused()`)
- No mocking of Eloquent — hit the real test database (SQLite in-memory for speed is forbidden; PG in Docker matches prod behavior)
- Mailpit + fixture messages for bounce-processing tests

## 8. Static Analysis & Style

| Tool | Config | Bar |
|------|--------|-----|
| Laravel Pint | `pint.json` — Laravel preset | Zero diffs on `--test` |
| PHPStan | `phpstan.neon` — **level 9**, zero baseline, custom rules (raw-SQL ban, `Cache::forever` guard) | Zero errors |
| Composer audit | CI weekly | Zero unpatched CVEs |

## 9. Git Conventions

- **Conventional Commits:** `feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`, `perf:`, `security:`
- **Branches:** `feat/campaign-builder`, `fix/bounce-parsing`, `docs/api-contract`
- **PR template checklist:**
  - [ ] Tenant isolation verified (new model/endpoint has cross-tenant test)
  - [ ] No N+1 (lazy-loading guard passes)
  - [ ] Form Request + Resource used
  - [ ] Dark mode + skeleton + empty state (UI PRs)
  - [ ] No secrets, no inline styles, no raw SQL
  - [ ] Tests: new/changed behavior covered
- One review required; `main` and `develop` protected

## 10. Definition of Done (every PR)

1. Local gate green (Pint + PHPStan 9 + full Pest suite)
2. Tenant isolation test exists for any new scoped surface
3. Matches "20-not-200" — reviewer can read the diff in < 5 minutes
4. UX bar met if user-facing (PRD §4)
5. Docs updated if a decision changed (ADR required for decision-log changes)

---

## 11. Future-Proof Notes

- **ADRs** (`docs/adr/NNNN-title.md`) for any decision-log change — start numbering at `0001`
- **Mutation testing** (Infection PHP) evaluated at v1.1 for Action-layer test quality
- **Contract tests** from the OpenAPI spec at v2 — API drift detection
- **Load tests** (k6) for `/t/*` tracking and send pipeline at Phase 2 scale
