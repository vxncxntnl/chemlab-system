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

COPY backend/composer.json backend/composer.lock ./backend/

RUN cd backend && composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY backend ./backend
COPY frontend ./frontend

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && cd frontend \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/backend

RUN php artisan storage:link || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force && php artisan optimize && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
