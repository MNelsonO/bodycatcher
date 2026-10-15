<?php

return [

    /*
    | Credentials stay in the environment. The desk never copies them into
    | a database column and never renders the values.
    |
    | driver: meta | flaresend | twilio
    |
    */
    'driver' => env('MESSAGING_DRIVER', 'meta'),

    'token' => env('WHATSAPP_TOKEN', ''),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),

    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),

    'flaresend' => [
        'api_key' => env('FLARESEND_API_KEY', ''),
        'webhook_secret' => env('FLARESEND_WEBHOOK_SECRET', ''),
        'base_url' => env('FLARESEND_BASE_URL', 'https://api.flaresend.com'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'from' => env('TWILIO_WHATSAPP_FROM', ''),
    ],

    /*
    | Inbound leads open on the default pipeline, preferring a stage with
    | this name when it is still active. Otherwise the first active stage.
    |
    */
    'inbound_stage_name' => 'New Lead',
    'inbound_source' => 'whatsapp',

    'service_window_hours' => 24,

    /*
    | Opt-out is an exact match on the whole inbound text, after trimming
    | and lowercasing. No other wording is treated as an opt-out.
    |
    */
    'opt_out_keywords' => ['stop', 'unsubscribe'],

];
