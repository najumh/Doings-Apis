<?php

namespace App\Services;

use App\AI\Gemini;
use App\Models\Commit;
use App\Models\Project;
use App\Models\SourceCodeEmbedding;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class Github{
    protected $client;
    protected $baseUrl = 'https://api.github.com';
    protected $token;

    public function __construct()
    {
        // Your GitHub personal access token
        $this->token = env('GITHUB_TOKEN');
        $this->client = new Client();
    }

    public function getCommits($owner, $repo, $branch = 'main', $perPage = 30, $page = 1)
    {
        $url = "/repos/{$owner}/{$repo}/commits";
        
        $response = $this->client->get($this->baseUrl . $url, [
            'query' => [
                'sha' => $branch, // Specify the branch
                'per_page' => $perPage, // Number of results per page
                'page' => $page, // The page number
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token, // Use the token for authentication
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        $commits = json_decode($response->getBody()->getContents(), true);

        usort($commits, function($a, $b) {
            $dateA = strtotime($a['commit']['author']['date']);
            $dateB = strtotime($b['commit']['author']['date']);
            return $dateB - $dateA; // for descending order
        });

        $commitsSlice = array_slice($commits, 0, 10);

        // Map through each commit and extract necessary fields
        $formattedCommits = array_map(function($commit) {
            return [
                'hash' => $commit['sha'] ?? '', // Extract commit hash
                'message' => $commit['commit']['message'] ?? '', // Extract commit message
                'authorName' => $commit['commit']['author']['name'] ?? '', // Extract author name
                'authorAvatar' => $commit['author']['avatar_url'] ?? '', // Extract author avatar URL
                'date' => $commit['commit']['author']['date'] ?? '', // Extract commit date
            ];
        }, $commitsSlice);

        return $formattedCommits;
    }

    public function pollCommits ($project_id)
    {
        $project = Project::findOrFail($project_id);
        $repoUrl = explode("/", $project->repoUrl); 
        $owner = $repoUrl[count($repoUrl)-2]; 
        $repo = $repoUrl[count($repoUrl)-1];
        $commitHashes = $this->getCommits($owner,$repo,$project->branch);
        $unprocessCommits = $this->filterUnprocessedCommits($project_id,$commitHashes);

        foreach($unprocessCommits as $key=>$commit){
            $unprocessCommits[$key]["project_id"] = $project_id;
            $unprocessCommits[$key]["summary"] = $this->aiSummarize($project->repoUrl,$commit['hash']);
            $unprocessCommits[$key]["created_at"] = new \dateTime;
            $unprocessCommits[$key]["updated_at"] = new \dateTime;
        }

        Commit::insert($unprocessCommits);
        return $unprocessCommits;

    }

    public function indexGithubRepo($project_id,$githubToken="")
    {
        $project = Project::findOrFail($project_id);
        
        $docs = $this->loadGithubRepo($project->repoUrl,$project->branch,$githubToken);
        
        $gemini = new Gemini();

        foreach($docs as $doc){
            //dd($doc["metadata"]);
            
                $summary = $gemini->summerizeCode($doc);
                $embedding = $gemini->generateEmbeddings($summary);
                $scEmbedding = new SourceCodeEmbedding();
                $scEmbedding->project_id = $project_id;
                $scEmbedding->fileName = $doc['metadata']['source'];
                $scEmbedding->sourceCode = $doc["content"];
                $scEmbedding->summary = $summary;
                $scEmbedding->summaryEmbedding = $embedding;
                $scEmbedding->save();
            
        }
        return true;
    
    }

    public function loadGithubRepo($githubUrl, $branch="main", $githubToken="")
    {
        
        ini_set('max_execution_time', '0');
        
        $loader = new GithubRepoLoader(
            $githubUrl,
            [
                'accessToken' => $githubToken,
                'branch' => $branch,
                'recursive' => true,
                // 'verbose' => true,
                'ignoreFiles' => ['package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lockb'],
                'unknown' => 'warn',
                'maxConcurrency' => 5
            ]
        );
        
        $documents = $loader->load();

        return $documents;

    }

    public function filterUnprocessedCommits($project_id,$commitHashes)
    {
        $processedHashes = Commit::where('project_id',$project_id)->pluck('hash')->toArray();

        $unprocessCommits = array_filter($commitHashes,function($commit) use($processedHashes){
            return !in_array($commit['hash'],$processedHashes);
        });

        return $unprocessCommits;

    }

    public function aiSummarize($repoUrl,$hash)
    {   
        $summary = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3.diff',
        ])->get("$repoUrl/commit/$hash.diff"); 
        
        $gemini = new Gemini();
        $aiSummary = $gemini->chat($summary->body());
        return $aiSummary;
        
    }

}
