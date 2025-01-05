<?php

namespace App\AI;

use GuzzleHttp\Psr7\Stream;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;


class Gemini 
{
    protected $endpoing = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=";
    protected $embdeddingsEndpoing = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=";

    protected $prompt = "You are an expert programmer, and you are trying to summarize a git diff.
    Reminders about the git diff format:
    For every file, there are a few metadata lines, like (for example):
    \'\'\'
    diff --git a/lib/index.js b/lib/index.js 
    index aadf691..bfef603 100644
    --- a/lib/index.js 
    +++ b/lib/index.js
    \'\'\'
    This means that \'11b/index.js\' was modified in this commit. Note that this is only an example.
    Then there is a specifier of the lines that were modified.
    A line starting with \'+\' means it was added.
    A line that starting with \'-\' means that line was deleted.
    A line that starts with neither \'+\' nor \'-\' is code given for context and better understanding.
    It is not part of the diff.
    [...]
    EXAMPLE SUMMARY COMMENTS:
    \'\'\'
    * Raised the amount of returned recordings from \'10\' to \'100\' [packages/server/recordings_api.ts]. [packages/server/constants.ts]
    * Fixed a typo in the github action name [.github/workflows/gpt-commit-summarizer.yml]
    * Moved the \'octokitl\' initialization to a separate file [src/octokit.ts], [sro=c/index.ts]
    * Added an OpenAI API for completions [packages/utils/apis/openai.ts]
    * Lowered numeric tolerance for test files
    \'\'\'
    Most commits will have less comments than this examples list.
    The last comment does not include the file names.
    because there were more than two relevant files in the hypothetical commit.
    Do not include parts of the example in your summary.
    It is given only as an example of appropriate comments. 
    Please summarise the following diff file: \n\n ";


    protected $codePrompt = "
        You are an intelligent senior most software engineer who specialises in onboarding junior software engineers onto projects
        You are onboarding a junior software engineer and explaining to them the purpoise of the {source} file

    Here is the code:
    ---
    {code}
    ---

        Give a summary no more than 100 words of the code above.
    ";


    protected $quetionPromt = "
        You are a ai code assistant who answers questions about the codebase. Your target audience is a technical intern who is looking to understan the codebase.
        Al assistant is a brand new, powerful, human-like artificial intelligence.
        The traits of AI include expert knowledge, helpfulness, cleverness, and articulateness.
        Al is a well-behaved and well-mannered individual.
        AI is always friendly, kind, and inspiring, and he is eager to provide vivid and thoughtful responses to the user.
        Al has the sum of all knowledge in their brain, and is able to accurately answer nearly any question about any topic in conversation.
        If the question is asking about code or a specific file, AI will provide the detailed answer, giving step by step instructions, including code snippets.
        
        START CONTEXT BLOCK
        {context}
        END OF CONTEXT BLOCK
        
        START QUESTION
        {question}
        END OF QUESTION
        
        Al assistant will take into account any CONTEXT BLOCK that is provided in a conversation.
        If the context does not provide the answer to question, the AI assistant will say, \"I'm sorry, but I don't know the answer.
        AI assistant will not apologize for previous responses, but instead will indicated new information was gained.
        AI assistant will not invent anything that is not drawn directly from the context.
        Answer in markdown syntax, with code snippets if needed. Be as detailed as possible when answering, mke sure there is no error.
        
    
    ";




    protected $key;

    public function __construct()
    {
        $this->key = env('GEMINI_API_KEY');
    }

    public function chat($text)
    {

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',  // Setting Content-Type header
            ])->post($this->endpoing. $this->key, [
                  // Adding query parameter 'key' to URL
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->prompt . $text]
                        ]
                    ]
                ]
            ]);
            
        return $response->json("candidates.0.content.parts.0.text");
    }

    public function summerizeCode($document)
    {
        $prompt = str_replace("{source}",$document['metadata']['source'], $this->codePrompt);
        $prompt = str_replace("{code}",substr($document['content'],0,1000), $prompt);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',  // Setting Content-Type header
            ])->post($this->endpoing. $this->key, [
                  // Adding query parameter 'key' to URL
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
        return $response->json("candidates.0.content.parts.0.text");
    }

    public function generateEmbeddings($text)
    {
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json', 
            ])->post($this->embdeddingsEndpoing . $this->key, [
                'model' => 'models/text-embedding-004',
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

        return $response->json("embedding.values");
    }

    public function askQuestion($context,$question)
    {


        $prompt = str_replace("{context}",$context, $this->quetionPromt);
        $prompt = str_replace("{question}",$question, $prompt);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent?alt=sse&key={$this->key}";

        // Define the payload data
        $data = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => $prompt
                        ]
                    ]
                ]
            ]
        ];

        // Send the POST request to the API
        $response =  Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->withOptions([
            'stream' => true,
        ])// Disable SSL verification if needed
            ->timeout(0) // Disable timeout (for long-running requests)
            ->post($url, $data);
        return $response;
        return $response->getBody();
        return $this->parseStreamedResponse($response->getBody());

    }

    private function parseStreamedResponse(Stream $stream)
    {
        $buffer = '';
        
        while (!$stream->eof()) {
            $chunk = $stream->read(1024);
            $buffer .= $chunk;
            
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                if (trim($line)) {
                    yield $line;
                }
            }
        }
        
        if (trim($buffer)) {
            yield $buffer;
        }
    }


}









