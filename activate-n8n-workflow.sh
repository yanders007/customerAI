#!/bin/bash
# Script pour importer et activer automatiquement le workflow N8N au démarrage

echo "⏳ Attente démarrage N8N (15s)..."
sleep 15

MAX_RETRIES=40
RETRY=0

while [ $RETRY -lt $MAX_RETRIES ]; do
    # Vérifier si N8N est accessible
    if curl -s http://localhost:5678/healthz > /dev/null 2>&1; then
        echo "✓ N8N est accessible"
        
        # Attendre encore un peu pour s'assurer que N8N est complètement prêt
        sleep 3
        
        # Importer le workflow via l'API REST (plus fiable que n8n import:workflow)
        if [ -f "/app/n8n/workflow.json" ]; then
            echo "▶ Import du workflow via API N8N..."
            
            # Lire le contenu du workflow
            WORKFLOW_CONTENT=$(cat /app/n8n/workflow.json)
            
            # Importer via POST /rest/workflows
            IMPORT_RESULT=$(curl -s -X POST http://localhost:5678/rest/workflows \
              -H "Content-Type: application/json" \
              -d "$WORKFLOW_CONTENT" 2>/dev/null)
            
            if echo "$IMPORT_RESULT" | grep -q '"id"'; then
                echo "✓ Workflow importé avec succès"
            else
                echo "⚠ Import échoué (peut-être déjà présent), tentative de récupération..."
            fi
            
            echo "⏳ Attente indexation workflow (3s)..."
            sleep 3
        fi
        
        # Récupérer la liste des workflows
        WORKFLOWS=$(curl -s http://localhost:5678/rest/workflows 2>/dev/null)
        
        # Debug : afficher ce que l'API retourne
        echo "DEBUG: API response = $WORKFLOWS"
        
        if [ ! -z "$WORKFLOWS" ] && [ "$WORKFLOWS" != "null" ] && [ "$WORKFLOWS" != "[]" ]; then
            # Extraire tous les IDs de workflows
            WORKFLOW_IDS=$(echo "$WORKFLOWS" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
            
            if [ -z "$WORKFLOW_IDS" ]; then
                echo "⚠ Aucun workflow trouvé après import (tentative $RETRY/$MAX_RETRIES)"
                RETRY=$((RETRY+1))
                sleep 3
                continue
            fi
            
            for WF_ID in $WORKFLOW_IDS; do
                echo "▶ Activation du workflow $WF_ID..."
                RESULT=$(curl -s -X PATCH http://localhost:5678/rest/workflows/$WF_ID \
                  -H "Content-Type: application/json" \
                  -d '{"active": true}' 2>/dev/null)
                
                if echo "$RESULT" | grep -q '"active":true'; then
                    echo "✓ Workflow $WF_ID activé avec succès"
                else
                    echo "⚠ Échec activation workflow $WF_ID (peut-être déjà actif)"
                fi
            done
            
            echo "✓ Import et activation des workflows terminés"
            exit 0
        fi
    fi
    
    RETRY=$((RETRY+1))
    echo "⏳ Tentative $RETRY/$MAX_RETRIES..."
    sleep 3
done

echo "❌ Timeout : N8N non accessible après ${MAX_RETRIES} tentatives"
exit 1
