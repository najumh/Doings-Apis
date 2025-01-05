<?php

namespace App\Http\Controllers;

use App\AI\Gemini;
use App\Models\Project;
use App\Models\SourceCodeEmbedding;
use App\Services\Github;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;

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
            $github->indexGithubRepo($project->id,env('GITHUB_TOKEN'));
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

    public function askQuestion(Request $request)
    {
        $question = request()->question;
        $project_id = request()->project_id;
        $gemini = new Gemini();
        $question_vector =  $gemini->generateEmbeddings($question);
        $results = SourceCodeEmbedding::query()->nearestNeighbors('summaryEmbedding', $question_vector, Distance::L2)
                    ->where("project_id",$project_id)
                    // ->whereRaw(DB::raw("neighbor_distance > .5"))
                    ->take(7)->get();

        
        $content = "";

        foreach ($results as $result){
            $content .= "source: $result->fileName \ncode content: $result->sourceCode \nSummary of File: $result->summary \n\n";
        }

        $gemini = new Gemini();
        $response =  $gemini->askQuestion($content,$question);
        $contentType = $response->header('Content-Type');
        
        $body = $response->getBody();
        
        return response()->stream(function() use($body,$results) {
            echo "data: " . json_encode(["files" => $results->toArray()]) . "\n\n";
            ob_flush();
            flush();
            while (!$body->eof()) {
                echo $body->read(1024);
                ob_flush();
                flush();
            }

            // echo "data: stop\n\n";
            // echo "event: stop\n";
            echo "event: stop\n";
            echo "data: stop\n\n";
            ob_flush();
            flush();
            //return $results;
          },200,[
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache'
        ]);

    }
}
