<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class SourceCodeEmbedding extends Model
{
    use HasNeighbors;

    protected $casts = ['summaryEmbedding' => Vector::class];
}
