# syntax=docker/dockerfile:1.7

###############################################################################
# EliteSender production image
#
# Multi-stage on purpose. The previous single-stage build installed the Node 20
# toolchain *into the runtime image* (hundreds of MB of build tooling shipped
# to production), ran `artisan migrate` at build time (a build must never touch
# a database — it bakes whatever schema the builder could reach and makes the
# image un-rebuildable offline), and hardcoded DB_CONNECTION=sqlite +
# CACHE_STORE=array, silently contradicting the documented PostgreSQL 17 +
# Redis 7 topology.
###############################################################################

# ---------------------------------------------------------------------------
# Stage 1 — front-end assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js* postcss.config.js* ./
COPY resources ./resources

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: package discovery needs the full source tree, which is not
# copied yet. It runs in the final stage instead.
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 3 — runtime
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS runtime

# Only non-secret, non-topology defaults belong in the image. Connection
# strings are injected by the platform at run time.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off

WORKDIR /var/www/html

USER root
RUN install -d -o www-data -g www-data \
        /var/www/html/storage/framework/{cache,sessions,views} \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache
USER www-data

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Now that the full tree is present, finish Composer's post-install work
# (package discovery) and warm the framework caches. Config is deliberately
# NOT cached here: `config:cache` freezes env() at build time, and every
# credential arrives at run time.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan event:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8080/up || exit 1
