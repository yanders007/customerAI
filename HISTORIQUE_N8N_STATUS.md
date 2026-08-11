# ✅ Vérification : Historique N8N + Statut en temps réel

## 🎯 Historique des conversations

### Backend ✅
**AskController** (ligne 213) :
```php
$history = $this->buildHistory($conversation, $userMessage->id);
```

**buildHistory()** (ligne 290-316) :
- Récupère les **5 derniers messages** (HISTORY_MAX_MESSAGES = 5)
- Format : `Client : message\nAssistant : réponse\n...`
- Exclut le message actuel (déjà envoyé séparément)

**N8nService** (ligne 47) :
```php
$response = Http::timeout(90)
    ->post($this->webhookUrl, [
        'question'      => $question,
        'documentation' => $documentation,
        'faq'           => $faq,
        'history'       => $history,        // ✅ ENVOYÉ
        'client_name'   => $clientName,
        'provider'      => $aiConfig->provider,
        'model'         => $aiConfig->model,
        'api_key'       => $aiConfig->api_key,
    ]);
```

### Workflow N8N ✅
**Code in JavaScript node** (final-client.json) :
```javascript
const history = body.history || '';

const prompt = `...
${history ? '=== HISTORIQUE DE LA CONVERSATION ===\\n' + history + '\\n' : ''}
Question du client : ${question}`;
```

**Résultat** : L'IA reçoit bien l'historique des 5 derniers échanges ! ✅

---

## 👥 Statut clients en temps réel

### Migration ✅
```bash
2026_08_11_000003_add_last_seen_to_clients_table.php
```
- Ajoute colonne `last_seen` (timestamp nullable)

### Modèle Client ✅
```php
protected $fillable = [..., 'last_seen', ...];
protected $casts = ['last_seen' => 'datetime', ...];

public function isOnline(): bool
{
    if (!$this->last_seen) return false;
    return $this->last_seen->diffInMinutes(now()) < 2;
}
```

### Endpoints ✅

**POST /client/heartbeat** :
- Rate limit : 120/min (1 requête toutes les 30s max)
- Middleware : auth.client
- Action : `Client::update(['last_seen' => now()])`

**GET /admin/clients** :
- Retourne pour chaque client :
  - `last_seen` (ISO 8601)
  - `is_online` (boolean, true si last_seen < 2 minutes)

**POST /client/login** :
- Met à jour `last_login` ET `last_seen`

---

## 🔧 À implémenter côté frontend

### Heartbeat automatique
Le frontend client doit appeler toutes les **30 secondes** :
```javascript
setInterval(() => {
  fetch('https://backend.com/api/client/heartbeat', {
    method: 'POST',
    credentials: 'include',  // Important pour cookies session
    headers: {
      'Content-Type': 'application/json'
    }
  });
}, 30000);
```

### Affichage statut admin
Dans la liste clients (`/admin/clients`), afficher :
```javascript
{client.is_online ? (
  <span className="badge bg-success">🟢 En ligne</span>
) : (
  <span className="badge bg-secondary">⚫ Hors ligne</span>
)}
```

Rafraîchir la liste toutes les 10 secondes :
```javascript
useEffect(() => {
  const interval = setInterval(() => {
    fetchClients();
  }, 10000);
  return () => clearInterval(interval);
}, []);
```

---

## 📦 Commit effectué

```
d69d38a - 🔄 Architecture IA + Statut temps réel
```

**Prêt à pusher vers GitHub** ✅

---

## ⚠️ Problème actuel : Redirection en boucle admin

**Symptôme** : Après login admin → redirection vers /login-admin en boucle

**Causes possibles** :
1. **Session cookies cross-domain** : Frontend doit envoyer `credentials: 'include'`
2. **CORS configuration** : Vérifier `SESSION_DOMAIN` et `CORS_ALLOWED_ORIGINS`
3. **Frontend** : Vérifier que le token/session est bien stocké après login

**À vérifier** :
- Frontend envoie `credentials: 'include'` dans tous les fetch()
- Backend `SESSION_DOMAIN=null` et `SESSION_SAME_SITE=none`
- CORS autorise les credentials : `supports_credentials: true`
