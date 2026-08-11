# 📧 Configuration Email Brevo (Envoi Automatique)

Ce guide explique comment configurer **Brevo (ex-Sendinblue)** pour que le système envoie automatiquement les emails aux clients avec leurs identifiants.

---

## 🎯 Pourquoi Brevo ?

- ✅ **Gratuit** : 300 emails/jour
- ✅ **Fiable** : Haute délivrabilité
- ✅ **Rapide** : Configuration en 5 minutes
- ✅ **SMTP** : Compatible avec Laravel Mail

---

## 📋 Étape 1 : Créer un Compte Brevo

1. Allez sur : **https://brevo.com**
2. Cliquez sur **"Sign up free"**
3. Créez votre compte (email + mot de passe)
4. Confirmez votre email

---

## 🔑 Étape 2 : Obtenir la Clé SMTP

1. Connectez-vous à **https://app.brevo.com**
2. En haut à droite : **Cliquez sur votre nom** → **"SMTP & API"**
3. Onglet **"SMTP"**
4. Cliquez sur **"Create a new SMTP key"**
5. Nommez-la : `CustomerAI Production`
6. **Copiez la clé** (format : `xsmtpsib-xxxxxx...`)

⚠️ **IMPORTANT** : Cette clé ne s'affiche qu'une fois ! Notez-la.

---

## 📨 Étape 3 : Vérifier Votre Email Expéditeur

Brevo exige que vous vérifiiez l'email que vous utilisez pour envoyer.

### Option A : Email Personnel (Rapide)
1. Dans Brevo : **Senders** → **"Add a sender"**
2. Entrez : `mahamouddjanta1@gmail.com` (votre email)
3. Brevo envoie un email de confirmation
4. Cliquez sur le lien dans l'email

### Option B : Domaine Personnalisé (Pro)
Si vous avez un domaine (ex: `support@votre-entreprise.com`) :
1. **Senders** → **"Domains"** → **"Add a domain"**
2. Suivez les instructions pour ajouter les enregistrements DNS
3. Attendez la vérification (quelques heures)

---

## ⚙️ Étape 4 : Configurer Render

1. Allez sur : **https://dashboard.render.com**
2. Sélectionnez votre service : **customerai-oja8**
3. Onglet **"Environment"**
4. Cliquez sur **"Add Environment Variable"**
5. Ajoutez ces 7 variables :

### Variables à Ajouter

```env
MAIL_HOST=smtp-relay.brevo.com
```
```env
MAIL_PORT=587
```
```env
MAIL_USERNAME=b3ef1e001@smtp-brevo.com
```
```env
MAIL_PASSWORD=VOTRE_CLE_SMTP_BREVO_ICI
```
⚠️ **Remplacez par VOTRE clé SMTP obtenue à l'étape 2** (format: `xsmtpsib-xxxxx...`)

```env
MAIL_ENCRYPTION=tls
```
```env
MAIL_FROM_ADDRESS=mahamouddjanta1@gmail.com
```
⚠️ **Remplacez par l'email vérifié à l'étape 3**

```env
MAIL_FROM_NAME=Support CustomerAI
```

6. Cliquez sur **"Save Changes"**
7. Render redémarrera automatiquement (~2 minutes)

---

## ✅ Étape 5 : Tester l'Envoi

1. Attendez que Render affiche **"Live 🎉"**
2. Allez sur votre **frontend Vercel**
3. Connectez-vous en admin
4. **Clients** → **"+ Ajouter un Client"**
5. Remplissez :
   - Nom : `Test Client`
   - Email : **votre-email@gmail.com** (votre vraie adresse)
6. Cliquez sur **"📧 Créer et Envoyer"**

### Résultat Attendu

- ✅ Message : `"Client "Test Client" créé ! Les identifiants ont été envoyés par email à votre-email@gmail.com"`
- ✅ Un email arrive dans votre boîte (vérifiez spam si absent)
- ✅ L'email contient :
  - Identifiant du client
  - Mot de passe généré
  - Lien vers la page de connexion

---

## 🔍 Debug : Si l'Email N'Arrive Pas

### 1. Vérifier les Logs Render

Dans **Render** → **Logs**, cherchez :
```
=== Début création client ===
Validation OK
Identifiants générés
Client créé en DB
Tentative envoi email
Email envoyé avec succès
```

Si vous voyez `Échec envoi email`, regardez l'erreur.

### 2. Erreurs Courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| `Authentication failed` | Mauvaise clé SMTP | Vérifiez `MAIL_PASSWORD` sur Render |
| `Sender not verified` | Email non vérifié | Vérifiez l'email dans Brevo Senders |
| `Connection refused` | Mauvais port | Vérifiez `MAIL_PORT=587` |
| `Too many requests` | Limite gratuite atteinte (300/jour) | Attendez demain ou passez payant |

### 3. Vérifier les Stats Brevo

Dans **Brevo Dashboard** → **Statistics** :
- Vous voyez les emails envoyés
- Statut : Delivered / Bounced / Opened

---

## 🚀 C'est Terminé !

Maintenant, chaque fois que vous créez un client :
1. Le système génère automatiquement un mot de passe sécurisé
2. Un email est envoyé instantanément via Brevo
3. Le client reçoit ses identifiants dans sa boîte
4. Il peut se connecter avec l'identifiant + mot de passe

---

## 📊 Limites du Plan Gratuit Brevo

- **300 emails/jour** maximum
- Si vous dépassez : les emails sont mis en file d'attente
- Pour augmenter : Passez au plan payant (à partir de 19€/mois pour 20k emails)

---

## 🔒 Sécurité

⚠️ **Ne JAMAIS commiter** les vraies valeurs dans Git :
- Gardez `.env.example` avec des placeholders
- Les vraies clés sont uniquement sur Render Environment

---

## 📞 Support

Si ça ne fonctionne toujours pas après avoir suivi ce guide :
1. Envoyez les logs Render (partie avec "création client")
2. Vérifiez que toutes les variables sont bien sur Render
3. Assurez-vous que l'email expéditeur est vérifié sur Brevo
