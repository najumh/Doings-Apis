<?php

use App\Http\Controllers\CommitController;
use App\Http\Controllers\ProjectController;
use App\Services\Github;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;



Route::middleware(['auth:sanctum'])->group(function () {
 
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/projects', [ProjectController::class,'store']);
    Route::get('/projects',[ProjectController::class,'index']);
    Route::get('/projects/{id}/commitlogs',[CommitController::class,'logs']);
    


});


Route::get('/test',function(){


    $github = new Github();

    //najumh/examiner
    return $github->pollCommits(7);
    $commits = $github->getCommits('najumh','examiner');
    return $commits;
});