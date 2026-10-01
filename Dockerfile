FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        curl \
        ca-certificates \
        gnupg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql zip intl bcmath pcntl opcache \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && corepack enable \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json pnpm-lock.yaml ./
RUN corepack prepare pnpm@latest --activate && pnpm install --frozen-lockfile

COPY . .

RUN cp .env.example .env \
    && composer dump-autoload --optimize --no-dev \
    && php artisan key:generate --force \
    && pnpm build \
    && rm -f .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker/entrypoint.sh"]
