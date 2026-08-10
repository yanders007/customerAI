#!/bin/bash
# Script pour démarrer le worker de queue Laravel

cd "$(dirname "$0")"

echo "════════════════════════════════════════════════════════════════"
echo "🚀 DÉMARRAGE DU WORKER DE QUEUE"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "Ce worker traite l'indexation des documents en arrière-plan."
echo "Les embeddings seront générés automatiquement après la création"
echo "de chaque document, sans bloquer l'utilisateur."
echo ""
echo "Configuration:"
echo "  • Retry: 3 tentatives en cas d'échec"
echo "  • Timeout: 600 secondes (10 minutes par job)"
echo "  • Backoff: 30s, 60s, 120s entre chaque retry"
echo ""
echo "Pour arrêter: Ctrl+C"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Démarrer le worker avec les bonnes options
php artisan queue:work --tries=3 --timeout=600 --verbose
