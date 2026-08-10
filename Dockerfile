FROM php:8.3-fpm-bookworm

# ── System deps ─────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    nginx supervisor curl git unzip zip \
    libpq-dev libzip-dev libpng-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ───────────────────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql \
    mbstring zip exif pcntl bcmath gd xml

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Backend Laravel ──────────────────────────────────────────────────────────
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY backend/ .
RUN composer run-script post-autoload-dump

# ── Configs Nginx / Supervisor ───────────────────────────────────────────────
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY supervisord.conf /etc/supervisor/conf.d/app.conf

# ── Script de démarrage ──────────────────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Permissions ──────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /app/backend/storage /app/backend/bootstrap/cache \
    && chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/entrypoint.sh"]
