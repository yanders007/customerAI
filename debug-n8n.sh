#!/bin/bash
# Script de diagnostic N8N pour Render Shell

echo "═══════════════════════════════════════════"
echo "  Diagnostic N8N - CustomerAI"
echo "═══════════════════════════════════════════"
echo ""

# 1. Vérifier que N8N écoute sur le port
echo "1️⃣  Vérification port N8N..."
if curl -s http://localhost:5678/healthz > /dev/null 2>&1; then
    echo "   ✅ N8N est accessible sur localhost:5678"
else
    echo "   ❌ N8N n'est pas accessible"
    exit 1
fi
echo ""

# 2. Vérifier les variables d'environnement N8N
echo "2️⃣  Variables d'environnement N8N..."
echo "   DB_POSTGRESDB_HOST=$DB_POSTGRESDB_HOST"
echo "   DB_POSTGRESDB_DATABASE=$DB_POSTGRESDB_DATABASE"
echo "   DB_POSTGRESDB_USER=$DB_POSTGRESDB_USER"
echo "   DB_POSTGRESDB_SCHEMA=$DB_POSTGRESDB_SCHEMA"
echo "   N8N_ENCRYPTION_KEY=${N8N_ENCRYPTION_KEY:0:10}... (masqué)"
echo ""

# 3. Tester l'API N8N (liste des workflows)
echo "3️⃣  Test API N8N (liste workflows)..."
WORKFLOWS=$(curl -s http://localhost:5678/api/v1/workflows 2>/dev/null)
echo "   Réponse API:"
echo "$WORKFLOWS" | head -10
echo ""

# 4. Compter les workflows
if echo "$WORKFLOWS" | grep -q '"id"'; then
    WORKFLOW_COUNT=$(echo "$WORKFLOWS" | grep -o '"id":' | wc -l)
    echo "   ✅ Nombre de workflows trouvés: $WORKFLOW_COUNT"
else
    echo "   ⚠️  Aucun workflow trouvé"
fi
echo ""

# 5. Vérifier le fichier workflow source
echo "4️⃣  Fichier workflow source..."
if [ -f "/app/n8n/workflow.json" ]; then
    FILE_SIZE=$(stat -f%z "/app/n8n/workflow.json" 2>/dev/null || stat -c%s "/app/n8n/workflow.json" 2>/dev/null)
    echo "   ✅ Fichier existe: /app/n8n/workflow.json ($FILE_SIZE bytes)"
else
    echo "   ❌ Fichier manquant: /app/n8n/workflow.json"
fi
echo ""

# 6. Vérifier les processus supervisord
echo "5️⃣  Processus Supervisor..."
supervisorctl status | grep -E "(n8n|php-fpm|nginx|queue-worker)" || echo "   ⚠️  Supervisor non accessible"
echo ""

# 7. Tester la connexion PostgreSQL depuis Laravel
echo "6️⃣  Test connexion PostgreSQL (Laravel)..."
cd /app/backend
php artisan migrate:status > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   ✅ Connexion PostgreSQL OK (Laravel)"
else
    echo "   ❌ Connexion PostgreSQL échouée"
fi
echo ""

echo "═══════════════════════════════════════════"
echo "  Diagnostic terminé"
echo "═══════════════════════════════════════════"
