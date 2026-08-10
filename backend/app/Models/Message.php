<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'role', 'content',
        'tokens_input', 'tokens_output',
        'retrieval_source', 'chunks_used',
    ];

    protected $casts = [
        'tokens_input'     => 'integer',
        'tokens_output'    => 'integer',
        'chunks_used'      => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Tokens totaux consommés par ce message */
    public function getTotalTokensAttribute(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }
}
