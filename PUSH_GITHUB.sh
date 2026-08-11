#!/bin/bash

# Script de push vers GitHub
# Usage: ./PUSH_GITHUB.sh

echo "════════════════════════════════════════════════════════════════════════"
echo "                  📤 PUSH CUSTOMERAI VERS GITHUB"
echo "════════════════════════════════════════════════════════════════════════"
echo ""

# Vérifier si nous sommes dans le bon répertoire
if [ ! -d ".git" ]; then
    echo "❌ Erreur: Ce script doit être exécuté dans le répertoire du projet"
    exit 1
fi

# Afficher les commits en attente
echo "📋 Commits en attente de push:"
echo "────────────────────────────────────────────────────────────────────────"
git log --oneline origin/main..HEAD 2>/dev/null || git log --oneline -10
echo ""

# Afficher le statut
echo "📊 Statut git:"
echo "────────────────────────────────────────────────────────────────────────"
git status --short
echo ""

# Compter les commits
COMMIT_COUNT=$(git rev-list --count origin/main..HEAD 2>/dev/null || echo "10")
echo "✨ $COMMIT_COUNT commits prêts à être poussés"
echo ""

# Vérifier la connexion réseau à GitHub
echo "🔍 Vérification connexion GitHub..."
if ping -c 1 github.com >/dev/null 2>&1; then
    echo "✅ Connexion réseau OK"
else
    echo "❌ Pas de connexion à GitHub"
    exit 1
fi
echo ""

# Vérifier le remote
echo "🔗 Remote configuré:"
git remote -v
echo ""

# Essayer le push
echo "════════════════════════════════════════════════════════════════════════"
echo "🚀 Lancement du push..."
echo "════════════════════════════════════════════════════════════════════════"
echo ""
echo "⚠️  Si vous utilisez HTTPS, GitHub vous demandera:"
echo "   • Username: votre nom d'utilisateur GitHub"
echo "   • Password: votre Personal Access Token (PAS votre mot de passe!)"
echo ""
echo "   Pour créer un token:"
echo "   1. https://github.com/settings/tokens"
echo "   2. Generate new token (classic)"
echo "   3. Cochez 'repo' (Full control of private repositories)"
echo "   4. Copiez le token généré"
echo ""

# Push
git push origin main

# Vérifier le résultat
if [ $? -eq 0 ]; then
    echo ""
    echo "════════════════════════════════════════════════════════════════════════"
    echo "✅ PUSH RÉUSSI!"
    echo "════════════════════════════════════════════════════════════════════════"
    echo ""
    echo "📦 $COMMIT_COUNT commits poussés sur origin/main"
    echo ""
    echo "🔄 Prochaines étapes:"
    echo "   1. Vercel redéploiera automatiquement le frontend"
    echo "   2. Sur Render, exécutez: php artisan migrate"
    echo "   3. Testez sur Android la connexion et les nouvelles features"
    echo ""
else
    echo ""
    echo "════════════════════════════════════════════════════════════════════════"
    echo "❌ PUSH ÉCHOUÉ"
    echo "════════════════════════════════════════════════════════════════════════"
    echo ""
    echo "🔧 Solutions possibles:"
    echo ""
    echo "1. Authentification HTTPS (recommandé):"
    echo "   • Utilisez un Personal Access Token"
    echo "   • https://github.com/settings/tokens"
    echo ""
    echo "2. Passer en SSH:"
    echo "   git remote set-url origin git@github.com:yanders007/customerAI.git"
    echo "   git push origin main"
    echo ""
    echo "3. Vérifier vos credentials:"
    echo "   git config --global credential.helper store"
    echo "   git push origin main"
    echo ""
    exit 1
fi
