<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Projet extends Model
{
    protected $table = 'projets';
    protected $fillable = ['client_id', 'nom_projet'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function documentations()
    {
        return $this->hasMany(Documentation::class);
    }
}
