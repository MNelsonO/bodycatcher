<?php

return [

    /*
    | Prefer credentials saved on /integrations (encrypted). Env values remain
    | the fallback. Secrets are never rendered back to the browser.
    |
    | driver: meta | flaresend | twilio (desk can override via DeskSetting)
    |
    */
    'driver' => env('MESSAGING_DRIVER', 'meta'),

    'token' => env('WHATSAPP_TOKEN', ''),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),

    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),

    /*
    | Self-hosted Evolution API (WhatsApp Web / Baileys) on this server. It
    | reports both sides of every chat, including messages the owner sends
    | from their phone, and can sync recent history when first linked.
    */
    /*
    | Safe sending for WhatsApp Web style connections (evolution, flaresend).
    | WhatsApp bans numbers that behave like bots: bursts, cold messages to
    | people who never wrote, the same text to many chats. These limits keep
    | automatic sends at a human pace and stop accidental mass sending.
    */
    'safety' => [
        'enabled' => env('WHATSAPP_SAFETY_ENABLED', true),
        'auto_min_gap_seconds' => (int) env('WHATSAPP_AUTO_MIN_GAP_SECONDS', 20),
        'auto_per_chat_gap_seconds' => (int) env('WHATSAPP_AUTO_PER_CHAT_GAP_SECONDS', 60),
        'auto_per_hour' => (int) env('WHATSAPP_AUTO_PER_HOUR', 20),
        'auto_per_day' => (int) env('WHATSAPP_AUTO_PER_DAY', 100),
        'auto_same_text_chats_per_hour' => (int) env('WHATSAPP_AUTO_SAME_TEXT_CHATS', 4),
        'manual_new_chats_per_day' => (int) env('WHATSAPP_MANUAL_NEW_CHATS_PER_DAY', 25),
        'manual_burst_per_10_minutes' => (int) env('WHATSAPP_MANUAL_BURST_PER_10_MIN', 40),
        // Messaging a whole label: at most this many per day, this far apart.
        'campaign_per_day' => (int) env('WHATSAPP_CAMPAIGN_PER_DAY', 30),
        'campaign_gap_seconds' => (int) env('WHATSAPP_CAMPAIGN_GAP_SECONDS', 150),
        'campaign_quiet_hours' => (int) env('WHATSAPP_CAMPAIGN_QUIET_HOURS', 12),
    ],

    /*
    | What a WhatsApp Business label does in the desk. Labels are matched by
    | name (case-insensitive, partial). Stage moves only happen when the
    | pipeline permits that move; otherwise the label is just recorded.
    */
    'label_rules' => [
        ['match' => ['follow up', 'follow-up', 'followup', 'call back'], 'action' => 'follow_up'],
        ['match' => ['order complete', 'completed', 'paid', 'won', 'closed deal'], 'action' => 'won'],
        ['match' => ['lost', 'not interested', 'dead', 'got elsewhere'], 'action' => 'lost'],
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_BASE_URL', 'http://127.0.0.1:8080'),
        'api_key' => env('EVOLUTION_API_KEY', ''),
        'instance' => env('EVOLUTION_INSTANCE', 'kemet'),
        'webhook_token' => env('EVOLUTION_WEBHOOK_TOKEN', ''),
    ],

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
    'inbound_stage_name' => 'New',
    'inbound_source' => 'whatsapp',

    'service_window_hours' => 24,

    /*
    | Meta Cloud API only: approved template used when the 24-hour window
    | is closed. The desk message body is sent as body parameter {{1}}.
    | FlareSend ignores this — free-form text is always allowed there.
    |
    */
    'template' => [
        'name' => env('WHATSAPP_TEMPLATE_NAME', ''),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
    ],

    /*
    | Opt-out is an exact match on the whole inbound text, after trimming
    | and lowercasing. No other wording is treated as an opt-out.
    |
    */
    'opt_out_keywords' => ['stop', 'unsubscribe'],

];
