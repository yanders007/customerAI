# Fix N8N sur Render (Service Gratuit)

## Problème résolu
```
Database connection timed out
503 Database is not ready!
```

## Cause
N8N nécessite des variables d'environnement spécifiques (`DB_POSTGRESDB_HOST`, `DB_POSTGRESDB_USER`, etc.) qui n'étaient pas mappées depuis les variables Laravel standard (`DB_HOST`, `DB_USERNAME`, etc.).

## Solution implémentée

### 1. Mapping automatique des variables (docker-entrypoint.sh)

Le script `docker-entrypoint.sh` a été modifié pour **mapper automatiquement** les variables Laravel vers N8N :

```bash
# Les variables Render/Laravel (DB_HOST, DB_DATABASE, etc.) 
# sont automatiquement converties en variables N8N
export DB_POSTGRESDB_HOST="${DB_HOST:-localhost}"
export DB_POSTGRESDB_PORT="${DB_PORT:-5432}"
export DB_POSTGRESDB_DATABASE="${DB_DATABASE:-postgres}"
export DB_POSTGRESDB_USER="${DB_USERNAME:-postgres}"
export DB_POSTGRESDB_PASSWORD="${DB_PASSWORD}"
export DB_POSTGRESDB_SCHEMA="${DB_POSTGRESDB_SCHEMA:-n8n}"
```

### 2. Désactivation de l'authentification API N8N (supervisord.conf)

N8N nécessitait une API Key pour l'import des workflows. Comme N8N tourne en **localhost uniquement** (non exposé publiquement), l'authentification API a été désactivée :

```bash
N8N_API_KEY_AUTH_DISABLED="true"
```

Ceci permet au script `activate-n8n-workflow.sh` d'importer et activer les workflows sans clé API.

N8N utilise **la même base PostgreSQL** que Laravel, mais dans un **schéma différent** (`n8n`) :
- ✅ Pas besoin de créer une nouvelle base de données
- ✅ Pas de coûts supplémentaires
- ✅ Les données sont isolées (pas de conflit)

### 4. Clé de chiffrement N8N

Si vous voulez que vos workflows N8N **survivent aux redéploiements**, ajoutez cette variable dans Render :

```bash
N8N_ENCRYPTION_KEY=<générez avec: openssl rand -hex 32>
```

**Sans cette variable** : une clé aléatoire sera générée à chaque déploiement, et vous perdrez vos workflows/credentials N8N.

## Configuration Render (après ce fix)

### Variables DÉJÀ configurées (ne rien changer)
- `DB_HOST` → utilisé par Laravel ET N8N
- `DB_PORT` → utilisé par Laravel ET N8N
- `DB_DATABASE` → utilisé par Laravel ET N8N
- `DB_USERNAME` → utilisé par Laravel ET N8N
- `DB_PASSWORD` → utilisé par Laravel ET N8N

### Variables à ajouter (optionnel)
- `N8N_ENCRYPTION_KEY` → pour persister les workflows N8N entre redéploiements

## Après le push

1. **Push le code corrigé** :
   ```bash
   git add docker-entrypoint.sh backend/.env.example DEPLOY.sh
   git commit -m "fix: Mapping automatique variables DB pour N8N"
   git push origin main
   ```

2. **Render va redéployer automatiquement** (3-5 min)

3. **Vérifier les logs Render** :
   ```
   ✓ Variables N8N configurées (Host: xxx, DB: xxx, Schema: n8n)
   ✓ N8N est accessible
   ✓ Workflow importé avec succès
   ```

## Architecture finale

```
┌─────────────────────────────────────────┐
│   Service Render (gratuit)              │
│                                          │
│  ┌──────────────┐   ┌──────────────┐   │
│  │   Laravel    │   │     N8N      │   │
│  │  (port 8000) │   │ (port 5678)  │   │
│  └──────┬───────┘   └──────┬───────┘   │
│         │                  │            │
│         └──────────┬───────┘            │
│                    │                    │
│         ┌──────────▼───────────┐        │
│         │   PostgreSQL         │        │
│         │   (Supabase)         │        │
│         │                      │        │
│         │  ├─ public (Laravel) │        │
│         │  └─ n8n (N8N)        │        │
│         └──────────────────────┘        │
└─────────────────────────────────────────┘
```

## Avantages de cette solution

✅ **Aucune variable supplémentaire requise** (mapping auto)  
✅ **Une seule base PostgreSQL** (gratuit Supabase)  
✅ **Isolation des données** (schémas séparés)  
✅ **Compatible service Render gratuit**  
✅ **Fonctionne out-of-the-box** après push  
✅ **Pas d'API Key nécessaire** (N8N en localhost uniquement)

## Note de sécurité

⚠️ **N8N_API_KEY_AUTH_DISABLED="true"** est sécurisé dans ce contexte car :
- N8N écoute uniquement sur `localhost:5678` (non exposé publiquement)
- Accessible uniquement depuis l'intérieur du conteneur Render
- Aucun accès externe possible via internet
- L'authentification Basic Auth reste active pour l'UI N8N (si configurée)

## Troubleshooting

### Si N8N ne démarre toujours pas :

1. **Vérifier les logs Render** pour voir les variables mappées
2. **Vérifier que PostgreSQL est accessible** depuis Render
3. **Tester la connexion DB** :
   ```bash
   # Dans Render Shell
   php artisan migrate:status
   ```

### Si vous perdez vos workflows N8N après redéploiement :

Ajoutez `N8N_ENCRYPTION_KEY` dans Render Environment avec une valeur fixe.
