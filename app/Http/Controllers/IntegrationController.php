<?php

namespace App\Http\Controllers;

use App\Integrations\IntegrationAccount;
use App\Integrations\IntegrationCatalog;
use App\Integrations\IntegrationReport;
use App\Integrations\Services\GoogleSearchConsoleSync;
use App\Integrations\Services\IntegrationConfigResolver;
use App\Integrations\Services\MetaBusinessInsightsSync;
use App\Integrations\Services\RequestIntegrationHelp;
use App\Models\User;
use App\WhatsApp\DeskSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private IntegrationConfigResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureDefaults();
        $this->resolver->applyToConfig();

        $user = $request->user();
        $accounts = IntegrationAccount::query()->orderBy('provider')->get();
        $catalogByProvider = collect(IntegrationCatalog::all())->keyBy('provider');

        $latestReports = IntegrationReport::query()
            ->with('account:id,provider,label')
            ->orderByDesc('fetched_at')
            ->limit(20)
            ->get();

        $driver = (string) config('whatsapp.driver', 'meta');

        return Inertia::render('integrations/index', [
            'can_manage' => $user instanceof User && $user->isOwner(),
            'messaging_driver' => $driver,
            'webhook_paths' => [
                'flaresend' => url('/webhooks/flaresend'),
                'meta' => url('/webhooks/whatsapp'),
                'twilio' => url('/webhooks/twilio'),
            ],
            'catalog' => IntegrationCatalog::all(),
            'accounts' => $accounts->map(function (IntegrationAccount $account) use ($catalogByProvider): array {
                $meta = $catalogByProvider->get($account->provider);

                return [
                    'id' => $account->id,
                    'provider' => $account->provider,
                    'label' => $account->label,
                    'is_active' => $account->is_active,
                    'settings' => $account->settings ?? [],
                    'credential_flags' => $this->resolver->credentialFlags($account),
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                    'last_sync_error' => $account->last_sync_error,
                    'configured' => $this->configured($account),
                    'supports_sync' => (bool) ($meta['supports_sync'] ?? false),
                    'can_set_driver' => (bool) ($meta['can_set_driver'] ?? false),
                ];
            }),
            'reports' => $latestReports->map(fn (IntegrationReport $report): array => [
                'id' => $report->id,
                'provider' => $report->account?->provider,
                'label' => $report->account?->label,
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'metrics' => $report->metrics,
                'rows' => $report->rows ?? [],
                'fetched_at' => $report->fetched_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request, IntegrationAccount $account): RedirectResponse
    {
        abort_unless($request->user()?->isOwner() === true, 403);

        $catalog = IntegrationCatalog::for($account->provider);
        abort_unless($catalog !== null, 404);

        $secretKeys = array_column($catalog['secret_fields'], 'key');
        $publicKeys = array_column($catalog['public_fields'], 'key');

        $rules = [
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'messaging_driver' => ['nullable', 'in:meta,flaresend,twilio'],
        ];

        foreach ($secretKeys as $key) {
            $rules['credentials.'.$key] = ['nullable', 'string', 'max:4000'];
        }

        foreach ($publicKeys as $key) {
            $rules[$key] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        $settings = $account->settings ?? [];
        foreach ($publicKeys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                $settings[$key] = is_string($value) && trim($value) !== '' ? trim($value) : ($settings[$key] ?? null);
            }
        }

        $incomingSecrets = is_array($request->input('credentials')) ? $request->input('credentials') : [];
        $account->credentials = $this->resolver->mergeSecrets(
            $account->credentials ?? [],
            $incomingSecrets,
            $secretKeys,
        );

        $account->label = $data['label'];
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $account->is_active = (bool) $data['is_active'];
        }
        $account->settings = $settings;
        $account->save();

        if (($catalog['can_set_driver'] ?? false) && filled($data['messaging_driver'] ?? null)) {
            $desk = DeskSetting::current();
            $desk->messaging_driver = $data['messaging_driver'];
            $desk->save();
        }

        $this->resolver->overlayAccount($account->fresh());
        $this->resolver->overlayMessagingDriver();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $account->provider === 'google_calendar' && $account->is_active
                ? 'Google Calendar activated. Desk calendar items push when credentials are set.'
                : ($catalog['title'] ?? 'Integration').' saved on the desk — no .env edit needed.',
        ]);

        return redirect()->route('integrations.index');
    }

    public function sync(Request $request, IntegrationAccount $account, MetaBusinessInsightsSync $meta, GoogleSearchConsoleSync $gsc): RedirectResponse
    {
        abort_unless($request->user()?->isOwner() === true, 403);

        $this->resolver->applyToConfig();

        if ($account->provider === 'meta_business') {
            $meta->sync($account);
        } elseif ($account->provider === 'google_search_console') {
            $gsc->sync($account);
        } elseif ($account->provider === 'google_calendar') {
            if (! $this->configured($account)) {
                $account->last_sync_error = 'Add calendar ID and access token on this page first.';
                $account->save();
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => 'Google Calendar credentials are missing.',
                ]);

                return redirect()->route('integrations.index');
            }

            $account->last_synced_at = now();
            $account->last_sync_error = null;
            $account->save();
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Google Calendar looks ready. New desk calendar items push when you save them.',
            ]);

            return redirect()->route('integrations.index');
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This connection does not pull reports — configure keys and use WhatsApp / Calendar flows.',
            ]);

            return redirect()->route('integrations.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sync finished for '.$account->label.'.']);

        return redirect()->route('integrations.index');
    }

    public function requestHelp(Request $request, IntegrationAccount $account, RequestIntegrationHelp $help): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 403);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'stuck_on' => ['nullable', 'string', 'max:240'],
        ]);

        $result = $help->handle(
            $request->user(),
            $account,
            $data['note'],
            $data['stuck_on'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return redirect()->route('integrations.index');
    }

    private function ensureDefaults(): void
    {
        $defaults = [
            'flaresend' => [
                'label' => 'FlareSend WhatsApp',
                'is_active' => true,
                'settings' => [
                    'base_url' => config('whatsapp.flaresend.base_url') ?: 'https://api.flaresend.com',
                ],
            ],
            'meta_whatsapp' => [
                'label' => 'Meta Cloud WhatsApp',
                'is_active' => true,
                'settings' => [
                    'phone_number_id' => config('whatsapp.phone_number_id') ?: null,
                ],
            ],
            'meta_business' => [
                'label' => 'Meta Business',
                'is_active' => true,
                'settings' => [
                    'page_id' => config('integrations.meta.page_id') ?: null,
                    'ad_account_id' => config('integrations.meta.ad_account_id') ?: null,
                ],
            ],
            'google_search_console' => [
                'label' => 'Google Search Console',
                'is_active' => true,
                'settings' => [
                    'site_url' => config('integrations.google_search_console.site_url') ?: null,
                ],
            ],
            'google_calendar' => [
                'label' => 'Google Calendar',
                'is_active' => false,
                'settings' => [
                    'calendar_id' => config('integrations.google_calendar.calendar_id') ?: null,
                ],
            ],
        ];

        foreach ($defaults as $provider => $attrs) {
            IntegrationAccount::query()->firstOrCreate(
                ['provider' => $provider, 'label' => $attrs['label']],
                [
                    'is_active' => $attrs['is_active'],
                    'settings' => $attrs['settings'],
                ],
            );
        }
    }

    private function configured(IntegrationAccount $account): bool
    {
        $credentials = $account->credentials ?? [];
        $settings = $account->settings ?? [];

        return match ($account->provider) {
            'flaresend' => filled($credentials['api_key'] ?? null)
                || filled(config('whatsapp.flaresend.api_key')),
            'meta_whatsapp' => (filled($credentials['token'] ?? null) || filled(config('whatsapp.token')))
                && (filled($settings['phone_number_id'] ?? null) || filled(config('whatsapp.phone_number_id'))),
            'meta_business' => (filled($credentials['access_token'] ?? null) || filled(config('integrations.meta.access_token')))
                && (filled($settings['page_id'] ?? null) || filled(config('integrations.meta.page_id'))),
            'google_search_console' => (filled($credentials['access_token'] ?? null) || filled(config('integrations.google_search_console.access_token')))
                && (filled($settings['site_url'] ?? null) || filled(config('integrations.google_search_console.site_url'))),
            'google_calendar' => (filled($credentials['access_token'] ?? null) || filled(config('integrations.google_calendar.access_token')))
                && (filled($settings['calendar_id'] ?? null) || filled(config('integrations.google_calendar.calendar_id'))),
            default => false,
        };
    }
}
