# 07 — PWA Specification & Design System

**Status:** Draft | **Bar:** $1M premium feel, mobile-first, app-like — from the first commit.

---

## 1. Design Tokens

### 1.1 Typography
| Token | Value | Use |
|-------|-------|-----|
| `font-sans` | Inter, system-ui fallback | All UI text |
| `font-mono` | Geist Mono, ui-monospace fallback | Stats, code, IDs |
| Scale | 12 / 14 / 16 / 20 / 24 / 30 / 36px | 14px = body default |

### 1.2 Spacing & shape
- **8px grid** — every margin/padding is a multiple of 4, mostly 8 (`p-2`, `p-4`, `p-6`, `gap-4`…)
- Radius: `rounded-xl` cards · `rounded-lg` inputs/buttons · `rounded-full` badges/avatars
- Elevation (3 levels only):
  - `shadow-sm` — cards, list rows
  - `shadow-md` — dropdowns, popovers, toasts
  - `shadow-lg` — modals, drawers

### 1.3 Color
| Role | Light | Dark |
|------|-------|------|
| Surface | `slate-50` bg / `white` cards | `slate-950` bg / `slate-900` cards |
| Text | `slate-900` / `slate-500` secondary | `slate-100` / `slate-400` |
| Brand (primary actions) | `indigo-600` → hover `indigo-500` | `indigo-400` |
| Success | `emerald-600` | `emerald-400` |
| Danger | `rose-600` | `rose-400` |
| Warning | `amber-500` | `amber-400` |

Dark mode: system preference default + manual toggle persisted per user. Every component ships both variants — `dark:` is part of "done".

---

## 2. Layout System

### Desktop (≥1024px)
- Left sidebar nav (collapsible to icons): Dashboard · Campaigns · Contacts · Lists · SMTP Pool · Bounces · Settings
- Content max-width `max-w-7xl`, 24px gutters
- Usage meter (emails this cycle vs plan) pinned in top bar — always visible, never a surprise

### Mobile (<1024px)
- **Bottom navigation** (4 items + center FAB "New campaign"): Dashboard · Campaigns · ＋ · Contacts · Settings
- Top bar: page title + contextual action only
- All lists → card stacks; all tables → key-value cards; horizontal scroll is a design failure

---

## 3. Interaction Patterns (the app-feel contract)

| Pattern | Spec |
|---------|------|
| Touch targets | ≥44px height/width, ≥48px for primary CTAs |
| Transitions | 150ms ease-out via Alpine `x-transition`; no linear, no >300ms |
| Skeleton loaders | Shimmer placeholders matching final layout on **every** async load — spinners are banned |
| Optimistic UI | Toggles, renames, archives update instantly; rollback + error toast on failure |
| Pull-to-refresh | Dashboard + all list views (mobile) |
| Swipe actions | List rows: swipe left → Archive (gray) / Delete (rose); haptic tick via Vibration API where supported |
| Focus states | Visible `ring-2 ring-indigo-500` on every interactive element (keyboard + a11y) |
| Modals | Bottom sheet on mobile, centered dialog on desktop; `Esc` + backdrop click close; focus trapped |
| Empty states | SVG illustration + one sentence + one CTA (see PRD §4.3) — an invitation, never a dead end |

---

## 4. Notification Architecture

### 4.1 Toast stack (immediate feedback)
- Position: top-center mobile, bottom-right desktop
- Success: auto-dismiss 3s, checkmark micro-animation
- Error: persists until dismissed, includes one actionable line ("SMTP connection failed — check credentials")
- Max 3 visible; queue overflows collapse into "3 more updates"
- Implemented as `<x-toast>` Blade component + `window.$toast()` Alpine global; queue workers push via session flash + SSE (v2 real-time)

### 4.2 In-app notification center
- Bell icon in top bar with unread count badge
- Events: campaign completed/failed, bounce spike detected, import finished, usage at 80%/100%, team invites
- Mark read/unread, deep-link to source entity
- Per-channel preferences in Settings (`notification_preferences` table): in-app / email digest / push

### 4.3 Push notifications (Web Push + VAPID)
- Events (opt-in): campaign completed, hard-bounce threshold hit, plan usage ≥90%
- **Permission asked contextually** — after first campaign send, never on page load
- Graceful degradation: unsupported browser → silently hidden

### 4.4 Email digests
- Daily/weekly summary (per preference): sends, opens, clicks, bounces, usage
- Transactional (immediate): security events (new login, token created), billing events

---

## 5. PWA Technical Spec

### 5.1 manifest.json
```json
{
  "name": "EliteSender",
  "short_name": "EliteSender",
  "display": "standalone",
  "start_url": "/dashboard",
  "theme_color": "#4f46e5",
  "background_color": "#0f172a",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/maskable-512.png", "sizes": "512x512", "purpose": "maskable" }
  ]
}
```

### 5.2 Service worker (Workbox via Vite PWA plugin)
| Asset class | Strategy | Rationale |
|-------------|----------|-----------|
| Built CSS/JS/fonts/icons | Cache-first, versioned precache | Immutable hashed assets |
| Blade pages / API GETs | Network-first, 3s timeout → cache | Fresh data wins, offline works |
| Dashboard stats | Stale-while-revalidate | Instant paint + background refresh |
| Tracking pixel domains | Network-only | Never cache tracking |
| Offline fallback | `/offline` page + last-cached dashboard read-only | Never a blank screen |

Update flow: new SW detected → toast "Update available — [Refresh]" (no forced reload mid-work).

### 5.3 Offline capability (v1)
- Read-only: cached dashboard, campaign list, contact counts
- Draft autosave: campaign composer persists to IndexedDB every 5s; syncs on reconnect with conflict = newest-wins + notice
- Everything else: clear "You're offline" state with retry CTA

---

## 6. Performance Budgets (CI-checked at v1 hardening)

| Metric | Target |
|--------|--------|
| Lighthouse PWA | ≥ 95 |
| Lighthouse Performance | ≥ 90 |
| Lighthouse Accessibility | ≥ 95 |
| FCP | < 1.2s |
| LCP | < 2.0s |
| CLS | < 0.05 |
| TTI | < 2.5s |
| JS bundle (gzip) | < 50KB (Alpine ~15KB + app code) |
| CSS bundle (gzip, Tailwind purged) | < 30KB |

---

## 7. Accessibility (WCAG 2.1 AA)

- Semantic HTML first (nav/main/button, not div-soup)
- All form fields labeled; errors linked via `aria-describedby`
- Keyboard: full flow navigable; visible focus rings; modal focus trap
- Contrast ≥ 4.5:1 text, ≥ 3:1 UI components (both themes)
- Screen-reader announcements on toasts and async completions (`aria-live="polite"`)
- Respect `prefers-reduced-motion` — animations degrade to instant

---

## 8. Component Inventory (Blade `<x-*>`)

Base: `x-app-layout` · `x-card` · `x-button` (primary/secondary/ghost/danger) · `x-input` · `x-select` · `x-modal` · `x-drawer` · `x-toast` · `x-badge` · `x-empty-state` · `x-skeleton` · `x-stat-card` · `x-usage-meter` · `x-health-score` · `x-bottom-nav` · `x-sidebar`

Domain: `x-campaign-status-pill` · `x-contact-row` · `x-smtp-card` · `x-import-progress` · `x-spin-preview`

Rules: components own their dark-mode variants and skeleton states; zero inline styles; `@apply` only inside component base classes.

---

## 9. Future-Proof Notes

- **Native wrapper (v3):** Capacitor over this PWA → App Store presence without a rewrite
- **Real-time (v2):** Redis pub/sub → SSE channel replaces polling for live campaign stats; Alpine listener already structured for push updates
- **Offline sending queue (v2):** IndexedDB outbox pattern extends the draft-sync mechanism
- **Design tokens → CSS variables:** enables tenant white-label theming (v3) by swapping a token map
