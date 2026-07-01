# Fase 0: imagen simple para verificar el stack. La optimizacion
# (PHP-FPM + Nginx, multi-stage, opcache) es tarea explicita de la Fase 7.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY apps/backend/composer.json apps/backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-progress

COPY apps/backend .

RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
