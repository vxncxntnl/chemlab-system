FROM php:8.5-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy Composer files first
COPY backend/composer.json backend/composer.lock ./backend/

# Install Laravel dependencies without running scripts
RUN cd backend && composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy Laravel backend
COPY backend ./backend

# Create required Laravel directories
RUN mkdir -p backend/bootstrap/cache \
    && mkdir -p backend/resources/views \
    && chmod -R 775 backend/bootstrap/cache \
    && chmod -R 775 backend/storage \
    && chmod -R 775 backend/resources/views

# Discover Laravel packages
RUN cd backend && php artisan package:discover --ansi

# Copy Vue frontend
COPY frontend ./frontend

# Install Node.js and build Vue
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && cd frontend \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/backend

# Create storage link
RUN php artisan storage:link || true

EXPOSE 10000

# Start Laravel
CMD ["sh", "-c", "php artisan migrate --force && php artisan optimize && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]