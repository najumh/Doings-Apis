<?php

namespace App\Services\Github;

use Exception;
use Illuminate\Support\Facades\Http;

trait FileProcessor
{
    protected function processRepo(): array
    {
        try {
            $files = $this->fetchRepoFiles($this->initialPath);
            return $this->processFiles($files);
        } catch (Exception $e) {
            $this->handleError("Failed to process directory: {$this->initialPath}, {$e->getMessage()}");
            throw $e;
        }
    }

    protected function processFiles(array $files): array
    {
        $documents = [];
        foreach ($files as $file) {
            if ($file['type'] !== 'dir' && $this->shouldIgnore($file['path'], $file['type'])) {
                continue;
            }

            if ($file['type'] === 'file') {
                try {
                    $content = $this->fetchFileContent($file);
                    $documents[] = [
                        'content' => $content,
                        'metadata' => [
                            'source' => $file['path'],
                            'repository' => "{$this->baseUrl}/{$this->owner}/{$this->repo}",
                            'branch' => $this->branch,
                        ],
                    ];
                } catch (Exception $e) {
                    $this->handleError("Failed to fetch file content: {$file['path']}, {$e->getMessage()}");
                }
            } elseif ($this->recursive) {
                $documents = array_merge($documents, $this->processDirectory($file['path']));
            }
        }

        return $documents;
    }

    protected function processDirectory(string $path): array
    {
        try {
            $files = $this->fetchRepoFiles($path);
            return $this->processFiles($files);
        } catch (Exception $e) {
            $this->handleError("Failed to process directory: {$path}, {$e->getMessage()}");
            return [];
        }
    }

    protected function fetchRepoFiles(string $path): array
    {
        $url = "{$this->apiUrl}/repos/{$this->owner}/{$this->repo}/contents/{$path}?ref={$this->branch}";
        
        $this->log("Fetching {$url}");
        
        $response = Http::withHeaders($this->headers)
            ->get($url);

        if (!$response->successful()) {
            throw new Exception("Unable to fetch repository files: {$response->status()} {$response->body()}");
        }

        $data = $response->json();
        return is_array($data) ? $data : [$data];
    }

    protected function fetchFileContent(array $file): string
    {
        $this->log("Fetching {$file['download_url']}");
        
        $response = Http::withHeaders($this->headers)
            ->get($file['download_url']);

        return $response->body();
    }

    protected function shouldIgnore(string $path, string $fileType): bool
    {
        if ($fileType !== 'dir' && $this->isBinaryPath($path)) {
            return true;
        }

        if (!empty($this->ignorePaths)) {
            return false;
        }

        return $fileType !== 'dir' && collect($this->ignoreFiles)->contains(function ($pattern) use ($path) {
            if (is_string($pattern)) {
                return $path === $pattern;
            }
            return preg_match($pattern, $path);
        });
    }

    protected function isBinaryPath(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, $this->binaryExtensions);
    }
}