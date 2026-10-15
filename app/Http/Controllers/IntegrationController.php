<?php

namespace App\Http\Controllers;

use App\Integrations\IntegrationAccount;
use App\Integrations\IntegrationReport;
use App\Integrations\Services\GoogleSearchConsoleSync;
use App\Integrations\Services\MetaBusinessInsightsSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $this->ensureDefaults();

        $accounts = IntegrationAccount::query()->orderBy('provider')->get();

        $latestReports = IntegrationReport::query()
            ->with('account:id,provider,label')
            ->orderByDesc('fetched_at')
            ->limit(20)
            ->get();

        return Inertia::render('integrations/index', [
            'accounts' => $accounts->map(fn (IntegrationAccount $account): array => [
                'id' => $account->id,
                'provider' => $account->provider,
                'label' => $account->label,
                'is_active' => $account->is_active,
                'settings' => $account->settings ?? [],
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                'last_sync_error' => $account->last_sync_error,
                'configured' => $this->configured($account),
            ]),
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
            'env_hints' => [
                'META_BUSINESS_TOKEN' => filled(config('integrations.meta.access_token')),
                'META_PAGE_ID' => filled(config('integrations.meta.page_id')),
                'GSC_SITE_URL' => filled(config('integrations.google_search_console.site_url')),
                'GSC_ACCESS_TOKEN' => filled(config('integrations.google_search_console.access_token')),
            ],
        ]);
    }

    public function sync(Request $request, IntegrationAccount $account, MetaBusinessInsightsSync $meta, GoogleSearchConsoleSync $gsc): RedirectResponse
    {
        abort_unless($request->user()?->isOwner() === true, 403);

        if ($account->provider === 'meta_business') {
            $meta->sync($account);
        } elseif ($account->provider === 'google_search_console') {
            $gsc->sync($account);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sync finished for '.$account->label.'.']);

        return redirect()->route('integrations.index');
    }

    public function update(Request $request, IntegrationAccount $account): RedirectResponse
    {
        abort_unless($request->user()?->isOwner() === true, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'page_id' => ['nullable', 'string', 'max:80'],
            'site_url' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = $account->settings ?? [];

        if ($account->provider === 'meta_business') {
            $settings['page_id'] = $data['page_id'] ?? ($settings['page_id'] ?? null);
        }

        if ($account->provider === 'google_search_console') {
            $settings['site_url'] = $data['site_url'] ?? ($settings['site_url'] ?? null);
        }

        $account->label = $data['label'];
        $account->is_active = $data['is_active'];
        $account->settings = $settings;
        $account->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integration updated.']);

        return redirect()->route('integrations.index');
    }

    private function ensureDefaults(): void
    {
        IntegrationAccount::query()->firstOrCreate(
            ['provider' => 'meta_business', 'label' => 'Meta Business'],
            [
                'is_active' => true,
                'settings' => [
                    'page_id' => config('integrations.meta.page_id') ?: null,
                ],
            ],
        );

        IntegrationAccount::query()->firstOrCreate(
            ['provider' => 'google_search_console', 'label' => 'Google Search Console'],
            [
                'is_active' => true,
                'settings' => [
                    'site_url' => config('integrations.google_search_console.site_url') ?: null,
                ],
            ],
        );
    }

    private function configured(IntegrationAccount $account): bool
    {
        return match ($account->provider) {
            'meta_business' => filled(config('integrations.meta.access_token'))
                && filled($account->settings['page_id'] ?? config('integrations.meta.page_id')),
            'google_search_console' => filled(config('integrations.google_search_console.access_token'))
                && filled($account->settings['site_url'] ?? config('integrations.google_search_console.site_url')),
            default => false,
        };
    }
}
