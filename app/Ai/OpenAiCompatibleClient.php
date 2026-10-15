<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleClient implements LlmClient
{
    public function __construct(private LlmConfiguration $configuration) {}

    public function complete(string $system, string $user): string
    {
        if (! $this->configuration->ready()) {
            throw new LlmRequestFailed('The language model is not configured.');
        }

        $base = rtrim((string) config('ai.base_url'), '/');
        $timeout = (int) config('ai.timeout_seconds');

        try {
            $response = Http::withToken((string) config('ai.api_key'))
                ->acceptJson()
                ->timeout($timeout > 0 ? $timeout : 20)
                ->post($base.'/chat/completions', [
                    'model' => config('ai.model'),
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new LlmRequestFailed('The model request did not complete.');
        }

        if ($response->failed()) {
            throw new LlmRequestFailed('The model request failed with HTTP '.$response->status().'.');
        }

        /** @var mixed $decoded */
        $decoded = $response->json();
        $content = $this->content($decoded);

        if ($content === null) {
            throw new LlmRequestFailed('The model response had no text.');
        }

        return $content;
    }

    private function content(mixed $decoded): ?string
    {
        if (! is_array($decoded)) {
            return null;
        }

        $choices = $decoded['choices'] ?? null;

        if (! is_array($choices) || ! is_array($choices[0] ?? null)) {
            return null;
        }

        $message = $choices[0]['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        $content = $message['content'] ?? null;

        return is_string($content) && trim($content) !== '' ? $content : null;
    }
}
