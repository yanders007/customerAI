# 📝 Changelog - CustomerAI

## [2.0.0] - 2026-08-11

### ✨ Nouvelles Fonctionnalités

#### 👁️ Visualisation et Modification des Documents
- Ajout d'une modal de visualisation complète des documents
- Édition inline avec sauvegarde et ré-indexation automatique
- Interface carte modernisée avec grille responsive
- Boutons d'action : Voir / Modifier / FAQ / Supprimer
- Design avec hover effects et animations

#### 🎨 Interface FAQ Modernisée
- Bouton d'ajout avec gradient orange/ambre
- Formulaire dépliable avec animations
- Cards FAQ numérotées avec badges
- Édition inline depuis la liste
- Icônes Font Awesome pour meilleure UX
- Animations au survol (transform + box-shadow)

#### 📊 Graphiques Améliorés
- **Graphique Barres** : Tooltips formatés, labels avec séparateurs de milliers
- **Graphique Donut** : Centre avec total animé, pourcentages dans légende
- **Nouveau Graphique Ligne** : Courbe lissée avec gradient de remplissage
- Points interactifs avec animations
- Tooltips améliorés avec bordures et arrondis

#### 📈 Graphique Tokens en Temps Réel
- Graphique en ligne ajouté au dashboard principal
- Graphique en ligne dans la section "Tokens & RAG"
- Badge animé "Mise à jour en direct" avec pulse CSS
- Gradient de remplissage rgba avec transition
- Format Y-axis intelligent (1000 → 1.0k)

#### 🔐 Génération Automatique de Mot de Passe
- Nouvelle méthode `generateProPassword()` dans `ClientController`
- Format professionnel : `Word@Word242` (ex: `Secure@Network47`)
- 24 mots disponibles + 7 symboles + 2 chiffres
- Longueur : 15-20 caractères
- Mémorisable mais sécurisé
- Formulaire admin simplifié : nom + email uniquement

### 🔧 Améliorations Backend

#### API REST
- Ajout endpoint `GET /admin/docs/{id}` pour visualisation document
- Méthode `show()` dans `DocsController` avec relation projet
- Retour JSON structuré avec métadonnées complètes

#### Email
- Configuration Brevo (Sendinblue) dans `.env.example`
- Paramètres SMTP : `smtp-relay.brevo.com:587` avec TLS
- Template email existant utilisé pour envoi credentials

#### Sécurité
- Validation formulaires renforcée
- Mot de passe auto-généré côté serveur uniquement
- Pas de transmission mot de passe en clair (sauf email)

### 🎨 Améliorations Frontend

#### Composants React
- `TokenLineChart` : Nouveau composant graphique ligne
- `DocTabPanel` : Modals visualisation + édition
- `FaqTabPanel` : Design modernisé avec formulaire dépliable
- `NewClientForm` : Simplifié (2 champs au lieu de 3)

#### Design System
- Gradients : `linear-gradient(135deg, ...)` sur boutons FAQ
- Animations : `translateY`, `box-shadow`, `pulse`
- Border-radius standardisés : 12-16px (cards), 8-10px (inputs)
- Couleurs cohérentes : Indigo, Vert, Orange, Rouge, Violet

#### Interactions
- Hover effects sur toutes les cards
- Focus states sur inputs avec border-color change
- Transitions fluides (0.2s)
- Loading states avec spinners

### 🐛 Corrections de Bugs

- ✅ Configuration email Resend → Brevo
- ✅ Génération mot de passe faible → Format professionnel
- ✅ Pas de visualisation document → Modal complète
- ✅ Graphiques basiques → Chart.js amélioré

### 📚 Documentation

- `AMELIORATIONS.md` : Documentation complète des fonctionnalités
- `GUIDE_RAPIDE.md` : Guide de déploiement et tests
- `CHANGELOG.md` : Historique des versions

### 🔄 Fichiers Modifiés

#### Backend
```
backend/.env.example
backend/app/Http/Controllers/Api/Admin/ClientController.php
backend/app/Http/Controllers/Api/Admin/DocsController.php
backend/routes/api.php
```

#### Frontend
```
frontend/src/pages.jsx
```

#### Documentation
```
AMELIORATIONS.md (nouveau)
GUIDE_RAPIDE.md (nouveau)
CHANGELOG.md (nouveau)
```

### 📦 Dépendances

Aucune nouvelle dépendance ajoutée. Le projet utilise :
- **Backend** : Laravel 11, PHP 8.2+
- **Frontend** : React 18, Chart.js 4.4, DOMPurify
- **Email** : Brevo SMTP
- **Database** : PostgreSQL (Supabase)

### 🚀 Déploiement

#### Backend (Render)
1. Ajouter variables d'environnement Brevo
2. Push vers GitHub → Redéploiement automatique
3. Vérifier logs pour confirmation

#### Frontend (Vercel)
1. Push vers GitHub → Redéploiement automatique
2. Build time : ~2-3 minutes
3. Vérifier console navigateur pour erreurs Chart.js

### ⚠️ Breaking Changes

- **Formulaire création client** : Le champ `password` n'est plus accepté par l'API
- **Endpoint docs** : Nouvelle route `GET /admin/docs/{id}` requise pour visualisation

### 🔜 Prochaines Améliorations Suggérées

- [ ] Export PDF des documents depuis la modal
- [ ] Historique des modifications de documents
- [ ] Recherche full-text dans les documents
- [ ] Filtres avancés dans la liste des FAQs
- [ ] Dark mode pour l'interface admin
- [ ] Notifications push pour escalades
- [ ] Statistiques par client (dashboard dédié)
- [ ] Import CSV pour création clients en masse

### 👥 Contributeurs

- Développement : Kiro AI Assistant
- Projet : Mahamoud Djanta

---

## [1.0.0] - 2026-07-XX

### 🎉 Version Initiale

- Système RAG avec embeddings Cohere
- Multi-providers AI (11 providers supportés)
- Dashboard admin avec métriques tokens
- Interface client avec chat IA
- Authentification admin/client séparée
- Escalade support humain
- Configuration n8n pour workflows
- Base de données PostgreSQL (Supabase)

---

**Format** : [Major.Minor.Patch]
- **Major** : Changements incompatibles avec versions précédentes
- **Minor** : Nouvelles fonctionnalités compatibles
- **Patch** : Corrections de bugs

**Légende** :
- ✨ Nouvelle fonctionnalité
- 🔧 Amélioration
- 🐛 Correction de bug
- 📚 Documentation
- 🔒 Sécurité
- ⚠️ Changement important
