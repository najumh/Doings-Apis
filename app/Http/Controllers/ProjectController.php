<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Github;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::whereHas('users', function ($query) {
            $query->where('user_id', Auth::user()->id);
        })->get();

        return $projects;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $newProject = $request->validate([
            'projectName' => 'required',
            'repoUrl' => 'required',
            'githubToken' => 'sometimes|nullable'
        ]);

        try {
            $project = Project::create($newProject);
            $project->users()->attach($request->user());
            $github = new Github();
            $github->pollCommits($project->id);
            return $project->load('users');
        } catch (\Throwable $th) {
            return response()->json(["error" => $th->getMessage()],500);
        }
        


    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        //
    }
}
