#!/bin/bash
# Script pour activer automatiquement le workflow N8N

echo "⏳ Attente démarrage N8N..."
sleep 10

MAX_RETRIES=30
RETRY=0

while [ $RETRY -lt $MAX_RETRIES ]; do
    # Vérifier si N8N est accessible
    if curl -s http://localhost:5678/healthz > /dev/null 2>&1; then
        echo "✓ N8N est accessible"
        
        # Récupérer la liste des workflows
        WORKFLOWS=$(curl -s http://localhost:5678/rest/workflows 2>/dev/null)
        
        if [ ! -z "$WORKFLOWS" ]; then
            # Extraire tous les IDs de workflows
            WORKFLOW_IDS=$(echo "$WORKFLOWS" | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
            
            for WF_ID in $WORKFLOW_IDS; do
                echo "▶ Activation du workflow $WF_ID..."
                RESULT=$(curl -s -X PATCH http://localhost:5678/rest/workflows/$WF_ID \
                  -H "Content-Type: application/json" \
                  -d '{"active": true}' 2>/dev/null)
                
                if echo "$RESULT" | grep -q '"active":true'; then
                    echo "✓ Workflow $WF_ID activé avec succès"
                else
                    echo "⚠ Échec activation workflow $WF_ID"
                fi
            done
            
            echo "✓ Activation des workflows terminée"
            exit 0
        fi
    fi
    
    RETRY=$((RETRY+1))
    echo "⏳ Tentative $RETRY/$MAX_RETRIES..."
    sleep 2
done

echo "❌ Timeout : N8N non accessible après ${MAX_RETRIES} tentatives"
exit 1
