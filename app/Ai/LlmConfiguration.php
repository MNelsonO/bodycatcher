<?php

namespace App\Ai;

class LlmConfiguration
{
    public function ready(): bool
    {
        return $this->value('ai.api_key') !== ''
            && $this->value('ai.base_url') !== ''
            && $this->value('ai.model') !== '';
    }

    public function provider(): string
    {
        $provider = $this->value('ai.provider');

        return $provider !== '' ? $provider : 'openai';
    }

    /**
     * @return list<array{key: string, configured: bool}>
     */
    public function variables(): array
    {
        return [
            ['key' => 'LLM_PROVIDER', 'configured' => $this->provider() !== ''],
            ['key' => 'LLM_API_KEY', 'configured' => $this->value('ai.api_key') !== ''],
            ['key' => 'LLM_BASE_URL', 'configured' => $this->value('ai.base_url') !== ''],
            ['key' => 'LLM_MODEL', 'configured' => $this->value('ai.model') !== ''],
        ];
    }

    private function value(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
