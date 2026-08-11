# 🚀 Instructions de Push Final

## 📦 Commits à pousser

**1 commit en attente** :

```bash
92eedec - 🐛 Fix: Redirection boucle login admin
```

## 🔧 Corrections apportées

### Backend ✅ (déjà poussé)
- ✅ Architecture IA : N8nService (prod) + AiService (tests)
- ✅ RAG optimisé : 30% meilleurs chunks
- ✅ Statut temps réel : last_seen, isOnline(), heartbeat
- ✅ Workflow N8N : API key dynamique
- ✅ Historique 5 derniers messages ✅

### Frontend 🆕 (ce commit)
- ✅ Supprimé CSRF Sanctum (non utilisé avec sessions Laravel)
- ✅ Ajouté logs debug pour diagnostiquer
- ✅ Sessions fonctionnent avec cookies PHPSESSID

## 🚀 Commande de push

```bash
cd ~/Images/github/customerAI
git push origin main
```

**Token GitHub** : Utilise ton token personnel (voir instructions précédentes)

---

## 🧪 Tests après déploiement

### 1. Test Configuration IA Admin ✅
1. Aller sur `https://ton-frontend.vercel.app/login-admin`
2. Se connecter (vérifier les logs console)
3. Aller sur "Configuration IA"
4. Configurer Gemini avec clé API
5. Tester connexion → Devrait retourner un message

### 2. Test Login Admin (correction boucle) 🆕
1. Ouvrir la console navigateur (F12)
2. Se connecter admin
3. **Vérifier les logs** :
   ```
   [AdminLogin] Tentative de connexion...
   [API] POST /admin/login
   [API] ✓ POST /admin/login 200
   [AdminLogin] Connexion réussie
   [AdminPanel] Vérification authentification...
   [API] GET /admin/me
   [API] ✓ GET /admin/me 200
   [AdminPanel] Authentifié: admin@example.com
   ```
4. **Si boucle persiste**, vérifier :
   - Cookie `laravel_session` présent dans DevTools → Application → Cookies
   - Variable d'environnement Vercel : `VITE_API_URL` = URL backend Render

### 3. Test Chatbot Client
1. Se connecter comme client
2. Poser une question
3. Vérifier que la réponse vient de N8N (avec historique si conversation existante)

### 4. Test Statut en ligne 🆕
1. Se connecter comme client
2. Dans un autre onglet, ouvrir admin `/admin/clients`
3. Le client devrait avoir badge "🟢 En ligne"
4. Fermer l'onglet client
5. Attendre 2 minutes
6. Rafraîchir admin → badge devrait passer à "⚫ Hors ligne"

**Note** : Pour que le statut temps réel fonctionne, il faut implémenter le heartbeat côté frontend client (toutes les 30s).

---

## ⚠️ Si problème de cookies persiste

### Diagnostic
1. Ouvrir DevTools → Console
2. Vérifier les logs `[API]` et `[AdminLogin]`
3. Ouvrir DevTools → Application → Cookies
4. Vérifier que `laravel_session` existe après login

### Causes possibles
1. **CORS mal configuré** :
   - Vérifier `.env` backend : `FRONTEND_URL=https://ton-frontend.vercel.app`
   - Vérifier `.env` frontend Vercel : `VITE_API_URL=https://ton-backend.onrender.com`

2. **SESSION_DOMAIN incorrect** :
   - Backend `.env` : `SESSION_DOMAIN=null` (pas de domaine spécifique)

3. **Navigateur bloque cookies tiers** :
   - Safari/Firefox : Paramètres → Bloquer cookies tiers (désactiver)
   - Chrome : Settings → Privacy → Allow all cookies (temporairement)

4. **HTTPS non actif** :
   - Vérifier que backend ET frontend sont en HTTPS
   - Render et Vercel font ça automatiquement ✅

### Solution de secours : Même domaine
Si les cookies cross-domain ne fonctionnent toujours pas, tu peux :
1. Configurer un domaine custom (ex: `api.monapp.com` et `app.monapp.com`)
2. Ou déployer frontend et backend sur même domaine (Render Web Service + Static Site)

---

## 📊 Architecture finale

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND (Vercel)                     │
│  • React + Vite                                              │
│  • axios withCredentials: true                               │
│  • Heartbeat client: POST /client/heartbeat (30s)           │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTPS + Cookies (laravel_session)
                     ↓
┌─────────────────────────────────────────────────────────────┐
│                      BACKEND (Render)                        │
│  Laravel 11 + PHP-FPM + Nginx                                │
│  ├─ AdminAuthController → Sessions PHPSESSID                 │
│  ├─ ClientAuthController → Heartbeat + last_seen            │
│  ├─ AiConfigController → Tests IA (AiService direct)        │
│  └─ AskController → Questions clients (N8nService webhook)  │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTP localhost:5678
                     ↓
┌─────────────────────────────────────────────────────────────┐
│                    N8N (même container)                      │
│  • Workflow: final-client.json                               │
│  • Webhook: /webhook/assistant                               │
│  • Reçoit: api_key, model, question, doc, faq, history      │
│  • Retourne: answer, tokens_input, tokens_output            │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTPS API
                     ↓
              Gemini API (Google)
```

---

## 📝 Fichiers modifiés (ce commit)

- `frontend/src/api.js` : Retiré CSRF Sanctum, ajouté logs
- `frontend/src/pages.jsx` : Ajouté logs debug AdminLogin + AdminPanel

---

## ✅ Checklist finale

- [ ] Push vers GitHub
- [ ] Vérifier déploiement Render (backend)
- [ ] Vérifier déploiement Vercel (frontend)
- [ ] Migration `add_last_seen_to_clients_table` exécutée sur Render
- [ ] Test login admin (vérifier logs console)
- [ ] Test configuration IA
- [ ] Test question client
- [ ] (Optionnel) Implémenter heartbeat frontend client

---

**🎉 Tout est prêt pour le push !**
