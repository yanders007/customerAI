<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'email', 'client_identifier', 'password', 'last_login'];
    protected $hidden   = ['password'];
    protected $casts    = ['last_login' => 'datetime'];
    public $timestamps  = false;

    public function projets()
    {
        return $this->hasMany(Projet::class);
    }

    // Accesseur pour savoir si le client est actif (connecté dans les 7 derniers jours)
    public function getIsActiveAttribute(): bool
    {
        return $this->last_login && $this->last_login->gte(now()->subDays(7));
    }
}
