<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:20px; }
.card { background:#fff; border-radius:8px; padding:32px; max-width:520px; margin:auto; border:1px solid #e0e0e0; }
h2 { color:#18181b; margin-top:0; }
.btn { display:inline-block; background:#dc2626; color:#fff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; margin-top:16px; }
.footer { color:#71717a; font-size:12px; margin-top:24px; border-top:1px solid #e4e4e7; padding-top:16px; }
</style></head>
<body>
<div class="card">
  <h2>Réinitialisation du mot de passe</h2>
  <p>Bonjour <strong>{{ $name }}</strong>,</p>
  <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous — ce lien est valable <strong>1 heure</strong>.</p>
  <a href="{{ $resetUrl }}" class="btn">Réinitialiser mon mot de passe →</a>
  <div class="footer">
    <p>Si vous n'avez pas fait cette demande, ignorez cet email. Votre mot de passe restera inchangé.</p>
  </div>
</div>
</body>
</html>
