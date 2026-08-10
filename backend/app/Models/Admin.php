<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Admin extends Model
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    // Ne jamais renvoyer le hash du mot de passe dans les réponses JSON
    protected $hidden = ['password'];

    public $timestamps = false; // la table n'a que created_at, pas updated_at
}
