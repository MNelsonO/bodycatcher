<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\TextBlock;
use Anthropic\RequestOptions;

/**
 * Native Claude client. The lead analysis is a single structured read, so one
 * Messages API call is enough. Laravel still decides what is stored and sent.
 */
class AnthropicClient implements LlmClient
{
    public function __construct(private LlmConfiguration $configuration) {}

    public function complete(string $system, string $user): string
    {
        if (! $this->configuration->ready()) {
            throw new LlmRequestFailed('The language model is not configured.');
        }

        $timeout = (int) config('ai.timeout_seconds');
        $base = trim((string) config('ai.base_url'));

        $client = new Client(
            apiKey: (string) config('ai.api_key'),
            baseUrl: $base !== '' ? $base : null,
        );

        try {
            $message = $client->messages->create(
                maxTokens: 16000,
                messages: [
                    ['role' => 'user', 'content' => $user],
                ],
                model: $this->configuration->model(),
                system: $system,
                outputConfig: ['effort' => $this->effort()],
                requestOptions: RequestOptions::with(
                    timeout: (float) ($timeout > 0 ? $timeout : 60),
                    maxRetries: 2,
                ),
            );
        } catch (APIConnectionException) {
            throw new LlmRequestFailed('The model request did not complete.');
        } catch (APIStatusException $exception) {
            throw new LlmRequestFailed('The model request failed with HTTP '.($exception->status ?? 0).'.');
        } catch (AnthropicException) {
            throw new LlmRequestFailed('The model request failed.');
        }

        if ($message->stopReason === 'refusal') {
            throw new LlmRequestFailed('The model declined to read this conversation.');
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            throw new LlmRequestFailed('The model response had no text.');
        }

        return $text;
    }

    /**
     * @return 'low'|'medium'|'high'|'xhigh'|'max'
     */
    private function effort(): string
    {
        return match (config('ai.effort')) {
            'low' => 'low',
            'high' => 'high',
            'xhigh' => 'xhigh',
            'max' => 'max',
            default => 'medium',
        };
    }
}
