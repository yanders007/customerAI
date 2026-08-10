# 🚀 Guide Rapide - CustomerAI v2.0

## Configuration Email Brevo (Urgent !)

### 1. Mettre à jour le fichier `.env` du backend

Ajoutez ou remplacez ces lignes dans `/backend/.env` :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=b3ef1e001@smtp-brevo.com
MAIL_PASSWORD=xsmtpsib-VOTRE_CLE_SMTP_BREVO_ICI
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=mahamouddjanta1@gmail.com
MAIL_FROM_NAME="Support IA"
```

### 2. Sur Render (Variables d'environnement)

Allez dans votre service Render → Environment → Ajoutez :

| Variable | Valeur |
|----------|--------|
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | `b3ef1e001@smtp-brevo.com` |
| `MAIL_PASSWORD` | `xsmtpsib-VOTRE_CLE_SMTP_BREVO_ICI` |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_FROM_ADDRESS` | `mahamouddjanta1@gmail.com` |
| `MAIL_FROM_NAME` | `Support IA` |

Puis **Save Changes** → Le service redémarrera automatiquement.

---

## Tester les Nouvelles Fonctionnalités

### 1. Création de Client avec Mot de Passe Auto

**Étapes** :
1. Connectez-vous en tant qu'admin
2. Onglet **Clients**
3. Cliquer sur **"+ Ajouter un Client"**
4. Remplir uniquement :
   - Nom : `Test Client`
   - Email : `votre-email-test@gmail.com`
5. Cliquer **"✨ Créer et envoyer"**

**Résultat attendu** :
- ✅ Message : "Client créé et identifiants envoyés par email"
- ✅ Email reçu avec mot de passe type `Secure@Network47`
- ✅ Client visible dans la liste

### 2. Visualiser et Modifier un Document

**Étapes** :
1. Admin → **Clients** → Sélectionner un client
2. Cliquer sur **"Nouveau projet"** ou sélectionner un existant
3. Onglet **Docs**
4. Sur une card de document, cliquer **"Voir"**
   - → Modal s'ouvre avec le contenu complet
5. Cliquer **"Modifier"** dans la modal
   - → Éditeur s'ouvre avec titre + contenu
6. Modifier le texte
7. Cliquer **"Enregistrer les modifications"**

**Résultat attendu** :
- ✅ Modal de visualisation moderne
- ✅ Édition inline fonctionnelle
- ✅ Message : "Document mis à jour et ré-indexé !"

### 3. Créer une FAQ avec le Nouveau Design

**Étapes** :
1. Dans un projet → Docs → Sélectionner une documentation
2. Onglet **FAQ**
3. Cliquer sur le **bouton orange gradient** "Ajouter une nouvelle FAQ"
4. Formulaire se déplie avec fond orange
5. Remplir :
   - Question : `Comment réinitialiser mon compte ?`
   - Réponse : `Cliquez sur "Mot de passe oublié" sur la page de connexion.`
6. Cliquer **"✨ Ajouter cette FAQ"**

**Résultat attendu** :
- ✅ Bouton avec gradient orange/ambre
- ✅ Formulaire avec icônes et animations
- ✅ FAQ ajoutée avec card numérotée

### 4. Voir les Graphiques Améliorés

**Dashboard** :
1. Admin → **Dashboard** (ou **Tableau de bord**)
2. Scroller vers le bas
3. Observer les graphiques :
   - **Barres** : Tokens input/output avec tooltips formatés
   - **Donut** : Sources RAG avec total au centre
   - **Ligne** : Nouveau graphique "Évolution des Tokens" avec gradient

**Section Tokens** :
1. Admin → **Tokens & RAG**
2. Observer les graphiques :
   - Barres + Donut (comme dashboard)
   - **Nouveau graphique en ligne** : "Tendance en Temps Réel" avec badge pulse vert

**Résultat attendu** :
- ✅ Graphiques avec animations fluides
- ✅ Tooltips améliorés avec bordures
- ✅ Gradient bleu sur graphique ligne
- ✅ Badge "Mise à jour en direct" avec animation pulse

---

## Vérifications Importantes

### Backend (Render)

```bash
# Vérifier les logs après redémarrage
# Dans Render Dashboard → Logs

# Rechercher :
✅ "Application key set successfully"
✅ "Server started on port 8000" (ou votre port)
❌ Erreurs SMTP (si présent, vérifier config email)
```

### Frontend (Vercel)

```bash
# Vérifier le build
# Dans Vercel Dashboard → Deployments → Dernier déploiement

# Statut attendu :
✅ "Ready" (avec icône verte)
✅ "Build successful"
```

### Base de Données (Supabase)

```sql
-- Vérifier que les clients sont créés correctement
SELECT name, email, client_identifier, created_at 
FROM clients 
ORDER BY created_at DESC 
LIMIT 5;
```

---

## Déploiement Git

### Backend + Frontend ensemble

```bash
# Depuis la racine du projet
cd /home/mahamoud/Images/github/customerAI

# Ajouter tous les changements
git add .

# Commit avec message descriptif
git commit -m "✨ v2.0: Docs visualisation, FAQ moderne, graphiques améliorés, mot de passe auto, Brevo email"

# Push vers GitHub
git push origin main
```

### Render redéploiera automatiquement le backend
### Vercel redéploiera automatiquement le frontend

**Temps estimé** : 3-5 minutes

---

## Résolution de Problèmes

### Email non reçu après création client

**Vérifications** :
1. ✅ Variables `MAIL_*` bien configurées dans Render
2. ✅ Email dans spam/indésirables
3. ✅ Logs Render pour erreurs SMTP
4. ✅ Quota Brevo non dépassé (300 emails/jour gratuit)

**Solution** :
```bash
# Dans Render Dashboard → Environment
# Vérifier que MAIL_PASSWORD contient bien :
xsmtpsib-VOTRE_CLE_SMTP_BREVO_ICI
```

### Graphiques ne s'affichent pas

**Vérifications** :
1. ✅ Chart.js chargé dans `index.html`
2. ✅ Console navigateur pour erreurs JavaScript
3. ✅ API `/admin/dashboard` retourne bien `tokens.week_data` et `tokens.month30_data`

**Solution** :
```html
<!-- Vérifier dans frontend/index.html -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

### Modal document ne s'ouvre pas

**Vérifications** :
1. ✅ Route API `GET /admin/docs/{id}` existe
2. ✅ Console navigateur pour erreur 404
3. ✅ Backend bien redéployé avec nouvelle route

**Solution** :
```bash
# Vérifier dans backend/routes/api.php
Route::get('/docs/{id}', [DocsController::class, 'show']);
```

---

## Checklist Finale

Avant de considérer le déploiement terminé :

- [ ] Backend redéployé sur Render
- [ ] Frontend redéployé sur Vercel
- [ ] Variables Brevo configurées dans Render
- [ ] Test création client → Email reçu
- [ ] Test visualisation document → Modal s'ouvre
- [ ] Test modification document → Sauvegarde OK
- [ ] Test création FAQ → Design orange visible
- [ ] Graphiques dashboard s'affichent
- [ ] Graphique ligne visible (dashboard + tokens)
- [ ] Badge pulse "Temps réel" animé

---

## Support

### Logs utiles

**Backend (Render)** :
```
Dashboard → Service → Logs
```

**Frontend (Vercel)** :
```
Dashboard → Project → Deployments → [Latest] → View Build Logs
```

**Base de données (Supabase)** :
```
Dashboard → Project → Database → Query Editor
```

---

## Commandes Utiles

### Tester l'API backend localement

```bash
# Créer un client (test mot de passe auto)
curl -X POST http://localhost:8000/api/admin/clients \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com"}'

# Voir un document
curl http://localhost:8000/api/admin/docs/1

# Récupérer stats dashboard
curl http://localhost:8000/api/admin/dashboard
```

### Vérifier les emails en local (MailHog)

Si vous testez en local avec MailHog :
```bash
# Lancer MailHog
mailhog

# Ouvrir http://localhost:8025
# Les emails envoyés apparaîtront ici
```

---

## 🎉 C'est Terminé !

Votre plateforme CustomerAI v2.0 est maintenant complète avec :
- ✨ Interface moderne
- 📊 Graphiques professionnels
- 👁️ Visualisation documents
- 🎨 FAQ design
- 🔐 Mots de passe auto
- 📧 Emails Brevo

**Bon déploiement ! 🚀**
