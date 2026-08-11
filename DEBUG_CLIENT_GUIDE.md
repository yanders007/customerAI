# 🔍 Guide de Debug Client

## 🎯 Problèmes résolus aujourd'hui

### ✅ Backend
1. **Import N8nService manquant** → `use App\Services\N8nService;` ajouté
2. **Architecture IA corrigée** → N8nService pour prod, AiService pour tests
3. **Statut temps réel** → `last_seen`, `isOnline()`, heartbeat
4. **CSRF Sanctum retiré** → Pas nécessaire avec sessions Laravel

### ✅ Frontend
1. **Logs debug ajoutés** → Admin + Client pour diagnostiquer
2. **Sessions cross-domain** → `withCredentials: true` configuré

---

## 🧪 Tests à faire maintenant

### 1. Login Admin ✅
**Déjà testé et fonctionne !**

### 2. Login Client 🔍
1. Ouvrir la console (F12)
2. Aller sur `/login-client`
3. Se connecter avec identifiant client
4. **Vérifier les logs** :
   ```
   [ClientLogin] Vérification session existante...
   [ClientLogin] Pas de session active 401
   [ClientLogin] Tentative de connexion... {identifier: "CLIENT-XXXXXX"}
   [API] POST /client/login
   [API] ✓ POST /client/login 200
   [ClientLogin] Connexion réussie
   ```

5. **Si problème, vérifier** :
   - Cookie `laravel_session` présent ?
   - Erreur CORS ?
   - Erreur 401 sur `/client/me` ?

### 3. Sélection Projet 🔍
1. Après login client → redirigé vers `/projects`
2. **Logs attendus** :
   ```
   [ProjectSelect] Chargement projets...
   [API] GET /client/me
   [API] GET /client/projets
   [API] ✓ GET /client/me 200
   [API] ✓ GET /client/projets 200
   [ProjectSelect] Authentifié: NomClient
   [ProjectSelect] Projets: 2
   ```

3. Cliquer sur un projet → redirigé vers `/chat`

### 4. Chat Client 🔍
1. **Logs attendus** :
   ```
   [Chat] Initialisation...
   [API] GET /client/me
   [API] ✓ GET /client/me 200
   [Chat] Authentifié: NomClient
   [Chat] Projet: NomProjet
   [API] GET /client/conversations
   [API] ✓ GET /client/conversations 200
   ```

2. **Poser une question**
3. Vérifier que l'IA répond (via N8N)

---

## ⚠️ Problèmes possibles et solutions

### Problème 1 : Boucle de redirection client
**Symptômes** :
- Client redirigé vers `/login-client` en boucle
- Cookie `laravel_session` manquant

**Solutions** :
1. Vérifier `.env` Vercel : `VITE_API_URL` correct ?
2. Vérifier `.env` Render : `FRONTEND_URL` correct ?
3. Vérifier cookies dans DevTools → Application → Cookies
4. Désactiver temporairement le bloqueur de cookies tiers

### Problème 2 : Erreur 401 sur `/client/me`
**Symptômes** :
- Log `[API] ✗ GET /client/me 401`
- Client redirigé vers login

**Causes** :
- Session expirée ou invalide
- Cookie non envoyé (CORS)

**Solutions** :
1. Vérifier que `withCredentials: true` dans api.js ✅
2. Vérifier CORS backend : `supports_credentials: true` ✅
3. Re-login client

### Problème 3 : Aucun projet disponible
**Symptômes** :
- Message "Aucun projet disponible"
- `[ProjectSelect] Projets: 0`

**Solutions** :
1. Vérifier en DB : `SELECT * FROM projets WHERE client_id = X`
2. Créer un projet depuis l'admin
3. Lier le projet au client

### Problème 4 : Chat ne charge pas
**Symptômes** :
- Redirigé vers `/projects`
- Log `[Chat] Aucun projet sélectionné`

**Causes** :
- Projet non sauvegardé dans localStorage
- Session perdue

**Solutions** :
1. Re-sélectionner un projet depuis `/projects`
2. Vérifier localStorage → `sia_session` contient `project`

### Problème 5 : IA ne répond pas
**Symptômes** :
- Question envoyée mais pas de réponse
- Erreur 500 backend

**Diagnostic** :
1. Vérifier logs Render
2. Chercher erreur N8N
3. Vérifier configuration IA admin

**Solutions possibles** :
- N8N non démarré → Vérifier supervisord
- Clé API invalide → Tester dans admin
- Workflow N8N cassé → Vérifier `final-client.json`

---

## 📊 Architecture actuelle

```
┌─────────────────────────────────────────────────┐
│          FRONTEND (Vercel)                       │
│  ClientLogin → ProjectSelect → Chat              │
│  • axios withCredentials: true ✅               │
│  • Logs debug dans console ✅                   │
└────────────────┬────────────────────────────────┘
                 │ HTTPS + Cookies
                 ↓
┌─────────────────────────────────────────────────┐
│          BACKEND (Render)                        │
│  ClientAuthController                            │
│  ├─ login() → Session + last_login/last_seen    │
│  ├─ me() → Retourne client + projet             │
│  └─ heartbeat() → Update last_seen              │
│                                                   │
│  ProjectController                               │
│  ├─ index() → Liste projets du client           │
│  └─ select() → Stocke projet en session         │
│                                                   │
│  AskController                                   │
│  └─ __invoke() → Questions via N8nService       │
└────────────────┬────────────────────────────────┘
                 │ HTTP localhost:5678
                 ↓
          N8N (même container)
          Workflow: final-client.json
```

---

## 🔧 Commandes utiles

### Vérifier les logs Render
```bash
# Dans le dashboard Render → Logs
# Chercher erreurs 500 ou exceptions
```

### Vérifier les cookies
```javascript
// Console navigateur
document.cookie.split('; ').forEach(c => console.log(c));
// Chercher : laravel_session=...
```

### Vérifier localStorage
```javascript
// Console navigateur
JSON.parse(localStorage.getItem('sia_session'));
// Doit contenir : {role: 'client', data: {...}, project: {...}}
```

### Forcer refresh session
```javascript
// Console navigateur
localStorage.removeItem('sia_session');
location.reload();
```

---

## ✅ Checklist de vérification complète

- [ ] **Backend déployé** sur Render (sans erreurs)
- [ ] **Frontend déployé** sur Vercel
- [ ] **Variables env Vercel** : `VITE_API_URL` correct
- [ ] **Variables env Render** : `FRONTEND_URL` correct
- [ ] **Login admin** fonctionne ✅
- [ ] **Login client** fonctionne (vérifier logs)
- [ ] **Sélection projet** fonctionne (vérifier logs)
- [ ] **Chat** charge correctement (vérifier logs)
- [ ] **Question IA** reçoit réponse via N8N
- [ ] **Heartbeat** client (optionnel, à implémenter frontend)
- [ ] **Statut en ligne** visible dans admin clients

---

## 🆘 Si rien ne fonctionne

1. **Vérifier les logs Render** → Chercher stack traces
2. **Vérifier la console navigateur** → Chercher erreurs JS
3. **Tester l'API directement** avec curl/Postman
4. **Vérifier la DB** → Tables `clients`, `projets`, `sessions`

### Test API Login
```bash
curl -X POST https://ton-backend.onrender.com/api/client/login \
  -H "Content-Type: application/json" \
  -d '{"client_identifier": "CLIENT-XXXXXX", "password": "MotDePasse"}' \
  -v
```

### Test API Me (avec cookie)
```bash
curl -X GET https://ton-backend.onrender.com/api/client/me \
  -H "Cookie: laravel_session=XXXXX" \
  -v
```

---

**🎉 Tout est maintenant loggé pour faciliter le debug !**

Les logs te diront exactement où ça bloque :
- `[ClientLogin]` → Problème de connexion
- `[ProjectSelect]` → Problème de chargement projets
- `[Chat]` → Problème d'initialisation chat
- `[API]` → Problème de requête HTTP
