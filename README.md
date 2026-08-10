# Support IA — Plateforme de support automatisé pour startups

Plateforme de support client full-stack avec IA, 100% open source et déployable gratuitement en quelques minutes.

**Stack** : React/Vite · Laravel/PHP 8.3 · PostgreSQL (Supabase) · n8n · Gemini · Cohere

---

## Architecture de déploiement

```
┌─────────────────────────────────────────────────────────┐
│                    RENDER (gratuit)                      │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │           Container Docker (port 10000)          │   │
│  │                                                  │   │
│  │  Nginx  →  PHP-FPM (Laravel API)                │   │
│  │                  ↓                               │   │
│  │             n8n :5678 (interne)                  │   │
│  │             Queue Worker (jobs)                  │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
         ↑ DB                              ↑ Frontend
┌─────────────────────┐        ┌─────────────────────────┐
│  SUPABASE (gratuit) │        │    VERCEL (gratuit)     │
│  PostgreSQL         │        │    React SPA             │
│  (Laravel + n8n)    │        │                          │
└─────────────────────┘        └─────────────────────────┘
```

> **n8n tourne à l'intérieur du container Render** — il n'est jamais exposé publiquement.
> Laravel communique avec lui via `http://localhost:5678`. La clé Gemini reste dans n8n.

---

## Déploiement complet (30 minutes)

### Étape 1 — Fork du repo

```bash
# Fork sur GitHub via l'UI, puis :
git clone https://github.com/TON_USERNAME/support-ia.git
cd support-ia
```

### Étape 2 — Supabase (base de données)

1. Créer un compte sur [supabase.com](https://supabase.com) → **New project**
2. Choisir la région la plus proche
3. Noter le mot de passe (il ne se réaffiche pas)
4. Dans **Settings → Database**, copier :
   - **Host** : `db.XXXX.supabase.co`
   - **Password** : celui que tu as noté
5. Dans **Settings → API**, copier l'URL et la clé anon (non utilisées directement, juste pour référence)

> Supabase gratuit : 500MB DB, 1GB storage, aucune carte bancaire requise.

### Étape 3 — Render (backend + n8n)

#### Option A : via Blueprint (recommandé)

1. Aller sur [render.com](https://render.com) → **New → Blueprint**
2. Connecter ton repo GitHub forké
3. Render détecte `render.yaml` automatiquement
4. Remplir les variables marquées `sync: false` dans le dashboard

#### Option B : manuellement

1. **New → Web Service** → connecter ton repo
2. **Environment** : Docker
3. **Dockerfile Path** : `./Dockerfile`
4. **Docker Context** : `.` (racine du repo)

#### Variables d'environnement à configurer dans Render

| Variable | Valeur |
|----------|--------|
| `APP_URL` | `https://TON-SERVICE.onrender.com` |
| `DB_HOST` | `db.XXXX.supabase.co` |
| `DB_PASSWORD` | Ton mot de passe Supabase |
| `FRONTEND_URL` | `https://TON-APP.vercel.app` |
| `GEMINI_API_KEY` | Clé [Google AI Studio](https://aistudio.google.com/apikey) (gratuit) |
| `COHERE_API_KEY` | Clé [Cohere](https://dashboard.cohere.com/api-keys) (gratuit) |
| `MAIL_PASSWORD` | Clé API [Resend](https://resend.com) (gratuit 3000/mois) |
| `MAIL_FROM_ADDRESS` | `noreply@tondomaine.com` |
| `SUPPORT_EMAIL` | `support@tondomaine.com` |
| `N8N_BASIC_AUTH_PASSWORD` | Mot de passe fort pour l'UI n8n |
| `N8N_ENCRYPTION_KEY` | `openssl rand -hex 32` (à fixer une fois pour toutes) |

> ⚠️ `APP_KEY` et `N8N_BASIC_AUTH_PASSWORD` peuvent être générés automatiquement par Render (`generateValue: true` dans render.yaml).

> ⚠️ **Fixe `N8N_ENCRYPTION_KEY`** à une valeur stable — sinon les credentials n8n (clé Gemini) sont perdues à chaque redéploiement.

### Étape 4 — Vercel (frontend)

1. Aller sur [vercel.com](https://vercel.com) → **New Project**
2. Importer ton repo GitHub forké
3. **Root Directory** : `frontend`
4. **Framework Preset** : Vite
5. Ajouter la variable d'environnement :
   - `VITE_API_URL` = `https://TON-SERVICE.onrender.com`
6. Deploy

### Étape 5 — Configurer n8n et importer ton workflow

#### Accéder à n8n (via Render Shell)

n8n tourne en interne dans le container. Pour accéder à son UI :

1. Dashboard Render → ton service → onglet **Shell**
2. Dans le terminal Render :
```bash
# Vérifier que n8n tourne
curl http://localhost:5678/healthz

# Lister les workflows existants
curl -u admin:TON_MOT_DE_PASSE http://localhost:5678/api/v1/workflows
```

#### Importer ton workflow n8n

**Option 1 : Via le fichier JSON (recommandé)**

```bash
# Dans le shell Render
n8n import:workflow --input=/app/n8n/workflow.json
```

**Option 2 : Modifier le repo et redéployer**

1. Exporte ton workflow depuis n8n local : **Menu ⋮ → Download**
2. Remplace `n8n/workflow.json` par ton fichier exporté
3. `git commit -am "add n8n workflow" && git push`
4. Render redéploie → le workflow est importé automatiquement

#### Configurer la clé Gemini dans n8n

Dans le shell Render, n8n peut être configuré via CLI :
```bash
# Ou accès via un tunnel SSH si tu as un compte Render payant
# En gratuit : utiliser le Shell Render intégré au dashboard
```

La clé Gemini est une **Credential n8n** (pas une variable d'env Laravel).
Elle doit être reconfigurée depuis l'UI n8n après chaque nouveau déploiement si `N8N_ENCRYPTION_KEY` n'est pas fixée.

---

## Variables d'environnement — Résumé

### Clés API gratuites à obtenir

| Service | URL | Usage |
|---------|-----|-------|
| Gemini | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | Génération de réponses IA (via n8n) + embeddings Laravel |
| Cohere | [dashboard.cohere.com/api-keys](https://dashboard.cohere.com/api-keys) | Embeddings RAG (20 req/min gratuit) |
| Resend | [resend.com](https://resend.com) | Emails (3000/mois gratuit) |
| Supabase | [supabase.com](https://supabase.com) | PostgreSQL (500MB gratuit) |
| Render | [render.com](https://render.com) | Hébergement backend (gratuit avec sleep) |
| Vercel | [vercel.com](https://vercel.com) | Hébergement frontend (gratuit) |

> **Coût total : 0€** pour un usage de démonstration/PFE

### Limitation gratuit Render

Le plan gratuit Render met le service en **veille après 15 minutes d'inactivité**. Le premier appel après veille prend ~30 secondes (cold start).

Pour éviter ça en démo : utiliser [UptimeRobot](https://uptimerobot.com) (gratuit) pour pinger le service toutes les 10 minutes.

---

## Développement local

```bash
# 1. Clone
git clone https://github.com/TON_USERNAME/support-ia.git
cd support-ia

# 2. Backend
cd backend
cp .env.example .env
# Modifier .env : DB_CONNECTION=sqlite (ou ta DB locale)
composer install
php artisan key:generate
php artisan migrate
php artisan serve  # → localhost:8000

# 3. Queue worker (dans un autre terminal)
cd backend
php artisan queue:work

# 4. n8n local (dans un autre terminal)
n8n start  # → localhost:5678

# 5. Frontend
cd frontend
cp .env.example .env
# NE PAS définir VITE_API_URL → proxy Vite vers localhost:8000
npm install
npm run dev  # → localhost:5173
```

---

## Personnalisation

### Changer le modèle IA

Le modèle (Gemini, GPT, Claude, etc.) est configuré dans ton **workflow n8n**.
Modifie le nœud IA dans l'éditeur n8n → commit `n8n/workflow.json` → push → redéploiement automatique.

### Changer les embeddings

Par défaut : Cohere `embed-multilingual-v3.0`.
Modifiable dans `backend/app/Services/CohereEmbeddingService.php`.

### Ajouter un domaine custom

- **Backend** : dans Render → Settings → Custom Domain
- **Frontend** : dans Vercel → Settings → Domains
- Mettre à jour `FRONTEND_URL` et `APP_URL` en conséquence

---

## Structure du repo

```
support-ia/
├── Dockerfile                 # Container unique (Laravel + n8n)
├── docker-entrypoint.sh       # Script de démarrage
├── supervisord.conf            # Gestionnaire de processus
├── nginx/
│   └── default.conf.template  # Config Nginx (PORT dynamique)
├── render.yaml                 # Déploiement Render (Infrastructure as Code)
├── .gitignore
├── README.md
│
├── n8n/
│   ├── workflow.json           # ⚠️ Remplacer par ton workflow exporté
│   └── README.md              # Instructions n8n
│
├── backend/                   # Laravel/PHP API
│   ├── app/
│   │   ├── Http/Controllers/  # Routes API
│   │   ├── Models/            # Modèles Eloquent
│   │   └── Services/          # N8nService, EmbeddingService, RAG...
│   ├── config/
│   ├── database/migrations/   # Schéma DB
│   ├── routes/api.php
│   └── .env.example           # ← Variables à configurer
│
└── frontend/                  # React/Vite SPA
    ├── src/
    │   ├── pages.jsx          # Toutes les pages (admin + client)
    │   ├── api.js             # Client HTTP (axios)
    │   └── styles/
    ├── vercel.json            # Config Vercel
    └── .env.example          # ← Variables à configurer
```

---

## Licence

MIT — Libre d'utilisation, modification et distribution.
