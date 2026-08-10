<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\Admin;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // ── Demande de reset ────────────────────────────────────────
    public function request(Request $request)
    {
        $data  = $request->validate([
            'email' => ['required', 'email', 'max:255'], 
            'type' => ['required', 'in:admin,client']
        ]);
        $email = $data['email'];
        $type  = $data['type'];

        $user = $type === 'admin'
            ? Admin::where('email', $email)->first()
            : Client::where('email', $email)->first();

        // On répond toujours "ok" pour ne pas révéler si l'email existe
        if (!$user) {
            return response()->json(['success' => true, 'message' => 'Si cet email existe, un lien a été envoyé.']);
        }

        // Supprimer les anciens tokens
        DB::table('password_reset_tokens_custom')->where('email', $email)->where('type', $type)->delete();

        $token = Str::random(64);
        DB::table('password_reset_tokens_custom')->insert([
            'email'      => $email,
            'type'       => $type,
            'token'      => $token,
            'expires_at' => now()->addHour(),
        ]);

        $frontendUrl = config('services.support.frontend_url');
        $resetUrl    = "{$frontendUrl}/reset-password?token={$token}&type={$type}";

        Mail::to($email)->send(new PasswordResetMail(
            name:     $user->name,
            resetUrl: $resetUrl,
            type:     $type,
        ));

        return response()->json(['success' => true, 'message' => 'Si cet email existe, un lien a été envoyé.']);
    }

    // ── Réinitialisation ────────────────────────────────────────
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'max:100'],
            'type'     => ['required', 'in:admin,client'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $row = DB::table('password_reset_tokens_custom')
            ->where('token', $data['token'])
            ->where('type', $data['type'])
            ->first();

        if (!$row || now()->isAfter($row->expires_at)) {
            return response()->json(['success' => false, 'error' => 'Lien invalide ou expiré.'], 422);
        }

        $user = $data['type'] === 'admin'
            ? Admin::where('email', $row->email)->first()
            : Client::where('email', $row->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur introuvable.'], 404);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        DB::table('password_reset_tokens_custom')->where('token', $data['token'])->delete();

        return response()->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
