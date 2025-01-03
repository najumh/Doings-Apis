<?php

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Services\Github;
use Illuminate\Http\Request;

class CommitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Commit $commit)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Commit $commit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Commit $commit)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Commit $commit)
    {
        //
    }

    public function logs($project_id)
    {
        $github = new Github();
        $github->pollCommits($project_id);
        return Commit::where('project_id', $project_id)->get();
    }
}
