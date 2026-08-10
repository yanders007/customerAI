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

APP_KEY_VALUE=$(grep -m1 "^APP_KEY=" .env | cut -d'=' -f2- | sed 's/#.*//' | xargs)
if [ -z "$APP_KEY_VALUE" ] || [ "$APP_KEY_VALUE" = "base64:CHANGE" ]; then
    echo "▶ APP_KEY manquante, génération..."
    php artisan key:generate --force
fi

# ── Migrations ───────────────────────────────────────────────────────────────
echo "▶ Migrations..."
php artisan migrate --force --no-interaction

# ── Caches Laravel ───────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Import du workflow n8n (idempotent) ───────────────────────────────────────
if [ -f "/app/n8n/workflow.json" ]; then
    echo "▶ Import du workflow n8n..."
    n8n import:workflow --input=/app/n8n/workflow.json || echo "⚠ Import n8n échoué (workflow déjà présent ?)"
fi

# ── Lancer Supervisor (nginx + php-fpm + n8n + queue worker) ─────────────────
echo "▶ Lancement de Supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/app.conf
