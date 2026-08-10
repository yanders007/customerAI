# Workflow n8n

## Comment remplacer `workflow.json`

1. **Exporte ton workflow depuis n8n** :
   - Ouvre ton workflow → Menu `⋮` → **Download**
   - Tu obtiens un fichier `.json`

2. **Remplace ce fichier** :
   ```bash
   cp ~/Downloads/mon-workflow.json n8n/workflow.json
   ```

3. **Commit et push** → Render redéploie automatiquement

## Accéder à l'UI n8n en production

n8n tourne en interne dans le container Render (port 5678 non exposé). Pour y accéder :

```bash
# Depuis le dashboard Render → ton service → "Shell"
# Puis dans le terminal Render :
curl http://localhost:5678/healthz
```

Ou utilise un **tunnel temporaire** via Render Shell pour configurer les credentials Gemini.

## Variables d'environnement n8n (set dans Render)

| Variable | Valeur |
|----------|--------|
| `N8N_BASIC_AUTH_USER` | `admin` |
| `N8N_BASIC_AUTH_PASSWORD` | *(généré automatiquement)* |
| `N8N_ENCRYPTION_KEY` | *(à fixer manuellement pour persistance)* |

> ⚠️ **Important** : Fixe `N8N_ENCRYPTION_KEY` à une valeur stable dans Render sinon les credentials n8n (clé Gemini, etc.) sont perdues à chaque redéploiement.

## Configurer la clé Gemini dans n8n

La clé Gemini est gérée via les **Credentials n8n** (pas via les variables d'env Laravel). Au premier déploiement :

1. Accède au shell Render
2. Lance un tunnel : `ssh -L 5678:localhost:5678 ...` *(ou utilise le Render Shell UI)*
3. Ouvre `http://localhost:5678` dans ton navigateur
4. Credentials → Add → Google Gemini → colle ta clé API
