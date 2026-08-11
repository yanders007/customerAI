#!/bin/bash

echo "🎨 Vérification du nouveau design..."
echo ""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1"
        return 0
    else
        echo -e "${RED}✗${NC} $1 manquant"
        return 1
    fi
}

check_content() {
    if grep -q "$2" "$1" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} $3"
        return 0
    else
        echo -e "${RED}✗${NC} $3"
        return 1
    fi
}

echo "📁 Fichiers CSS:"
check_file "src/styles/admin.css"
check_file "src/styles/client.css"
check_file "src/styles/login.css"
check_file "src/styles/app.css"
check_file "src/styles/design-system.css"

echo ""
echo "🎨 Nouvelles couleurs:"
check_content "src/styles/admin.css" "#2563eb" "Bleu primary (#2563eb)"
check_content "src/styles/admin.css" "#059669" "Vert success (#059669)"
check_content "src/styles/admin.css" "#f9fafb" "Gris 50 (#f9fafb)"

echo ""
echo "✏️ Font Inter:"
check_content "index.html" "Inter" "Font Inter dans index.html"
check_content "src/styles/admin.css" "Inter" "Font Inter dans CSS"

echo ""
echo "🔤 Textes modifiés:"
check_content "src/pages.jsx" "Support Client" "Support Client (au lieu de Support IA)"
check_content "src/pages.jsx" "fa-headset" "Icône headset (au lieu de robot)"
check_content "index.html" "Support Client" "Title modifié"

echo ""
echo "🎯 Anciennes références supprimées:"
if grep -q "#6366f1" "src/styles/admin.css" 2>/dev/null; then
    echo -e "${YELLOW}⚠${NC} Violet #6366f1 encore présent"
else
    echo -e "${GREEN}✓${NC} Violet #6366f1 supprimé"
fi

if grep -q "fa-robot" "src/pages.jsx" 2>/dev/null; then
    echo -e "${YELLOW}⚠${NC} Icônes robot encore présentes"
else
    echo -e "${GREEN}✓${NC} Icônes robot supprimées"
fi

echo ""
echo "📊 Résumé:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Le redesign est complet !"
echo ""
echo "Pour tester:"
echo "  npm run dev"
echo ""
echo "Pour déployer:"
echo "  npm run build"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
