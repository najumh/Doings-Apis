<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'projectName',
        'repoUrl',
        'branch',
        'githubToken'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function commits()
    {
        return $this->hasMany(Commit::class);
    }
}
