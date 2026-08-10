# ✅ Checklist de Déploiement - CustomerAI v2.0

## Avant de Déployer

- [ ] J'ai lu `AMELIORATIONS.md` pour comprendre les nouvelles fonctionnalités
- [ ] J'ai lu `GUIDE_RAPIDE.md` pour le processus de déploiement
- [ ] J'ai vérifié que tous les fichiers sont présents dans le repo

---

## 📦 Déploiement Git

### Commit et Push
- [ ] Tous les fichiers modifiés sont ajoutés (`git add .`)
- [ ] Commit créé avec message descriptif
- [ ] Push vers GitHub réussi (`git push origin main`)

**Commande rapide** :
```bash
chmod +x DEPLOY.sh
./DEPLOY.sh
```

---

## 🔧 Configuration Backend (Render)

### Variables d'Environnement
- [ ] `MAIL_HOST` = `smtp-relay.brevo.com`
- [ ] `MAIL_PORT` = `587`
- [ ] `MAIL_USERNAME` = `b3ef1e001@smtp-brevo.com`
- [ ] `MAIL_PASSWORD` = `xsmtpsib-VOTRE_CLE_SMTP_BREVO_ICI`
- [ ] `MAIL_ENCRYPTION` = `tls`
- [ ] `MAIL_FROM_ADDRESS` = `mahamouddjanta1@gmail.com`
- [ ] `MAIL_FROM_NAME` = `Support IA`

### Redéploiement
- [ ] Service Render a redémarré automatiquement après push GitHub
- [ ] Logs Render sans erreurs critiques
- [ ] API répond sur `https://[votre-app].onrender.com/api/admin/me`

---

## 🎨 Configuration Frontend (Vercel)

### Déploiement
- [ ] Build Vercel réussi (statut "Ready")
- [ ] Temps de build < 5 minutes
- [ ] URL Vercel accessible
- [ ] Console navigateur sans erreurs JavaScript

---

## 🧪 Tests Fonctionnels

### 1. Création de Client avec Mot de Passe Auto
- [ ] Admin → Clients → "+" → Remplir Nom + Email
- [ ] Formulaire affiche message "Un mot de passe sécurisé sera généré"
- [ ] Cliquer "✨ Créer et envoyer"
- [ ] Message "Client créé et identifiants envoyés par email"
- [ ] Email reçu avec mot de passe format `Word@Word42`
- [ ] Connexion client fonctionne avec le mot de passe reçu

### 2. Visualisation de Document
- [ ] Admin → Clients → Projet → Docs
- [ ] Cliquer "Voir" sur une card de document
- [ ] Modal s'ouvre avec contenu complet
- [ ] Header affiche titre + projet
- [ ] Bouton "Modifier" visible
- [ ] Bouton "Fermer" fonctionne

### 3. Modification de Document
- [ ] Depuis modal de visualisation, cliquer "Modifier"
- [ ] Modal d'édition s'ouvre
- [ ] Champs titre et contenu éditables
- [ ] Modifier le texte
- [ ] Cliquer "Enregistrer les modifications"
- [ ] Message "Document mis à jour et ré-indexé !"
- [ ] Changements visibles dans la liste

### 4. Création de FAQ Moderne
- [ ] Docs → FAQ d'une documentation
- [ ] Bouton orange gradient "Ajouter une nouvelle FAQ" visible
- [ ] Cliquer → Formulaire se déplie avec fond orange
- [ ] Icônes Font Awesome présentes (sparkles, question, comment-dots)
- [ ] Remplir question et réponse
- [ ] Cliquer "✨ Ajouter cette FAQ"
- [ ] FAQ ajoutée avec card numérotée

### 5. Graphiques Dashboard
- [ ] Admin → Dashboard
- [ ] **Graphique Barres** : Input/Output tokens s'affiche
- [ ] **Graphique Donut** : Sources RAG avec total au centre
- [ ] **Graphique Ligne** : "Évolution des Tokens" visible sous les 2 premiers
- [ ] Tooltips formatés avec séparateurs de milliers
- [ ] Hover sur graphiques fonctionne

### 6. Graphiques Section Tokens
- [ ] Admin → Tokens & RAG
- [ ] Graphiques Barres + Donut présents
- [ ] **Graphique Ligne** : "Tendance en Temps Réel" visible
- [ ] Badge "Mise à jour en direct" avec animation pulse verte
- [ ] Gradient header violet/indigo visible
- [ ] Sélecteur "7 jours / 30 jours" fonctionne

### 7. Interface Générale
- [ ] Hover effects sur les cards (transform + box-shadow)
- [ ] Animations fluides (transitions 0.2s)
- [ ] Gradients sur boutons FAQ (orange/ambre)
- [ ] Colors cohérentes (Indigo, Vert, Orange, Rouge)
- [ ] Responsive sur mobile (grilles auto-fill)

---

## 🐛 Résolution de Problèmes

### Email Non Reçu
- [ ] Vérifier spam/indésirables
- [ ] Vérifier variables MAIL_* dans Render Environment
- [ ] Vérifier logs Render pour erreurs SMTP
- [ ] Tester avec un autre email

### Graphiques Ne S'affichent Pas
- [ ] Vérifier console navigateur (F12)
- [ ] Vérifier que Chart.js est chargé dans `index.html`
- [ ] Vérifier API `/admin/dashboard` retourne `tokens.week_data`
- [ ] Clear cache navigateur (Ctrl+Shift+R)

### Modal Document Ne S'ouvre Pas
- [ ] Vérifier console navigateur pour erreur 404
- [ ] Vérifier route `GET /admin/docs/{id}` existe (logs Render)
- [ ] Backend bien redéployé après modifications

### Formulaire Client Demande Mot de Passe
- [ ] Frontend bien redéployé sur Vercel
- [ ] Clear cache navigateur
- [ ] Vérifier console pour erreur API

---

## 📊 Métriques de Succès

### Performance
- [ ] Backend cold start < 30 secondes
- [ ] Frontend load time < 2 secondes
- [ ] API response time < 500ms (hors cold start)

### Qualité
- [ ] Aucune erreur dans console navigateur
- [ ] Aucune erreur critique dans logs Render
- [ ] Emails délivrés en < 10 secondes

### Fonctionnel
- [ ] 9/9 tests fonctionnels passés
- [ ] Toutes les animations fonctionnent
- [ ] Tous les graphiques s'affichent

---

## 📸 Screenshots à Prendre (pour Documentation)

- [ ] Dashboard avec les 3 graphiques (barres, donut, ligne)
- [ ] Section Tokens avec graphique ligne + badge pulse
- [ ] Modal visualisation document
- [ ] Modal édition document
- [ ] Formulaire FAQ avec design orange
- [ ] Card FAQ créée (numérotée)
- [ ] Formulaire création client (simplifié)
- [ ] Email reçu avec mot de passe

---

## 🎯 Validation Finale

### Critères de Réussite
- [x] Backend déployé et accessible
- [x] Frontend déployé et accessible
- [x] Emails Brevo configurés
- [x] 9/9 tests fonctionnels passés
- [x] Aucun bug critique
- [x] Documentation complète

### Livrables
- [x] Code source sur GitHub
- [x] Backend sur Render
- [x] Frontend sur Vercel
- [x] Documentation (AMELIORATIONS.md, GUIDE_RAPIDE.md, CHANGELOG.md)
- [x] Script déploiement (DEPLOY.sh)

---

## 🎉 C'est Terminé !

Si toutes les cases sont cochées, votre CustomerAI v2.0 est :
- ✅ **Déployé** sur production
- ✅ **Fonctionnel** avec toutes les nouvelles fonctionnalités
- ✅ **Documenté** complètement
- ✅ **Testé** et validé

**Félicitations ! 🚀**

---

## 📞 Support

### Documentation
- `README.md` - Vue d'ensemble et installation
- `AMELIORATIONS.md` - Détails techniques des fonctionnalités
- `GUIDE_RAPIDE.md` - Guide de déploiement rapide
- `CHANGELOG.md` - Historique des versions

### Logs
- **Backend** : Render Dashboard → Service → Logs
- **Frontend** : Vercel Dashboard → Project → Deployments → Build Logs
- **Base de données** : Supabase Dashboard → SQL Editor

### Contacts Utiles
- Documentation Laravel : https://laravel.com/docs
- Documentation React : https://react.dev
- Documentation Chart.js : https://www.chartjs.org/docs
- Support Render : https://render.com/docs
- Support Vercel : https://vercel.com/docs

---

*Version 2.0 - Août 2026*
*Tous droits réservés © CustomerAI*
