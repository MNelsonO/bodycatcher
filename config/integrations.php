<?php

return [

    /*
    | Env values are the fallback. Saved keys on /integrations overlay these
    | at boot via IntegrationConfigResolver (encrypted in the database).
    |
    */

    'meta' => [
        'access_token' => env('META_BUSINESS_TOKEN', env('WHATSAPP_TOKEN', '')),
        'page_id' => env('META_PAGE_ID', ''),
        'ad_account_id' => env('META_AD_ACCOUNT_ID', ''),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
        'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
    ],

    'google_search_console' => [
        'site_url' => env('GSC_SITE_URL', ''),
        // Path to a Google service-account JSON file, or leave blank.
        'credentials_json' => env('GSC_CREDENTIALS_JSON', ''),
        'access_token' => env('GSC_ACCESS_TOKEN', ''),
    ],

    'google_calendar' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID', ''),
        'access_token' => env('GOOGLE_CALENDAR_ACCESS_TOKEN', ''),
    ],

];
