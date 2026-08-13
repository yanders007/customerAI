<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'email', 'client_identifier', 'password', 'last_login', 'last_seen', 'is_active', 'support_email'];
    protected $hidden   = ['password'];
    protected $casts    = ['last_login' => 'datetime', 'last_seen' => 'datetime', 'is_active' => 'boolean'];
    public $timestamps  = false;

    public function projets()
    {
        return $this->hasMany(Projet::class);
    }

    public function aiUsages()
    {
        return $this->hasMany(AiUsage::class);
    }

    /**
     * Vérifie si le client est en ligne (actif dans les 2 dernières minutes)
     */
    public function isOnline(): bool
    {
        if (!$this->last_seen) {
            return false;
        }
        return $this->last_seen->diffInMinutes(now()) < 2;
    }
}
