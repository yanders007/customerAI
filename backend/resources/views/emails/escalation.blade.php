<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:20px; }
.card { background:#fff; border-radius:8px; padding:32px; max-width:520px; margin:auto; border:1px solid #e0e0e0; }
h2 { color:#dc2626; margin-top:0; }
.info { background:#fff7f7; border:1px solid #fecdd3; border-radius:6px; padding:16px; margin:16px 0; }
.info p { margin:6px 0; font-size:14px; }
.question { background:#f9f9f9; border-left:3px solid #dc2626; padding:12px 16px; margin:16px 0; font-style:italic; color:#3f3f46; }
.btn { display:inline-block; background:#18181b; color:#fff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; margin-top:16px; }
.footer { color:#71717a; font-size:12px; margin-top:24px; }
</style></head>
<body>
<div class="card">
  <h2>⚠️ L'IA n'a pas pu répondre</h2>
  <p>Un client a posé une question à laquelle l'assistant IA n'a pas trouvé de réponse dans la documentation.</p>
  <div class="info">
    <p><strong>Client :</strong> {{ $clientName }}</p>
    <p><strong>Projet :</strong> {{ $projetName }}</p>
    <p><strong>ID conversation :</strong> {{ $conversationUuid }}</p>
  </div>
  <p><strong>Question posée :</strong></p>
  <div class="question">{{ $question }}</div>
  <p>Cliquez ci-dessous pour accéder à la conversation complète et répondre directement au client :</p>
  <a href="{{ $conversationUrl }}" class="btn">Voir la conversation →</a>
  <div class="footer">
    <p>Votre réponse sera visible par le client dans son chat en temps réel.</p>
  </div>
</div>
</body>
</html>
