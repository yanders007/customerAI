# Support IA — Plateforme de support automatisé pour startups

Plateforme full-stack avec IA, 100% open source, déployable gratuitement en ~20 minutes.
Le provider IA se configure directement depuis l'interface admin — aucune variable d'environnement requise pour l'IA.

**Stack** : React/Vite · Laravel/PHP 8.3 · PostgreSQL (Supabase) · Render · Vercel

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      RENDER (gratuit)                         │
│                                                              │
│   ┌──────────────────────────────────────────────────────┐  │
│   │            Container Docker  :10000                   │  │
│   │                                                      │  │
│   │   Nginx → PHP-FPM (Laravel API)                      │  │
│   │                    ↓                                 │  │
│   │        AiService → Provider IA de ton choix          │  │
│   │        (Gemini · Groq · OpenAI · Mistral…)           │  │
│   │               Queue Worker                           │  │
│   └──────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
          ↕ PostgreSQL                      ↕ API REST
┌─────────────────────────┐     ┌──────────────────────────────┐
│   SUPABASE (gratuit)    │     │      VERCEL (gratuit)         │
│   PostgreSQL            │     │      React SPA                │
│   · Tables app          │     │      → Page config IA admin   │
│   · Clés IA chiffrées   │     └──────────────────────────────┘
└─────────────────────────┘
```

**La clé API du provider IA est saisie depuis le dashboard admin**, chiffrée, et stockée dans Supabase.
Laravel appelle directement le provider (Gemini, Groq, OpenAI, Mistral…) sans passer par n8n.

---

## Providers IA supportés

| Provider | Gratuit | Modèles recommandés |
|----------|---------|-------------------|
| **Google Gemini** | ✅ Oui (sans CB) | gemini-2.0-flash, gemini-1.5-flash |
| **Groq** | ✅ Oui (sans CB) | llama-3.3-70b-versatile |
| **Together AI** | ✅ Oui (crédits offerts) | Llama-3-70b |
| **Cohere** | ✅ Oui (trial) | command-r |
| OpenAI | ❌ Payant | gpt-4o-mini |
| Anthropic | ❌ Payant | claude-3-haiku |
| Mistral | ❌ Payant | mistral-small |
| DeepSeek | ❌ Payant | deepseek-chat |
| OpenRouter | ❌ Payant | (agrégateur) |
| xAI / Grok | ❌ Payant | grok-beta |
| Perplexity | ❌ Payant | sonar-small |

---

## Comptes à créer (tous gratuits, sans CB)

| Service | Lien | Usage |
|---------|------|-------|
| GitHub | [github.com](https://github.com) | Héberger le repo forké |
| Supabase | [supabase.com](https://supabase.com) | Base de données PostgreSQL |
| Render | [render.com](https://render.com) | Backend Laravel (container Docker) |
| Vercel | [vercel.com](https://vercel.com) | Frontend React |
| Cohere | [dashboard.cohere.com](https://dashboard.cohere.com) | Embeddings RAG |
| Resend | [resend.com](https://resend.com) | Emails (3 000/mois gratuit) |

La clé du **provider IA** (Gemini, Groq…) sera configurée depuis l'admin de l'app après le déploiement.

---

## Étape 1 — Fork et clone

### 1.1 Fork

1. Ouvrir le repo sur GitHub → cliquer **Fork** → **Create fork**

### 1.2 Clone

```bash
git clone https://github.com/TON_USERNAME/support-ia.git
cd support-ia
```

---

## Étape 2 — Supabase (base de données)

### 2.1 Créer le projet

1. [supabase.com](https://supabase.com) → **New project**
2. Remplir :
   - **Name** : `support-ia`
   - **Database Password** : générer un mot de passe fort → **copier immédiatement** (ne réapparaît pas)
   - **Region** : la plus proche (ex: West EU Ireland)
3. **Create new project** → attendre ~2 minutes

### 2.2 Récupérer les infos de connexion

1. Dans Supabase → **Settings** → **Database**
2. Section **Connection parameters** → mode **Session**
3. Copier :
   - **Host** : `db.XXXXXXXXXX.supabase.co`
   - **Password** : celui noté à l'étape précédente

> Les autres valeurs sont fixes : Port `5432`, Database `postgres`, User `postgres`.

### 2.3 Une seule chose à vérifier

S'assurer d'utiliser le **port 5432** (pas le port 6543 du pooler PgBouncer).
Le port 5432 est indiqué dans la section "Session mode" — c'est celui que Render utilisera.

---

## Étape 3 — Cohere (embeddings RAG uniquement)

1. [dashboard.cohere.com/api-keys](https://dashboard.cohere.com/api-keys) → créer un compte
2. **New trial key** → copier la clé

> Cohere est uniquement utilisé pour les embeddings (recherche dans la doc). Le provider IA principal sera configuré depuis l'admin.

---

## Étape 4 — Resend (emails)

1. [resend.com](https://resend.com) → **Get Started**
2. **API Keys** → **Create API Key** → copier la clé (`re_...`)

> Sans domaine vérifié, les emails partent depuis `onboarding@resend.dev` — parfait pour un PFE.
> Pour un usage réel : **Domains** → ajouter et vérifier ton domaine.

---

## Étape 5 — Render (backend)

### 5.1 Créer le compte

1. [render.com](https://render.com) → s'inscrire avec GitHub

### 5.2 Déployer via Blueprint

1. Dashboard → **New** → **Blueprint**
2. Sélectionner ton repo GitHub forké
3. Render détecte `render.yaml` automatiquement
4. Cliquer **Apply**

### 5.3 Renseigner les variables d'environnement

Après Apply, Render liste les variables `sync: false` à remplir. Les voici toutes :

| Variable | Valeur |
|----------|--------|
| `APP_URL` | `https://NOM.onrender.com` *(connu après le 1er deploy — à mettre à jour)* |
| `DB_HOST` | `db.XXXX.supabase.co` |
| `DB_PASSWORD` | Ton mot de passe Supabase |
| `FRONTEND_URL` | `https://TON-APP.vercel.app` *(à mettre après le deploy Vercel)* |
| `COHERE_API_KEY` | Ta clé Cohere |
| `MAIL_PASSWORD` | Ta clé Resend (`re_...`) |
| `MAIL_FROM_ADDRESS` | `noreply@tondomaine.com` ou `onboarding@resend.dev` |
| `SUPPORT_EMAIL` | Email qui reçoit les escalades |

> `APP_KEY` est généré automatiquement par Render (`generateValue: true`).

### 5.4 Premier déploiement

Le build prend **3 à 5 minutes** (PHP, Composer, extensions PostgreSQL).
Surveiller l'onglet **Logs** → chercher :
```
▶ Migrations...
▶ Lancement de Supervisor...
```

### 5.5 Vérifier

```
https://NOM.onrender.com/api/admin/me
```
Réponse attendue : `{"message":"Unauthenticated."}` → Laravel répond.

---

## Étape 6 — Vercel (frontend)

### 6.1 Importer le projet

1. [vercel.com](https://vercel.com) → **Add New Project**
2. Importer le repo GitHub forké
3. **Root Directory** → **Edit** → `frontend`
4. **Framework** → Vite (détecté automatiquement)
5. **Environment Variables** :
   - `VITE_API_URL` = `https://NOM.onrender.com` *(ton URL Render)*
6. **Deploy**

### 6.2 Mettre à jour FRONTEND_URL dans Render

1. Copier l'URL Vercel (ex: `https://support-ia-abc.vercel.app`)
2. Render → ton service → **Environment**
3. Modifier `FRONTEND_URL` → coller l'URL Vercel
4. **Save Changes** → Render redéploie

> Cette étape est nécessaire pour les cookies CORS entre les deux domaines.

---

## Étape 7 — Créer le premier compte admin

```bash
# Depuis le dashboard Render → ton service → onglet Shell
cd /app/backend
php artisan tinker

# Dans Tinker :
\App\Models\Admin::create([
    'name'     => 'Admin',
    'email'    => 'admin@example.com',
    'password' => bcrypt('mot-de-passe-fort'),
]);
exit
```

---

## Étape 8 — Configurer le provider IA depuis l'admin

C'est la nouveauté principale. **Aucune variable d'environnement requise pour l'IA.**

### 8.1 Se connecter à l'admin

Ouvrir `https://TON-APP.vercel.app` → se connecter avec le compte créé à l'étape 7.

### 8.2 Aller dans Configuration IA

Dans la navigation admin → **Configuration IA** (ou icône clé).

### 8.3 Choisir un provider gratuit

L'interface affiche tous les providers disponibles. Pour commencer gratuitement :

#### Option A — Google Gemini (recommandé)

1. Cliquer sur la carte **Google Gemini** → s'ouvre sur [aistudio.google.com/apikey](https://aistudio.google.com/apikey)
2. **Create API key** → copier la clé (`AIza...`)
3. Revenir dans l'admin → la carte Gemini est déjà sélectionnée
4. **Modèle** : `gemini-2.0-flash` (rapide et gratuit)
5. Coller la clé dans **Clé API**
6. Cliquer **Tester la connexion** → voir la réponse de test
7. Cliquer **Sauvegarder la configuration**

#### Option B — Groq (ultra-rapide)

1. Cliquer sur la carte **Groq** → [console.groq.com/keys](https://console.groq.com/keys)
2. **Create API Key** → copier la clé (`gsk_...`)
3. **Modèle** : `llama-3.3-70b-versatile`
4. Coller, tester, sauvegarder.

### 8.4 Tester le chat client

1. Créer un client depuis l'admin
2. Se connecter avec le compte client
3. Poser une question → l'IA répond

---

## Changer de provider IA

Depuis le dashboard admin → **Configuration IA** :
- Cliquer sur un autre provider
- Entrer la nouvelle clé
- Sauvegarder → actif immédiatement

L'ancienne configuration est automatiquement désactivée.

---

## Personnaliser le comportement de l'IA

Dans **Configuration IA** → **Prompt système personnalisé** :

```
Tu es l'assistant de support de [Nom de l'entreprise], spécialisé en [domaine].
Tu réponds uniquement en te basant sur la documentation fournie.
Ton ton est [professionnel/amical/technique].
```

Laisser vide pour utiliser le prompt par défaut.

---

## Développement local

```bash
# 1. Backend
cd backend
cp .env.example .env
# Modifier .env :
#   DB_CONNECTION=sqlite
#   APP_DEBUG=true
#   SESSION_SECURE_COOKIE=false
#   FRONTEND_URL=http://localhost:5173
composer install
php artisan key:generate
php artisan migrate
php artisan serve              # → http://localhost:8000

# 2. Queue worker (terminal séparé)
cd backend && php artisan queue:work

# 3. Frontend (terminal séparé)
cd frontend
cp .env.example .env
# Laisser VITE_API_URL vide → proxy Vite vers localhost:8000
npm install
npm run dev                   # → http://localhost:5173

# 4. Configurer l'IA
# → Se connecter à http://localhost:5173 en admin
# → Configuration IA → choisir Gemini ou Groq → entrer la clé → sauvegarder
```

---

## Intégration dans pages.jsx

Le composant `AiConfigPage.jsx` est un fichier indépendant. Pour l'intégrer :

**1. Ajouter l'import en haut de pages.jsx**
```js
import AiConfigPage from './AiConfigPage';
```

**2. Ajouter le cas dans le switch/router admin**
```js
case 'ai-config':
  return <AiConfigPage api={api} />;
```

**3. Ajouter un item dans la nav admin**
```jsx
<button onClick={() => setPage('ai-config')}>
  🔑 Configuration IA
</button>
```

---

## Limites du plan gratuit

| Service | Limite | Note |
|---------|--------|------|
| Render | Veille après 15 min | Cold start ~30s — utiliser UptimeRobot pour éviter |
| Render | 512 MB RAM | PHP-FPM + Queue Worker : ~150 MB max |
| Supabase | 500 MB DB | Très largement suffisant |
| Vercel | 100 GB/mois | Plus que suffisant |
| Gemini | 1 500 req/jour | Suffisant pour une démo PFE |
| Groq | 30 req/min | Ultra-rapide, largement suffisant |
| Cohere | 20 req/min | Pour les embeddings uniquement |
| Resend | 3 000 emails/mois | Suffisant |

### Éviter la veille Render

[UptimeRobot](https://uptimerobot.com) (gratuit) → **New Monitor** → HTTP → URL : `https://NOM.onrender.com/api/admin/me` → toutes les 5 min.

---

## Structure du repo

```
support-ia/
├── Dockerfile                  # PHP 8.3 + Nginx (léger, sans n8n)
├── docker-entrypoint.sh        # Migration + caches + Supervisor
├── supervisord.conf             # Nginx + PHP-FPM + Queue Worker
├── nginx/default.conf.template # Config Nginx (PORT dynamique Render)
├── render.yaml                  # Blueprint Render
├── .gitignore
├── README.md
│
├── backend/                    # Laravel/PHP API
│   ├── app/
│   │   ├── Http/Controllers/Api/Admin/
│   │   │   ├── AiConfigController.php   # ← NOUVEAU : CRUD config IA
│   │   │   ├── ClientController.php
│   │   │   └── DocsController.php
│   │   ├── Models/
│   │   │   └── AiConfig.php             # ← NOUVEAU : stockage clé chiffrée
│   │   └── Services/
│   │       ├── AiService.php            # ← NOUVEAU : multi-provider
│   │       ├── CohereEmbeddingService.php
│   │       └── RetrievalService.php
│   ├── database/migrations/
│   │   └── ...create_ai_configs_table.php  # ← NOUVEAU
│   ├── routes/api.php           # Routes AI config ajoutées
│   └── .env.example
│
└── frontend/                   # React/Vite SPA
    ├── src/
    │   ├── AiConfigPage.jsx     # ← NOUVEAU : UI configuration IA
    │   ├── pages.jsx            # À intégrer (voir section ci-dessus)
    │   └── api.js
    └── .env.example
```

---

## Variables d'environnement — Résumé complet

### Render (backend)

| Variable | Obligatoire | Valeur |
|----------|-------------|--------|
| `APP_KEY` | ✅ | Généré automatiquement par Render |
| `APP_URL` | ✅ | `https://NOM.onrender.com` |
| `DB_HOST` | ✅ | `db.XXXX.supabase.co` |
| `DB_PASSWORD` | ✅ | Mot de passe Supabase |
| `FRONTEND_URL` | ✅ | `https://TON-APP.vercel.app` |
| `COHERE_API_KEY` | ✅ | Clé Cohere (embeddings) |
| `MAIL_PASSWORD` | ✅ | Clé Resend |
| `MAIL_FROM_ADDRESS` | ✅ | Email d'envoi |
| `SUPPORT_EMAIL` | ✅ | Email qui reçoit les escalades |

### Vercel (frontend)

| Variable | Obligatoire | Valeur |
|----------|-------------|--------|
| `VITE_API_URL` | ✅ | `https://NOM.onrender.com` |

### Provider IA

**Aucune variable d'environnement.** La clé est saisie depuis l'admin de l'application → chiffrée → stockée en base.

---

## Licence

MIT — Libre d'utilisation, modification et distribution.
