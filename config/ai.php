<?php

$provider = env('LLM_PROVIDER', 'openai');

$defaultBase = match ($provider) {
    'anthropic' => '',
    'gemini' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    default => 'https://api.openai.com/v1',
};

$defaultModel = match ($provider) {
    'anthropic' => 'claude-opus-5-5',
    'gemini' => 'gemini-2.0-flash',
    default => 'gpt-4o-mini',
};

return [

    /*
    | provider: openai | gemini | anthropic
    | openai and gemini use an OpenAI-compatible chat completions endpoint.
    | anthropic calls Claude through the official Anthropic PHP SDK; only
    | LLM_API_KEY is required and LLM_EFFORT (low|medium|high) sets depth.
    | Gemini: https://generativelanguage.googleapis.com/v1beta/openai
    */

    'provider' => $provider,

    'api_key' => env('LLM_API_KEY', ''),

    'base_url' => env('LLM_BASE_URL', $defaultBase),

    'model' => env('LLM_MODEL', $defaultModel),

    'effort' => env('LLM_EFFORT', 'medium'),

    'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', $provider === 'anthropic' ? 60 : 20),

    /*
    | When live assistant is on (desk setting), inbound WhatsApp is answered
    | with product-aware replies that keep the conversation topic. Rules on
    | Intelligence still apply when the live assistant is off.
    */

    'live_history_limit' => (int) env('LLM_LIVE_HISTORY_LIMIT', 40),

    /*
    | Auto follow-ups read this many prior WhatsApp messages per lead, plus
    | knowledge RAG and the rolling memory_summary on the conversation.
    */
    'follow_up_history_limit' => (int) env('LLM_FOLLOW_UP_HISTORY_LIMIT', 40),

];
