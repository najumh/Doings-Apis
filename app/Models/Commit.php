<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commit extends Model
{
    protected $fillable = [
        'project_id',
        'hash',
        'message',
        'authorName',
        'authroAvatar',
        'summary'
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }
}
