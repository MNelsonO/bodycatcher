<?php

$provider = env('LLM_PROVIDER', 'openai');

$defaultBase = match ($provider) {
    'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    default => 'https://api.openai.com/v1',
};

$defaultModel = match ($provider) {
    'gemini' => 'gemini-2.0-flash',
    default => 'gpt-4o-mini',
};

return [

    /*
    | provider: openai | gemini
    | Both use an OpenAI-compatible chat completions endpoint.
    | Gemini: https://generativelanguage.googleapis.com/v1beta/openai
    */

    'provider' => $provider,

    'api_key' => env('LLM_API_KEY', ''),

    'base_url' => env('LLM_BASE_URL', $defaultBase),

    'model' => env('LLM_MODEL', $defaultModel),

    'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 20),

    /*
    | When live assistant is on (desk setting), inbound WhatsApp is answered
    | with product-aware replies that keep the conversation topic. Rules on
    | Intelligence still apply when the live assistant is off.
    */

    'live_history_limit' => (int) env('LLM_LIVE_HISTORY_LIMIT', 12),

];
