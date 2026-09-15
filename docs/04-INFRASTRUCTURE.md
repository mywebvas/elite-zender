# 04 — Infrastructure & Deployment

**Status:** Draft | **Strategy:** $0 start → scale with revenue

---

## 1. Cost Path

| Phase | Trigger | Stack | Monthly Cost |
|-------|---------|-------|--------------|
| 0 — MVP | Launch | Railway free tier: app + PostgreSQL 17 + Redis 7 | **$0** |
| 1 | First paying user | Railway Hobby (per service) | ~$20 |
| 2 | ~100 users | Railway Pro (autoscaling workers) | ~$50–100 |
| 3 | Revenue-justified | Railway Pro, or migrate to Fly.io / AWS ECS (Dockerfile is portable) | Scales with MRR |

Free-tier stack everywhere else: R2 (10GB), B2 (10GB), Sentry, BetterStack, GitHub Actions (2,000 min/mo), Laravel Pulse (built-in).

---

## 2. Local Development — Docker Desktop + Laravel Sail

### 2.1 Stack

| Service | Image | Purpose |
|---------|-------|---------|
| `app` | PHP 8.5 FPM + Nginx | Laravel 13 |
| `postgres` | postgres:17 | Database |
| `redis` | redis:7-alpine | Cache + queue — **Unix socket only** |
| `mailpit` | axllent/mailpit | SMTP + IMAP capture (test sends + bounce fixtures) |
| `minio` | minio/minio | Local R2 (S3 API) |

### 2.2 Redis Unix socket wiring

```yaml
# docker-compose.yml (Sail override) — relevant excerpts
services:
  app:
    volumes:
      - redis-sock:/var/run/redis          # shared socket volume
  redis:
    image: redis:7-alpine
    command: >
      redis-server
      --port 0
      --unixsocket /var/run/redis/redis.sock
      --unixsocketperm 770
      --appendonly yes
    volumes:
      - redis-sock:/var/run/redis
      - redis-data:/data
volumes:
  redis-sock:
  redis-data:
```

```dotenv
# .env (local)
REDIS_SCHEME=unix
REDIS_SOCKET=/var/run/redis/redis.sock
```

- No TCP listener locally — socket file permission is the access boundary.
- Health check (`php artisan redis:ping` or `INFO`) fails loudly if socket missing; **no silent TCP fallback**.

### 2.3 Daily workflow

```bash
./vendor/bin/sail up -d        # full stack
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev  # Vite HMR
./vendor/bin/sail test --parallel   # the quality gate (see Coding Standards)
```

---

## 3. Railway Deployment

### 3.1 Services

| Railway service | Source | Notes |
|-----------------|--------|-------|
| `app` | Dockerfile | Nginx + PHP-FPM, serves HTTP |
| `worker` | Same image, `php artisan horizon` | Queue lanes: high/default/low |
| `scheduler` | Same image, `php artisan schedule:work` | Bounce polls, usage resets, rollups |
| PostgreSQL 17 | Railway managed | Daily snapshots |
| Redis 7 | Railway managed | **TCP here** — `REDIS_SCHEME=tcp` |

**Transport duality:** Unix socket locally, TCP on Railway. `REDIS_SCHEME` env switches; tenant prefix isolation is identical either way (see Architecture §4).

### 3.2 Dockerfile (multi-stage, ~40 lines)

```dockerfile
# Stage 1: composer deps
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist

# Stage 2: frontend build
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Stage 3: runtime
FROM php:8.5-fpm-alpine
RUN apk add --no-cache nginx supervisor \
 && docker-php-ext-install pdo_pgsql redis pcntl
WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisord.conf
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache
EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

### 3.3 railway.toml

```toml
[build]
builder = "DOCKERFILE"

[deploy]
healthcheckPath = "/health"
healthcheckTimeout = 30
restartPolicyType = "ON_FAILURE"

[env]
APP_ENV = "production"
REDIS_SCHEME = "tcp"
```

### 3.4 Secrets & config

- All secrets in Railway dashboard env vars — **no `.env` in production, nothing in git**.
- `APP_KEY`, DB/Redis credentials (Railway-injected), Stripe keys, R2/B2 keys, VAPID keys.

---

## 4. CI/CD — Meaningful Gates Only

Philosophy: **the real quality gate is local** (Pint + PHPStan 9 + full Pest suite + tenant-isolation tests — see [Coding Standards](09-CODING-STANDARDS.md)). CI confirms, it does not duplicate or expand.

### 4.1 Pipelines (GitHub Actions)

| Workflow | Trigger | Steps | Time |
|----------|---------|-------|------|
| `ci-gate.yml` | PR opened/updated | Pint `--test` → PHPStan 9 → Pest `--parallel` | ~2 min |
| `deploy-production.yml` | Merge to `main` | Build image → Railway deploy → post-deploy health check | ~3 min |
| `deploy-staging.yml` | Merge to `develop` | Same → staging environment | ~3 min |
| `security-audit.yml` | Weekly cron | `composer audit` + `npm audit` | ~1 min |

### 4.2 Branch model

```
main       → production (Railway)
develop    → staging (Railway staging env)
feat/*     → PR → ci-gate → develop
fix/*      → PR → ci-gate → develop
```

Rules: PRs require green `ci-gate` + 1 review. No direct pushes to `main`/`develop`. No preview environments at MVP (Railway cost); staging is the shared preview.

---

## 5. Monitoring & Observability (free tier)

| Tool | Covers |
|------|--------|
| **Laravel Pulse** | App-level: slow requests, queue depth, exceptions, cache hit rate — built-in, zero cost |
| **Sentry** | Exceptions + performance traces (5k events/mo free) |
| **BetterStack** | Uptime ping `/health` every 30s + status page |
| **Railway metrics** | CPU/RAM/network per service |
| **Horizon dashboard** | Queue throughput, failures, wait times (auth-gated) |

Alerting: Sentry → email/Slack on new issues; BetterStack → SMS/email on downtime; Horizon failed jobs → `global` Slack webhook (v2).

---

## 6. Backup & Recovery

| Asset | Strategy | Retention |
|-------|----------|-----------|
| PostgreSQL | Railway automated daily snapshots | 7 days (free tier) |
| PostgreSQL (extra) | Weekly `pg_dump` via scheduled GitHub Action → B2 | 12 weeks |
| Redis | AOF persistence; **cache is rebuildable, queue is short-lived** — no backup needed | — |
| R2 | Object versioning enabled | 30 days of versions |
| B2 | Lifecycle rules on archive buckets | per data class (see Database §retention) |

**Recovery targets:** RPO ≤ 24h (daily snapshot), RTO ≤ 1h (redeploy from image + restore snapshot). Full restore runbook lives in `deploy/runbooks/restore.md` (created at first deploy).

---

## 7. Environments

| Env | URL pattern | Data | Purpose |
|-----|------------|------|---------|
| local | `localhost` (Sail) | Seeded demo | Development |
| staging | `staging.elitesender.app` | Synthetic only | Pre-prod verification |
| production | `app.elitesender.app` | Live | — |

Config drift control: identical Dockerfile everywhere; only env vars differ.

---

## 8. Future-Proof Notes

- **Dockerfile is environment-agnostic** — same image runs on Railway, Fly.io, ECS Fargate. Migration is a config exercise, not a rebuild.
- **Compose profiles** (`dev`, `test`, `production-like`) allow prod-parity local testing when needed.
- **Terraform/Pulumi**: deferred until Phase 2+; Railway dashboard config is documented here as the interim source of truth.
- **Multi-region**: schema (UUID v7, tenant_id everywhere) and R2's global nature mean region expansion needs no data-model change.
- **Load testing**: k6 scripts for `/t/*` tracking endpoints and campaign send at Phase 2 (tracking is the highest-RPS surface).
