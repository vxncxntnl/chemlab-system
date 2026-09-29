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

# Copy Composer files first for better Docker caching
COPY backend/composer.json backend/composer.lock ./backend/

# Install PHP dependencies without running Laravel scripts yet
RUN cd backend && composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Copy the complete Laravel backend
COPY backend ./backend

# Create Laravel cache directory and discover packages
RUN mkdir -p backend/bootstrap/cache \
    && chmod -R 775 backend/bootstrap/cache \
    && cd backend \
    && php artisan package:discover --ansi

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

# Use Laravel backend as the application directory
WORKDIR /var/www/backend

# Create storage symlink
RUN php artisan storage:link || true

# Render uses port 10000
EXPOSE 10000

# Run migrations, optimize Laravel, then start the server
CMD ["sh", "-c", "php artisan migrate --force && php artisan optimize && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]