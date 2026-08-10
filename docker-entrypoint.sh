#!/bin/bash
set -e

# ── Port Render (défaut 10000) ────────────────────────────────────────────────
export PORT="${PORT:-10000}"
echo "▶ Démarrage sur le port $PORT"

# ── Générer la conf Nginx avec le bon port ────────────────────────────────────
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/sites-enabled/default
rm -f /etc/nginx/sites-enabled/000-default.conf 2>/dev/null || true

# ── Laravel : copier .env si absent ──────────────────────────────────────────
cd /app/backend

if [ ! -f ".env" ]; then
    cp .env.example .env
fi

# ── Générer APP_KEY si vide ───────────────────────────────────────────────────
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:CHANGE" .env; then
    php artisan key:generate --force
fi

# ── Migrations PostgreSQL (Supabase) ─────────────────────────────────────────
echo "▶ Migrations..."
php artisan migrate --force --no-interaction

# ── Caches Laravel ───────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Lancer n8n en arrière-plan ───────────────────────────────────────────────
echo "▶ Démarrage de n8n..."
N8N_PORT=5678 \
N8N_PROTOCOL=http \
N8N_HOST=localhost \
N8N_BASIC_AUTH_ACTIVE="${N8N_BASIC_AUTH_ACTIVE:-true}" \
N8N_BASIC_AUTH_USER="${N8N_BASIC_AUTH_USER:-admin}" \
N8N_BASIC_AUTH_PASSWORD="${N8N_BASIC_AUTH_PASSWORD:-changeme}" \
N8N_DB_TYPE="${N8N_DB_TYPE:-postgresdb}" \
N8N_DB_POSTGRESDB_HOST="${DB_HOST}" \
N8N_DB_POSTGRESDB_PORT="${DB_PORT:-5432}" \
N8N_DB_POSTGRESDB_DATABASE="${DB_DATABASE:-postgres}" \
N8N_DB_POSTGRESDB_USER="${DB_USERNAME:-postgres}" \
N8N_DB_POSTGRESDB_PASSWORD="${DB_PASSWORD}" \
N8N_DB_POSTGRESDB_SCHEMA="n8n" \
N8N_ENCRYPTION_KEY="${N8N_ENCRYPTION_KEY:-$(openssl rand -hex 32)}" \
N8N_DIAGNOSTICS_ENABLED=false \
N8N_PERSONALIZATION_ENABLED=false \
N8N_VERSION_NOTIFICATIONS_ENABLED=false \
NODE_OPTIONS="--max-old-space-size=300" \
n8n start &

N8N_PID=$!

# ── Attendre que n8n soit prêt ────────────────────────────────────────────────
echo "▶ Attente de n8n..."
for i in $(seq 1 60); do
    if curl -s -o /dev/null "http://localhost:5678/healthz" 2>/dev/null; then
        echo "✅ n8n prêt"
        break
    fi
    sleep 2
done

# ── Importer le workflow n8n si présent ───────────────────────────────────────
WORKFLOW_FILE="/app/n8n/workflow.json"
if [ -f "$WORKFLOW_FILE" ]; then
    echo "▶ Import du workflow n8n..."
    # Vérifier si le workflow existe déjà (via API)
    EXISTING=$(curl -s -u "${N8N_BASIC_AUTH_USER:-admin}:${N8N_BASIC_AUTH_PASSWORD:-changeme}" \
        "http://localhost:5678/api/v1/workflows" 2>/dev/null \
        | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d.get('data',d if isinstance(d,list) else [])))" 2>/dev/null || echo "0")
    
    if [ "$EXISTING" = "0" ]; then
        n8n import:workflow --input="$WORKFLOW_FILE" 2>/dev/null && \
            echo "✅ Workflow importé" || \
            echo "⚠️  Import workflow échoué (normal au 1er boot si DB vide)"
        
        # Activer tous les workflows importés
        curl -s -X POST \
            -u "${N8N_BASIC_AUTH_USER:-admin}:${N8N_BASIC_AUTH_PASSWORD:-changeme}" \
            -H "Content-Type: application/json" \
            "http://localhost:5678/api/v1/workflows" 2>/dev/null || true
    else
        echo "ℹ️  Workflow déjà présent ($EXISTING trouvé)"
    fi
fi

# ── Lancer Supervisor (nginx + php-fpm + queue worker) ───────────────────────
echo "▶ Lancement de Supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/app.conf
