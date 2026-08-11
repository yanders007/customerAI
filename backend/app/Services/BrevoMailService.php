<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct()
    {
        $this->apiKey = env('BREVO_API_KEY', '');
    }

    /**
     * Envoyer un email via l'API Brevo
     */
    public function sendClientCredentials(
        string $toEmail,
        string $toName,
        string $identifier,
        string $password,
        string $loginUrl
    ): bool {
        if (empty($this->apiKey)) {
            error_log('⚠️ BREVO_API_KEY non configurée');
            return false;
        }

        $fromEmail = env('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = env('MAIL_FROM_NAME', 'Support');

        $htmlContent = $this->buildHtmlEmail($toName, $identifier, $password, $loginUrl);

        try {
            error_log('→ Envoi email via API Brevo à: ' . $toEmail);

            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'sender' => [
                    'name' => $fromName,
                    'email' => $fromEmail,
                ],
                'to' => [
                    [
                        'email' => $toEmail,
                        'name' => $toName,
                    ]
                ],
                'subject' => '🔑 Vos Identifiants de Connexion',
                'htmlContent' => $htmlContent,
            ]);

            if ($response->successful()) {
                error_log('✓✓✓ EMAIL ENVOYÉ VIA API BREVO ✓✓✓');
                error_log('Message ID: ' . $response->json('messageId'));
                return true;
            } else {
                error_log('✗ Erreur API Brevo: ' . $response->status());
                error_log('Response: ' . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            error_log('✗✗✗ Exception API Brevo ✗✗✗');
            error_log('Erreur: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Construire le HTML de l'email
     */
    private function buildHtmlEmail(string $name, string $identifier, string $password, string $loginUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vos Identifiants</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);padding:40px;text-align:center;border-radius:12px 12px 0 0;">
                            <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:700;">🔑 Vos Identifiants</h1>
                            <p style="margin:10px 0 0;color:#e0e7ff;font-size:16px;">Bienvenue sur Support IA</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 20px;color:#374151;font-size:16px;line-height:1.6;">
                                Bonjour <strong>{$name}</strong>,
                            </p>
                            <p style="margin:0 0 30px;color:#374151;font-size:16px;line-height:1.6;">
                                Votre compte a été créé avec succès. Voici vos identifiants de connexion :
                            </p>
                            
                            <!-- Credentials Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:2px solid #e5e7eb;border-radius:8px;margin:0 0 30px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 15px;color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Identifiant</p>
                                        <p style="margin:0 0 20px;color:#111827;font-size:18px;font-family:monospace;font-weight:700;background:#ffffff;padding:12px;border-radius:6px;">{$identifier}</p>
                                        
                                        <p style="margin:0 0 15px;color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Mot de passe</p>
                                        <p style="margin:0;color:#111827;font-size:18px;font-family:monospace;font-weight:700;background:#ffffff;padding:12px;border-radius:6px;">{$password}</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{$loginUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px;">
                                            Se Connecter →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Security Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0 0;">
                                <tr>
                                    <td style="background:#fef3c7;border-left:4px solid #f59e0b;padding:15px;border-radius:6px;">
                                        <p style="margin:0;color:#92400e;font-size:14px;line-height:1.5;">
                                            ⚠️ <strong>Important :</strong> Conservez ces identifiants en lieu sûr. Ne les partagez avec personne.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9fafb;padding:30px;text-align:center;border-radius:0 0 12px 12px;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;color:#6b7280;font-size:13px;">
                                Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.
                            </p>
                            <p style="margin:10px 0 0;color:#9ca3af;font-size:12px;">
                                © 2026 Support IA - Tous droits réservés
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
