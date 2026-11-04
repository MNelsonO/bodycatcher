<?php

return [

    /*
    | "anthropic" calls Claude through the official Anthropic SDK and needs only
    | LLM_API_KEY (LLM_MODEL defaults to claude-opus-5-5). Anything else uses an
    | OpenAI-compatible chat completions endpoint, where all three of
    | LLM_API_KEY, LLM_BASE_URL, and LLM_MODEL must be set. If the provider is
    | not ready the desk reports it as missing and does not invent a summary.
    */

    'provider' => env('LLM_PROVIDER', 'openai'),

    'api_key' => env('LLM_API_KEY', ''),

    'base_url' => env('LLM_BASE_URL', ''),

    'model' => env('LLM_MODEL', ''),

    'effort' => env('LLM_EFFORT', 'medium'),

    'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 60),

];
