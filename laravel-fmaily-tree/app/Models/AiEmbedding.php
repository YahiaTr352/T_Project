<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiEmbedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'chunk_key',
        'content_hash',
        'content',
        'embedding',
        'model',
        'dimensions',
    ];

    protected $casts = [
        'embedding' => 'array',
        'dimensions' => 'integer',
    ];

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }
}
