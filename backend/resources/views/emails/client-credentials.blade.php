<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:20px; }
.card { background:#fff; border-radius:8px; padding:32px; max-width:520px; margin:auto; border:1px solid #e0e0e0; }
h2 { color:#18181b; margin-top:0; }
.creds { background:#f9f9f9; border:1px solid #e4e4e7; border-radius:6px; padding:16px; margin:20px 0; }
.creds p { margin:6px 0; font-size:15px; }
.creds strong { color:#18181b; }
.btn { display:inline-block; background:#dc2626; color:#fff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; margin-top:16px; }
.footer { color:#71717a; font-size:12px; margin-top:24px; }
</style></head>
<body>
<div class="card">
  <h2>Bienvenue, {{ $clientName }} 👋</h2>
  <p>Votre compte sur la plateforme <strong>Support IA</strong> a été créé. Voici vos identifiants de connexion :</p>
  <div class="creds">
    <p><strong>Identifiant :</strong> {{ $identifier }}</p>
    <p><strong>Mot de passe :</strong> {{ $password }}</p>
  </div>
  <p>Conservez ces informations en lieu sûr. Vous pouvez changer votre mot de passe après connexion.</p>
  <a href="{{ $loginUrl }}" class="btn">Accéder à la plateforme →</a>
  <div class="footer">
    <p>Si vous n'attendiez pas ce message, ignorez cet email.</p>
  </div>
</div>
</body>
</html>
