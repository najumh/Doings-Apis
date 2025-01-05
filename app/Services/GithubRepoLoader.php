<?php

namespace App\Services;

use App\Services\Github\FileProcessor;
use App\Services\Github\SubmoduleProcessor;
use Exception;
use Illuminate\Support\Facades\Log;

class GithubRepoLoader
{
    use FileProcessor, SubmoduleProcessor;

    protected string $baseUrl;
    protected string $apiUrl;
    protected string $owner;
    protected string $repo;
    protected string $initialPath;
    protected array $headers;
    protected string $branch;
    protected bool $recursive;
    protected bool $processSubmodules;
    protected string $unknown;
    protected ?string $accessToken;
    protected array $ignoreFiles;
    protected bool $verbose;
    protected int $maxConcurrency;
    protected int $maxRetries;
    protected array $ignorePaths;
    protected array $submoduleInfos = [];
    protected array $binaryExtensions;

    public function __construct(
        string $githubUrl,
        array $options = []
    ) {
        $this->baseUrl = $options['baseUrl'] ?? 'https://github.com';
        $this->apiUrl = $options['apiUrl'] ?? 'https://api.github.com';
        $this->accessToken = $options['accessToken'] ?? env('GITHUB_ACCESS_TOKEN');
        $this->branch = $options['branch'] ?? 'main';
        $this->recursive = $options['recursive'] ?? true;
        $this->processSubmodules = $options['processSubmodules'] ?? false;
        $this->unknown = $options['unknown'] ?? 'warn';
        $this->ignoreFiles = $options['ignoreFiles'] ?? [];
        $this->verbose = $options['verbose'] ?? false;
        $this->maxConcurrency = $options['maxConcurrency'] ?? 2;
        $this->maxRetries = $options['maxRetries'] ?? 2;
        $this->ignorePaths = $options['ignorePaths'] ?? [];
        
        $extracted = $this->extractOwnerAndRepoAndPath($githubUrl);
        $this->owner = $extracted['owner'];
        $this->repo = $extracted['repo'];
        $this->initialPath = $extracted['path'];

        $this->headers = [
            'User-Agent' => 'Laravel/GithubRepoLoader',
        ];

        if ($this->accessToken) {
            $this->headers['Authorization'] = "Bearer {$this->accessToken}";
        }

        if ($this->processSubmodules && !$this->recursive) {
            throw new Exception('Input property "recursive" must be true if "processSubmodules" is true.');
        }

        $this->binaryExtensions = json_decode(file_get_contents(__DIR__ . '/Github/binary-extensions.json'), true);
    }

    protected function extractOwnerAndRepoAndPath(string $url): array
    {
        $pattern = "#{$this->baseUrl}/([^/]+)/([^/]+)(/tree/[^/]+/(.+))?#i";
        if (!preg_match($pattern, $url, $matches)) {
            throw new Exception('Invalid GitHub URL format.');
        }

        return [
            'owner' => $matches[1],
            'repo' => $matches[2],
            'path' => $matches[4] ?? '',
        ];
    }

    public function load(): array
    {
        $this->log("Loading documents from {$this->baseUrl}/{$this->owner}/{$this->repo}/{$this->initialPath}...");

        $documents = $this->processRepo();

        if ($this->processSubmodules) {
            $this->getSubmoduleInfo();
            foreach ($this->submoduleInfos as $submoduleInfo) {
                $documents = array_merge($documents, $this->loadSubmodule($submoduleInfo));
            }
        }

        return $documents;
    }

    protected function handleError(string $message): void
    {
        switch ($this->unknown) {
            case 'ignore':
                break;
            case 'warn':
                Log::warning($message);
                break;
            case 'error':
                throw new Exception($message);
            default:
                throw new Exception("Unknown unknown handling: {$this->unknown}");
        }
    }

    protected function log(string $message): void
    {
        if ($this->verbose) {
            Log::info($message);
        }
    }
}