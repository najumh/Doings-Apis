<?php

namespace App\Services\Github;

trait SubmoduleProcessor
{
    protected function getSubmoduleInfo(): void
    {
        $this->log("Loading info about submodules...");
        
        $repoFiles = $this->fetchRepoFiles("");
        $gitmodulesFile = collect($repoFiles)->firstWhere('name', '.gitmodules');
        
        if ($gitmodulesFile) {
            $gitmodulesContent = $this->fetchFileContent($gitmodulesFile);
            $this->submoduleInfos = $this->parseGitmodules($gitmodulesContent);
        } else {
            $this->submoduleInfos = [];
        }

        $this->log("Found " . count($this->submoduleInfos) . " submodules");
    }

    protected function parseGitmodules(string $gitmodulesContent): array
    {
        if (!str_ends_with($gitmodulesContent, "\n")) {
            $gitmodulesContent .= "\n";
        }

        preg_match_all('/\[submodule "(.*?)"\]\n((\s+.*?\s*=\s*.*?\n)*)/m', $gitmodulesContent, $matches, PREG_SET_ORDER);

        $submoduleInfos = [];
        foreach ($matches as $match) {
            $name = $match[1];
            $propertyLines = $match[2];

            preg_match_all('/\s+(.*?)\s*=\s*(.*?)\s/m', $propertyLines, $propertyMatches, PREG_SET_ORDER);

            $path = null;
            $url = null;

            foreach ($propertyMatches as $propertyMatch) {
                $key = $propertyMatch[1];
                $value = $propertyMatch[2];

                switch ($key) {
                    case 'path':
                        $path = $value;
                        break;
                    case 'url':
                        $url = rtrim($value, '.git');
                        break;
                }
            }

            if (!$path || !$url) {
                throw new \Exception("Missing properties for submodule {$name}");
            }

            $files = $this->fetchRepoFiles($path);
            $submoduleInfos[] = [
                'name' => $name,
                'path' => $path,
                'url' => $url,
                'ref' => $files[0]['sha'],
            ];
        }

        return $submoduleInfos;
    }

    protected function loadSubmodule(array $submoduleInfo): array
    {
        if (!str_starts_with($submoduleInfo['url'], $this->baseUrl)) {
            $this->log("Ignoring external submodule {$submoduleInfo['url']}.");
            return [];
        }

        if (!str_starts_with($submoduleInfo['path'], $this->initialPath)) {
            $this->log("Ignoring submodule {$submoduleInfo['url']}, as it is not on initial path.");
            return [];
        }

        $this->log("Accessing submodule {$submoduleInfo['name']} ({$submoduleInfo['url']})...");

        $loader = new self($submoduleInfo['url'], [
            'accessToken' => $this->accessToken,
            'apiUrl' => $this->apiUrl,
            'baseUrl' => $this->baseUrl,
            'branch' => $submoduleInfo['ref'],
            'recursive' => $this->recursive,
            'processSubmodules' => $this->processSubmodules,
            'unknown' => $this->unknown,
            'ignoreFiles' => $this->ignoreFiles,
            'ignorePaths' => $this->ignorePaths,
            'verbose' => $this->verbose,
            'maxConcurrency' => $this->maxConcurrency,
            'maxRetries' => $this->maxRetries,
        ]);

        return $loader->load();
    }
}