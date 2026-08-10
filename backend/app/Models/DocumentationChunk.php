<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentationChunk extends Model
{
    protected $fillable = ['documentation_id', 'chunk_index', 'content', 'embedding'];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function documentation()
    {
        return $this->belongsTo(Documentation::class);
    }
}
