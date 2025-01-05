<?php

use App\AI\Gemini;
use App\Http\Controllers\CommitController;
use App\Http\Controllers\ProjectController;
use App\Models\SourceCodeEmbedding;
use App\Services\Github;
use App\Services\GithubRepoLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Pgvector\Laravel\Distance;
use Pgvector\Laravel\Vector;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Termwind\Components\Raw;

Route::middleware(['auth:sanctum'])->group(function () {
 
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/projects', [ProjectController::class,'store']);
    Route::get('/projects',[ProjectController::class,'index']);
    Route::get('/projects/{id}/commitlogs',[CommitController::class,'logs']);
    Route::get('/ask-question',[ProjectController::class,'askQuestion']);
    


});


Route::get('/test',function(){


        $question = request()->question;
        $project_id = request()->project_id;
        $gemini = new Gemini();
        $question_vector =  $gemini->generateEmbeddings($question);
        $results = SourceCodeEmbedding::query()->nearestNeighbors('summaryEmbedding', $question_vector, Distance::L2)
                    ->where("project_id",$project_id)->take(10)->get();

        // $vector = new Vector([1,2,3]);
        
        // $result2 =  DB::table('source_code_embeddings')
        // ->select(DB::raw("fileName, sourceCode, summary, 1 - (summaryEmbedding  <-> $vector) as similarity"))
        // ->whereRaw(DB::raw("1 - (summaryEmbedding  <-> $vector) > .5 "))
        // ->where('project_id',$project_id)
        // ->orderBy('similarity', 'DESC')
        // ->take(10)
        // ->get();

        //dd("data: " . json_encode($results->toArray()));

        $content = "";

        foreach ($results as $result){
            $content .= "source: $result->fileName \ncode content: $result->sourceCode \nSummary of File: $result->summary \n\n";
        }

        $gemini = new Gemini();
        $response =  $gemini->askQuestion($content,$question);
        $contentType = $response->header('Content-Type');
        
        //$body = $response->getBody();
        $body = $response->getBody();
        // while (!$body->eof()) {
           
        //      $data =  explode("\n\r\n",trim($body->getContents()));
           
        //     $res = "";
        //     foreach($data as $d){
        //         $res .= (json_decode(substr(trim($d),6))->candidates[0]->content->parts[0]->text);
                
        //     }

        //     echo "data: " . $res;

           

        // }
        
        //dd($body);

        // $stream = new StreamedResponse(function () use ($body) {
        //     while (!$body->eof()) {
        //         echo $body->read(1024);
        //     }
        // });
    
        // $stream->headers->set('Content-Type', $contentType);
    
        // return $stream;

        return response()->stream(function() use($body,$results) {
            echo "data: " . json_encode(["files" => $results->toArray()]) . "\n\r\n";
            ob_flush();
            flush();
            while (!$body->eof()) {
                echo $body->read(1024);
                ob_flush();
                flush();
            }
            //return $results;
          },200,[
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache'
        ]);

        dd("start");
        return $response;
        foreach ($response as $chunk) {
            //if ($chunk === "\n") continue;
            dd($chunk);
        }
        dd("end");
        // return $gemini->askQuestion($content,$question);

        return response()->stream(function () use ($gemini,$content,$question) {
            try {
                $response =  $gemini->askQuestion($content,$question);
                print_r ($response);     
                foreach ($response as $chunk) {
                    if ($chunk === "\n") continue;
                    dd($chunk);
                    $data = json_decode($chunk, true);
                    if (!$data) continue;
                    dd($data);

                    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        $text = $data['candidates'][0]['content']['parts'][0]['text'];
                        echo "data: " . json_encode([
                            'text' => $text,
                            'done' => false
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

                echo "data: " . json_encode(['done' => true]) . "\n\n";
                ob_flush();
                flush();
            } catch (\Exception $e) {
                Log::error('Gemini API Error: ' . $e->getMessage());
                echo "data: " . json_encode([
                    'error' => 'An error occurred while processing your request.',
                    'done' => true
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no'
        ]);

    // $github = new Github();
    // $github->indexGithubRepo(1,env('GITHUB_TOKEN'));
    // // return "hi";
    // $gemini = new Gemini();
    // return $gemini->generateEmbeddings("hellow world");


    // $githubUrl = "https://github.com/najumh/testing";
    // ini_set('max_execution_time', '0');
    // $loader = new GithubRepoLoader(
    //     'https://github.com/elliott-chong/chatpdf-yt',
    //     [
    //         'accessToken' => env('GITHUB_TOKEN'),
    //         'branch' => 'main',
    //         'recursive' => true,
    //         // 'verbose' => true,
    //         'ignoreFiles' => ['package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lockb'],
    //         'unknown' => 'warn',
    //         'maxConcurrency' => 5
    //     ]
    // );
    
    // $documents = $loader->load();

    // return $documents;

    
    // foreach ($documents as $document) {
    //     echo $document['metadata']['source'] . ': ' . $document['pageContent'];
    // }

    // $github = new Github();

    // //najumh/examiner
    // return $github->pollCommits(7);
    // $commits = $github->getCommits('najumh','examiner');
    // return $commits;
});