<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AiUsage extends Model
{
    protected $fillable = [
        'client_id',
        'admin_id',
        'conversation_id',
        'message_id',
        'ai_config_id',
        'request_type',
        'provider',
        'model',
        'tokens_input',
        'tokens_output',
        'embedding_tokens',
        'is_estimated',
        'metadata',
    ];

    protected $casts = [
        'tokens_input'     => 'integer',
        'tokens_output'    => 'integer',
        'embedding_tokens' => 'integer',
        'is_estimated'     => 'boolean',
        'metadata'         => 'array',
    ];

    protected $appends = ['total_tokens'];

    public static function recordUsage(array $attributes): ?self
    {
        try {
            return static::create($attributes);
        } catch (\Throwable $e) {
            // Les métriques ne doivent jamais bloquer une réponse client.
            Log::warning('AiUsage: impossible d’enregistrer la consommation', [
                'request_type' => $attributes['request_type'] ?? null,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getTotalTokensAttribute(): int
    {
        return (int) $this->tokens_input + (int) $this->tokens_output + (int) $this->embedding_tokens;
    }

    public function client()       { return $this->belongsTo(Client::class); }
    public function admin()        { return $this->belongsTo(Admin::class); }
    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function message()      { return $this->belongsTo(Message::class); }
    public function aiConfig()     { return $this->belongsTo(AiConfig::class); }
}
