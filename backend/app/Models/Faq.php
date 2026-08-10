<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['documentation_id', 'question', 'reponse', 'embedding'];

    protected $hidden = ['embedding']; // Ne pas exposer dans les réponses JSON

    public function documentation()
    {
        return $this->belongsTo(Documentation::class);
    }
}
