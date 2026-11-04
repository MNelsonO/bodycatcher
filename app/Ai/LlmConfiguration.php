<?php

namespace App\Ai;

class LlmConfiguration
{
    public const ANTHROPIC = 'anthropic';

    public const OPENAI_COMPATIBLE = 'openai';

    public const DEFAULT_CLAUDE_MODEL = 'claude-opus-5-5';

    public function provider(): string
    {
        return $this->value('ai.provider') === self::ANTHROPIC ? self::ANTHROPIC : self::OPENAI_COMPATIBLE;
    }

    public function model(): string
    {
        $model = $this->value('ai.model');

        if ($model === '' && $this->provider() === self::ANTHROPIC) {
            return self::DEFAULT_CLAUDE_MODEL;
        }

        return $model;
    }

    public function ready(): bool
    {
        if ($this->provider() === self::ANTHROPIC) {
            return $this->value('ai.api_key') !== '';
        }

        return $this->value('ai.api_key') !== ''
            && $this->value('ai.base_url') !== ''
            && $this->value('ai.model') !== '';
    }

    /**
     * @return list<array{key: string, configured: bool}>
     */
    public function variables(): array
    {
        if ($this->provider() === self::ANTHROPIC) {
            return [
                ['key' => 'LLM_PROVIDER', 'configured' => true],
                ['key' => 'LLM_API_KEY', 'configured' => $this->value('ai.api_key') !== ''],
                ['key' => 'LLM_MODEL', 'configured' => true],
            ];
        }

        return [
            ['key' => 'LLM_API_KEY', 'configured' => $this->value('ai.api_key') !== ''],
            ['key' => 'LLM_BASE_URL', 'configured' => $this->value('ai.base_url') !== ''],
            ['key' => 'LLM_MODEL', 'configured' => $this->value('ai.model') !== ''],
        ];
    }

    /**
     * @return array{provider: string, provider_label: string, model: string}
     */
    public function summary(): array
    {
        return [
            'provider' => $this->provider(),
            'provider_label' => $this->provider() === self::ANTHROPIC ? 'Claude (Anthropic)' : 'OpenAI-compatible',
            'model' => $this->model(),
        ];
    }

    private function value(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
