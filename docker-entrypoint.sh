#!/bin/bash
set -e

export PORT="${PORT:-10000}"
echo "▶ Démarrage sur le port $PORT"

# ── Générer la conf Nginx avec le bon port ────────────────────────────────────
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-enabled/000-default.conf 2>/dev/null || true

# ── Laravel : copier .env si absent ──────────────────────────────────────────
cd /app/backend
if [ ! -f ".env" ]; then cp .env.example .env; fi

if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:CHANGE" .env; then
    php artisan key:generate --force
fi

# ── Migrations ───────────────────────────────────────────────────────────────
echo "▶ Migrations..."
php artisan migrate --force --no-interaction

# ── Caches Laravel ───────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Lancer Supervisor (nginx + php-fpm + queue worker) ───────────────────────
echo "▶ Lancement de Supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/app.conf
