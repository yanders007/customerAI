<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiConfig extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'api_key_encrypted',
        'is_active',
        'system_prompt',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Masquer la clé dans toutes les sérialisations JSON ─────────────────
    protected $hidden = ['api_key_encrypted'];

    // ── Accesseur : décrypte la clé à la demande ───────────────────────────
    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->attributes['api_key_encrypted'])) {
            return null;
        }
        
        try {
            return Crypt::decryptString($this->attributes['api_key_encrypted']);
        } catch (\Exception $e) {
            \Log::error('Erreur décryptage clé API', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Mutateur : chiffre avant sauvegarde ───────────────────────────────
    public function setApiKeyAttribute(string $value): void
    {
        if (!empty($value)) {
            $this->attributes['api_key_encrypted'] = Crypt::encryptString($value);
        }
    }

    // ── Récupère la config active (avec cache 5 min) ───────────────────────
    public static function getActive(): ?self
    {
        return cache()->remember('ai_config_active', 300, function () {
            return self::where('is_active', true)->latest()->first();
        });
    }

    // ── Invalide le cache quand une config est sauvegardée ─────────────────
    protected static function booted(): void
    {
        static::saved(fn() => cache()->forget('ai_config_active'));
        static::deleted(fn() => cache()->forget('ai_config_active'));
    }
}
