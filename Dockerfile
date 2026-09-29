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
# Stage 1 — PHP dependencies
# ---------------------------------------------------------------------------
# Built before the assets stage on purpose: Tailwind has to scan Laravel's
# own pagination Blade views, which only exist inside vendor/.
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
# Stage 2 — front-end assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js* postcss.config.js* ./
COPY resources ./resources

# resources/css/app.css declares this path as a Tailwind @source. Without it
# the pagination controls shipped to production had no rounded ends, no
# borders and no spacing: the classes only exist in Laravel's own Blade view,
# the stage could not see them, and Tailwind tree-shook them away in silence.
COPY --from=vendor \
    /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views

# Set to 1 for an air-gapped build; the UI falls back to the system font
# stack declared in resources/css/app.css.
ARG VITE_DISABLE_REMOTE_FONTS=0
ENV VITE_DISABLE_REMOTE_FONTS=${VITE_DISABLE_REMOTE_FONTS}

RUN npm run build

# Fail the build rather than ship a stylesheet with holes in it. These two
# classes come only from the vendored pagination view, so their absence means
# the @source above stopped resolving.
RUN for class in rounded-l-md rounded-r-md; do \
        grep -qr "$class" public/build/assets/*.css \
            || { echo "Tailwind output is missing .$class — check the @source paths in resources/css/app.css"; exit 1; }; \
    done

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
