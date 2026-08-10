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

# ── Node.js 20 (pour n8n) ────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# ── n8n (version fixe pour reproductibilité) ─────────────────────────────────
ENV N8N_VERSION=1.68.0
RUN npm install -g n8n@${N8N_VERSION} --omit=dev \
    && node --version && n8n --version

# ── Backend Laravel ──────────────────────────────────────────────────────────
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY backend/ .

# ── Frontend build (Vercel s'en charge, mais on garde l'option) ─────────────
# Si tu préfères tout héberger sur Render, décommente ces lignes :
# WORKDIR /app/frontend
# COPY frontend/package*.json ./
# RUN npm ci
# COPY frontend/ .
# ARG VITE_API_URL
# ENV VITE_API_URL=${VITE_API_URL}
# RUN npm run build

# ── Configs Nginx / Supervisor ───────────────────────────────────────────────
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY supervisord.conf /etc/supervisor/conf.d/app.conf

# ── Script de démarrage ──────────────────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Permissions ──────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /app/backend/storage /app/backend/bootstrap/cache \
    && chmod -R 775 /app/backend/storage /app/backend/bootstrap/cache

# ── Dossier pour les données n8n ─────────────────────────────────────────────
RUN mkdir -p /root/.n8n && chmod 777 /root/.n8n

EXPOSE 10000

CMD ["/usr/local/bin/entrypoint.sh"]
