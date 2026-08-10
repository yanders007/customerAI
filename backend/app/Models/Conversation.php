<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $fillable = ['client_id', 'projet_id', 'uuid', 'status', 'escalated_at', 'pending_question'];
    protected $casts    = ['escalated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    public function client()      { return $this->belongsTo(Client::class); }
    public function projet()      { return $this->belongsTo(Projet::class); }
    public function messages()    { return $this->hasMany(Message::class)->orderBy('created_at'); }
}
