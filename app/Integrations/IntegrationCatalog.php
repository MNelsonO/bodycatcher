<?php

namespace App\Integrations;

/**
 * Sales-facing copy for each connection: what it unlocks, how demos feel,
 * and the fields an operator fills on the Integrations page.
 */
class IntegrationCatalog
{
    /**
     * @return list<array{
     *   provider: string,
     *   title: string,
     *   tagline: string,
     *   pitch: string,
     *   demos: list<string>,
     *   outcomes: list<string>,
     *   setup_steps: list<string>,
     *   secret_fields: list<array{key: string, label: string, hint?: string}>,
     *   public_fields: list<array{key: string, label: string, hint?: string}>,
     *   supports_sync: bool,
     *   can_set_driver: bool
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'provider' => 'flaresend',
                'title' => 'FlareSend WhatsApp',
                'tagline' => 'Reliable WhatsApp delivery with anti-ban pacing built in.',
                'pitch' => 'Connect FlareSend so the desk can reply like a salesperson: share demos, answer product questions from conversation context, and keep threads warm without burning the number.',
                'demos' => [
                    'Prospect asks “can we see a demo?” — assistant shares the right session or booking next step.',
                    'Inbound “how much for Laundry OS?” — reply from solid lessons, not vague filler.',
                    'Sticky questions escalate so a human closes with context.',
                ],
                'outcomes' => [
                    'Send and receive WhatsApp from the desk',
                    'Live AI replies when history and lessons are solid',
                    'Webhook inbound into the pipeline as New leads',
                ],
                'setup_steps' => [
                    'Create or open your FlareSend workspace and copy the API key.',
                    'Set a webhook secret and point FlareSend at /webhooks/flaresend.',
                    'Paste keys here, set messaging driver to FlareSend, then send a test hello.',
                ],
                'secret_fields' => [
                    ['key' => 'api_key', 'label' => 'API key', 'hint' => 'From FlareSend dashboard'],
                    ['key' => 'webhook_secret', 'label' => 'Webhook secret', 'hint' => 'Must match X-FlareSend-Secret or Bearer token'],
                ],
                'public_fields' => [
                    ['key' => 'base_url', 'label' => 'API base URL', 'hint' => 'Usually https://api.flaresend.com'],
                ],
                'supports_sync' => false,
                'can_set_driver' => true,
            ],
            [
                'provider' => 'meta_whatsapp',
                'title' => 'Meta Cloud WhatsApp',
                'tagline' => 'Official Meta Cloud API when you prefer Graph WhatsApp.',
                'pitch' => 'Use Meta’s Cloud API for WhatsApp. Same desk flows — demos, contextual replies, follow-ups — on the Graph channel.',
                'demos' => [
                    'Template or free-form replies inside the 24-hour window.',
                    'Inbound webhooks open leads on the New stage.',
                ],
                'outcomes' => [
                    'Cloud API send/receive',
                    'Verify and app-secret protected webhooks',
                ],
                'setup_steps' => [
                    'In Meta Developer, open your WhatsApp product and copy the token + phone number id.',
                    'Set verify token and app secret for /webhooks/whatsapp.',
                    'Save keys here and switch messaging driver to Meta.',
                ],
                'secret_fields' => [
                    ['key' => 'token', 'label' => 'WhatsApp access token'],
                    ['key' => 'app_secret', 'label' => 'App secret'],
                    ['key' => 'verify_token', 'label' => 'Webhook verify token'],
                ],
                'public_fields' => [
                    ['key' => 'phone_number_id', 'label' => 'Phone number ID'],
                ],
                'supports_sync' => false,
                'can_set_driver' => true,
            ],
            [
                'provider' => 'meta_business',
                'title' => 'Meta Business insights',
                'tagline' => 'Page reach and engagement for sales conversations.',
                'pitch' => 'Pull page insight snapshots so reports and pitches stay grounded in real Meta traffic — useful when you show a prospect how their brand shows up.',
                'demos' => [
                    '“Here’s what your page did last 28 days” in Reports.',
                    'Spot engagement dips before a nurture push.',
                ],
                'outcomes' => [
                    '28-day Meta insight reports on the desk',
                    'Sync on demand for owner reviews',
                ],
                'setup_steps' => [
                    'Use a Meta token with pages_read_engagement (or reuse WhatsApp token if it has rights).',
                    'Paste the Page ID and token here, then Pull latest report.',
                ],
                'secret_fields' => [
                    ['key' => 'access_token', 'label' => 'Meta Business token'],
                ],
                'public_fields' => [
                    ['key' => 'page_id', 'label' => 'Page ID'],
                    ['key' => 'ad_account_id', 'label' => 'Ad account ID (optional)'],
                ],
                'supports_sync' => true,
                'can_set_driver' => false,
            ],
            [
                'provider' => 'google_search_console',
                'title' => 'Google Search Console',
                'tagline' => 'Search queries and clicks that feed demand gen talk tracks.',
                'pitch' => 'Show which queries bring people in. Great for demos where you connect SEO interest to the product pitch.',
                'demos' => [
                    'Top queries → “people searching this already care about X”.',
                    'Share a traffic snapshot in a proposal call.',
                ],
                'outcomes' => [
                    'Search analytics rows in Reports',
                    'On-demand sync for the connected site',
                ],
                'setup_steps' => [
                    'Confirm the site property in Search Console.',
                    'Paste site URL (https://…/ or sc-domain:…) and an OAuth access token with webmasters scope.',
                    'Save, then Pull latest report.',
                ],
                'secret_fields' => [
                    ['key' => 'access_token', 'label' => 'Search Console access token'],
                ],
                'public_fields' => [
                    ['key' => 'site_url', 'label' => 'Site URL', 'hint' => 'e.g. https://example.com/ or sc-domain:example.com'],
                ],
                'supports_sync' => true,
                'can_set_driver' => false,
            ],
            [
                'provider' => 'google_calendar',
                'title' => 'Google Calendar',
                'tagline' => 'Push demos, follow-ups, and supervisor pings onto a real calendar.',
                'pitch' => 'When a lead books a demo or the AI escalates, the desk can push a timed event so nothing falls through — salesperson rhythm off WhatsApp too.',
                'demos' => [
                    'Mark a date on a lead → event lands on Google Calendar.',
                    'Operator escalation creates a timed ping for the owner.',
                ],
                'outcomes' => [
                    'Desk calendar activities sync out',
                    'Escalation and follow-up reminders on Google',
                ],
                'setup_steps' => [
                    'Create or pick a Google Calendar and copy its ID.',
                    'Paste an OAuth access token with calendar scope.',
                    'Activate the connection, then save a desk calendar item to verify.',
                ],
                'secret_fields' => [
                    ['key' => 'access_token', 'label' => 'Google Calendar access token'],
                ],
                'public_fields' => [
                    ['key' => 'calendar_id', 'label' => 'Calendar ID'],
                ],
                'supports_sync' => true,
                'can_set_driver' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function for(string $provider): ?array
    {
        foreach (self::all() as $item) {
            if ($item['provider'] === $provider) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function providers(): array
    {
        return array_column(self::all(), 'provider');
    }
}
