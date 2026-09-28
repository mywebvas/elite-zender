# Deployment notes

## Topology

```
         ┌──────────────┐
  TLS ──►│    nginx     │──► php-fpm 8.4 (or Octane/RoadRunner)
         └──────────────┘
                 │
     ┌───────────┼─────────────┐
     ▼           ▼             ▼
 PostgreSQL 17  Redis 7   supervisor
                          ├─ 4× worker (queue: high)
                          ├─ 2× worker (queue: default,low)
                          └─ 1× scheduler
```

## Files

| File | Purpose |
| --- | --- |
| `nginx/elitesender.conf` | TLS termination, compression, asset caching, per-path rate limits |
| `supervisor/elitesender-worker.conf` | Queue lanes + scheduler |

## Non-obvious requirements

- **`client_max_body_size` must stay ≥ the CSV upload limit.** The application
  validates uploads at 20 MB; nginx's 1 MB default rejects them at the edge
  with a 413 the app never sees.
- **Security headers come from the application**, not from nginx. Setting them
  in both places is how `X-Frame-Options: DENY` silently becomes `SAMEORIGIN`.
- **`/sw.js` must not be cached.** A cached service worker pins clients to an
  old build indefinitely.
- **One scheduler process only.** `onOneServer()` guards against multiple
  hosts, not multiple local processes.
- **Workers need `--max-time`.** Long-lived PHP processes accumulate state;
  recycling is cheaper than debugging a leak.

## Zero-downtime deploy order

```bash
php artisan down --render=errors::503 --retry=15

php artisan migrate --force        # additive migrations only
php artisan event:cache
php artisan route:cache
php artisan view:cache
# NB: never `config:cache` in an image build — env() returns null afterwards.

supervisorctl restart elitesender:*
php artisan up
```

## Health

`GET /up` is the health endpoint (`bootstrap/app.php`, `railway.json`).
It boots the framework, so it fails when the app cannot resolve its config —
which is exactly what a health check should detect.
