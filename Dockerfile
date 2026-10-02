FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    nginx \
    supervisor \
    gettext-base \
    && docker-php-ext-install pdo_pgsql \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy Composer files first
COPY backend/composer.json backend/composer.lock ./backend/

# Install Laravel dependencies
RUN cd backend && composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy Laravel backend
COPY backend ./backend

# Create required Laravel directories
RUN mkdir -p \
    backend/bootstrap/cache \
    backend/storage/framework/cache \
    backend/storage/framework/sessions \
    backend/storage/framework/views \
    backend/storage/logs \
    backend/resources/views \
    && chmod -R 775 backend/bootstrap/cache \
    && chmod -R 775 backend/storage \
    && chmod -R 775 backend/resources/views \
    && chown -R www-data:www-data backend/bootstrap/cache backend/storage

# Discover Laravel packages
RUN cd backend && php artisan package:discover --ansi

# Copy Vue frontend
COPY frontend ./frontend

# Install Node.js and build Vue frontend
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && cd frontend \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && rm -rf /var/lib/apt/lists/*

# Use Laravel backend
WORKDIR /var/www/backend

# Create storage link
RUN php artisan storage:link || true

# Nginx + Supervisor configuration (serves the app via php-fpm instead of
# Laravel's development-only "artisan serve" command)
COPY docker/nginx.conf.template /etc/nginx/nginx-site.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
RUN rm -f /etc/nginx/sites-enabled/default

EXPOSE 10000

# Start Laravel: run migrations/cache warmup, render the nginx config for the
# port Render assigns, then run php-fpm and nginx under supervisord
CMD ["sh", "-c", "php artisan migrate --force && php artisan optimize && PORT=${PORT:-10000} envsubst '${PORT}' < /etc/nginx/nginx-site.conf.template > /etc/nginx/sites-enabled/default.conf && exec supervisord -c /etc/supervisor/conf.d/supervisord.conf"]