# 🔍 DEBUG CLEF API - Guide de résolution

## 🎯 Problème identifié

Vous avez enregistré une clé API Gemini il y a 30min mais l'IA retourne "Clé API invalide".

## ✅ Corrections apportées

### 1. **Fixé le modèle AiConfig** (`backend/app/Models/AiConfig.php`)
   - **Avant** : L'accesseur `getApiKeyAttribute()` lisait mal `$this->api_key_encrypted`
   - **Maintenant** : Lecture correcte via `$this->attributes['api_key_encrypted']`
   - Ajout de gestion d'erreur avec logs si décryptage échoue

### 2. **Ajouté des logs de debug** dans `AiService.php`
   - Log automatique avant validation de la clé
   - Montre : provider, longueur encrypted, longueur décryptée, is_empty
   - Visible dans les logs Laravel (`storage/logs/laravel.log`)

### 3. **Ajouté un endpoint de debug** : `GET /api/admin/ai-config/debug`
   - Retourne l'état complet de la clé API active
   - Montre si la clé est stockée, chiffrée, décryptée correctement
   - **Accessible uniquement aux admins authentifiés**

## 🔧 Comment tester maintenant

### Étape 1 : Appelez l'endpoint de debug
```bash
# Depuis Postman/Insomnia/Bruno (avec token admin)
GET https://votre-backend.onrender.com/api/admin/ai-config/debug
Authorization: Bearer VOTRE_TOKEN_ADMIN
```

**Réponse attendue :**
```json
{
  "success": true,
  "debug": {
    "provider": "gemini",
    "has_api_key_encrypted_field": true,
    "api_key_encrypted_not_empty": true,
    "api_key_encrypted_length": 186,  // Doit être > 100 (chiffré)
    "decrypted_length": 39,            // Longueur de votre vraie clé
    "decrypted_first_15": "AIzaSyBxxx...",
    "validation_would_pass": true      // ✅ DOIT être true
  }
}
```

### Étape 2 : Vérifiez les logs Laravel
```bash
# Sur Render, dans Shell
cd /opt/render/project/src/backend
tail -f storage/logs/laravel.log
```

Ensuite testez une question via l'interface client. Vous verrez :
```
[INFO] AiService validation clé {
  "provider": "gemini",
  "has_encrypted": true,
  "encrypted_length": 186,
  "decrypted_length": 39,
  "is_empty": false
}
```

## 🚨 Si validation_would_pass = false

### Cas 1 : `api_key_encrypted_not_empty = false`
➜ **La clé n'a jamais été sauvegardée en base**
```bash
# Solution : Re-sauvegarder la clé via l'admin
POST /api/admin/ai-config
{
  "provider": "gemini",
  "model": "gemini-1.5-flash",
  "api_key": "VOTRE_CLE_GEMINI_ICI"
}
```

### Cas 2 : `decrypted_length = 0` mais `encrypted_length > 0`
➜ **Erreur de décryptage (APP_KEY changée ou corruption)**

**DANGER** : Si vous changez `APP_KEY` dans `.env`, toutes les anciennes clés chiffrées deviennent illisibles.

```bash
# Solution : Re-sauvegarder avec la nouvelle APP_KEY
POST /api/admin/ai-config
{
  "provider": "gemini",
  "model": "gemini-1.5-flash",
  "api_key": "VOTRE_CLE_GEMINI_ICI"
}
```

### Cas 3 : `decrypted_length < 10`
➜ **La clé sauvegardée est invalide/tronquée**
```bash
# Vérifiez que vous avez copié la clé complète depuis Google AI Studio
# Exemple clé valide : AIzaSyBxxx...xxxxxx (39 caractères)
```

## 📋 Checklist de vérification

- [ ] Endpoint `/api/admin/ai-config/debug` retourne `validation_would_pass: true`
- [ ] Les logs montrent `decrypted_length > 10` et `is_empty: false`
- [ ] Le provider est `"gemini"` et le model `"gemini-1.5-flash"` (ou autre)
- [ ] L'interface admin montre `has_key: true` et `key_length: 39`
- [ ] Un test via `/api/client/ask` fonctionne sans erreur "Clé API invalide"

## 🎉 Une fois résolu

Si tout fonctionne après déploiement, vous pouvez supprimer l'endpoint de debug pour la prod :

```php
// Dans backend/routes/api.php, commentez ou supprimez :
// Route::get('/ai-config/debug', [AiConfigController::class, 'debug']);
```

## 📞 Si ça ne marche toujours pas

1. Vérifiez que `APP_KEY` dans Render n'a pas changé récemment
2. Vérifiez que vous utilisez bien la bonne clé API Gemini (Google AI Studio)
3. Testez avec l'endpoint `/api/admin/ai-config/test` pour un test direct
4. Regardez les logs Laravel (`storage/logs/laravel.log`) pour des détails

---

**Prochaine étape** : Pusher ces changements et tester sur Render.
