FROM serversideup/php:8.3-fpm-nginx

# Environment variables
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    SESSION_DRIVER=cookie \
    CACHE_STORE=array

# Install Node.js for Vite build
USER root
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs sqlite3 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
USER www-data

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Install Node dependencies and build assets
RUN npm ci && npm run build && rm -rf node_modules

# Prepare SQLite database (if needed for deployment)
RUN touch database/database.sqlite \
    && php artisan migrate --force

# Optimize Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Expose port (serversideup/php uses 8080 by default)
EXPOSE 8080
