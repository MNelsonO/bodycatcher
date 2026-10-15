<?php

return [

    /*
    | OpenAI-compatible chat completions. All three must be set or the desk
    | reports the model as missing and does not invent a summary or a score.
    */

    'api_key' => env('LLM_API_KEY', ''),

    'base_url' => env('LLM_BASE_URL', ''),

    'model' => env('LLM_MODEL', ''),

    'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 20),

];
