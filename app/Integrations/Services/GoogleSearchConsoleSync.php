<?php

namespace App\Integrations\Services;

use App\Integrations\IntegrationAccount;
use App\Integrations\IntegrationReport;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleSearchConsoleSync
{
    public function sync(IntegrationAccount $account): IntegrationReport
    {
        $site = (string) ($account->settings['site_url'] ?? config('integrations.google_search_console.site_url'));
        $token = (string) config('integrations.google_search_console.access_token');
        $end = now()->subDay()->toDateString();
        $start = now()->subDays(28)->toDateString();

        if ($site === '' || $token === '') {
            return $this->storePlaceholder(
                $account,
                $start,
                $end,
                'Set GSC_SITE_URL and GSC_ACCESS_TOKEN to pull Search Console traffic.',
            );
        }

        try {
            $encoded = rawurlencode($site);
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post("https://www.googleapis.com/webmasters/v3/sites/{$encoded}/searchAnalytics/query", [
                    'startDate' => $start,
                    'endDate' => $end,
                    'dimensions' => ['query'],
                    'rowLimit' => 25,
                ]);

            if ($response->failed()) {
                return $this->storePlaceholder($account, $start, $end, 'GSC API error HTTP '.$response->status());
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->json() ?? [];
            $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

            $clicks = 0.0;
            $impressions = 0.0;
            $mapped = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clicks += (float) ($row['clicks'] ?? 0);
                $impressions += (float) ($row['impressions'] ?? 0);
                $keys = is_array($row['keys'] ?? null) ? $row['keys'] : [];
                $mapped[] = [
                    'query' => $keys[0] ?? '',
                    'clicks' => $row['clicks'] ?? 0,
                    'impressions' => $row['impressions'] ?? 0,
                    'ctr' => $row['ctr'] ?? 0,
                    'position' => $row['position'] ?? 0,
                ];
            }

            $report = IntegrationReport::query()->create([
                'integration_account_id' => $account->id,
                'report_type' => 'search',
                'period_start' => $start,
                'period_end' => $end,
                'metrics' => [
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
                    'queries' => count($mapped),
                ],
                'rows' => $mapped,
                'fetched_at' => now(),
            ]);

            $account->last_synced_at = now();
            $account->last_sync_error = null;
            $account->save();

            return $report;
        } catch (Throwable $exception) {
            return $this->storePlaceholder($account, $start, $end, $exception->getMessage());
        }
    }

    private function storePlaceholder(IntegrationAccount $account, string $start, string $end, string $error): IntegrationReport
    {
        $account->last_sync_error = $error;
        $account->last_synced_at = now();
        $account->save();

        return IntegrationReport::query()->create([
            'integration_account_id' => $account->id,
            'report_type' => 'search',
            'period_start' => $start,
            'period_end' => $end,
            'metrics' => [
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0,
                'queries' => 0,
                'status' => 'unavailable',
                'message' => $error,
            ],
            'rows' => [],
            'fetched_at' => now(),
        ]);
    }
}
