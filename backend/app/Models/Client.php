<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'email', 'client_identifier', 'password', 'last_login', 'is_active', 'support_email'];
    protected $hidden   = ['password'];
    protected $casts    = ['last_login' => 'datetime', 'is_active' => 'boolean'];
    public $timestamps  = false;

    public function projets()
    {
        return $this->hasMany(Projet::class);
    }
}
