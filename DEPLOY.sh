#!/bin/bash

# ============================================
# Script de Déploiement CustomerAI v2.0
# ============================================

echo "🚀 Déploiement CustomerAI v2.0"
echo "================================"
echo ""

# Vérifier qu'on est dans le bon répertoire
if [ ! -f "AMELIORATIONS.md" ]; then
    echo "❌ Erreur: Exécutez ce script depuis la racine du projet customerAI"
    exit 1
fi

# Vérifier que git est configuré
if ! git config user.email > /dev/null; then
    echo "⚠️  Configuration Git manquante"
    echo "Exécutez ces commandes avant de continuer:"
    echo "  git config user.email 'votre@email.com'"
    echo "  git config user.name 'Votre Nom'"
    exit 1
fi

echo "📋 Vérification des fichiers modifiés..."
git status --short

echo ""
echo "📦 Ajout des fichiers au commit..."
git add .

echo ""
echo "✍️  Création du commit..."
git commit -m "✨ v2.0: Visualisation docs, FAQ moderne, graphiques améliorés, mot de passe auto

Features:
- 👁️ Visualisation et modification documents (modals)
- 🎨 Interface FAQ modernisée avec gradients et animations
- 📊 Graphiques Chart.js améliorés (tooltips, gradients)
- 📈 Nouveau graphique en ligne pour tokens (dashboard + section tokens)
- 🔐 Génération automatique mot de passe (format Word@Word242)
- 📧 Configuration email Brevo SMTP

Backend:
- Nouveau endpoint GET /admin/docs/{id}
- Méthode generateProPassword() dans ClientController
- Config Brevo dans .env.example

Frontend:
- Composant TokenLineChart (graphique ligne)
- Modals visualisation/édition dans DocTabPanel
- Formulaire FAQ avec design orange gradient
- NewClientForm simplifié (nom + email uniquement)

Docs:
- AMELIORATIONS.md (documentation complète)
- GUIDE_RAPIDE.md (guide déploiement)
- CHANGELOG.md (historique versions)
- DEPLOY.sh (script automatique)
"

if [ $? -eq 0 ]; then
    echo "✅ Commit créé avec succès"
else
    echo "⚠️  Erreur lors du commit (peut-être aucun changement?)"
    exit 1
fi

echo ""
echo "🔄 Push vers GitHub..."
git push origin main

if [ $? -eq 0 ]; then
    echo "✅ Push réussi vers GitHub"
    echo ""
    echo "🎯 Prochaines étapes:"
    echo "================================"
    echo ""
    echo "1️⃣  RENDER (Backend)"
    echo "   → Dashboard: https://dashboard.render.com"
    echo "   → Aller dans votre service"
    echo "   → Vérifier que le redéploiement automatique a démarré"
    echo "   → Temps estimé: 3-5 minutes"
    echo ""
    echo "2️⃣  VARIABLES D'ENVIRONNEMENT RENDER"
    echo "   → Environment → Ajouter/Vérifier:"
    echo "   MAIL_HOST=smtp-relay.brevo.com"
    echo "   MAIL_PORT=587"
    echo "   MAIL_USERNAME=b3ef1e001@smtp-brevo.com"
    echo "   MAIL_PASSWORD=xsmtpsib-VOTRE_CLE_SMTP_BREVO_ICI"
    echo "   MAIL_ENCRYPTION=tls"
    echo "   MAIL_FROM_ADDRESS=mahamouddjanta1@gmail.com"
    echo "   MAIL_FROM_NAME=\"Support IA\""
    echo ""
    echo "3️⃣  VERCEL (Frontend)"
    echo "   → Dashboard: https://vercel.com/dashboard"
    echo "   → Le déploiement devrait démarrer automatiquement"
    echo "   → Temps estimé: 2-3 minutes"
    echo ""
    echo "4️⃣  TESTS POST-DÉPLOIEMENT"
    echo "   ✓ Créer un client (admin) → Vérifier email reçu"
    echo "   ✓ Visualiser un document → Modal s'ouvre"
    echo "   ✓ Modifier un document → Sauvegarde OK"
    echo "   ✓ Créer une FAQ → Design orange visible"
    echo "   ✓ Dashboard → Graphiques s'affichent"
    echo "   ✓ Section Tokens → Graphique ligne visible"
    echo ""
    echo "📚 Documentation disponible:"
    echo "   - AMELIORATIONS.md (détails complets)"
    echo "   - GUIDE_RAPIDE.md (guide rapide)"
    echo "   - CHANGELOG.md (historique)"
    echo ""
    echo "🎉 Déploiement lancé avec succès!"
    echo ""
else
    echo "❌ Erreur lors du push vers GitHub"
    echo "Vérifiez votre connexion et vos droits d'accès"
    exit 1
fi
