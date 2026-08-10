<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documentation extends Model
{
    protected $fillable = ['projet_id', 'titre', 'contenu', 'file_path'];

    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentationChunk::class);
    }
}
