<?php

namespace App\Integrations\Services;

use App\Integrations\IntegrationAccount;
use App\Integrations\IntegrationReport;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaBusinessInsightsSync
{
    public function sync(IntegrationAccount $account): IntegrationReport
    {
        $token = (string) config('integrations.meta.access_token');
        $pageId = (string) ($account->settings['page_id'] ?? config('integrations.meta.page_id'));
        $end = now()->toDateString();
        $start = now()->subDays(28)->toDateString();

        if ($token === '' || $pageId === '') {
            return $this->storePlaceholder($account, $start, $end, 'Meta Business token or page id missing. Set META_BUSINESS_TOKEN / META_PAGE_ID.');
        }

        try {
            $url = rtrim((string) config('integrations.meta.graph_url'), '/')
                .'/'
                .config('integrations.meta.graph_version')
                .'/'
                .$pageId
                .'/insights';

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($url, [
                    'metric' => 'page_impressions,page_engaged_users,page_post_engagements,page_fans',
                    'period' => 'day',
                    'since' => $start,
                    'until' => $end,
                ]);

            if ($response->failed()) {
                return $this->storePlaceholder($account, $start, $end, 'Meta API error HTTP '.$response->status());
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->json() ?? [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            $metrics = [
                'impressions' => 0,
                'engaged_users' => 0,
                'post_engagements' => 0,
                'fans' => 0,
            ];
            $rows = [];

            foreach ($data as $metric) {
                if (! is_array($metric)) {
                    continue;
                }
                $name = (string) ($metric['name'] ?? '');
                $values = is_array($metric['values'] ?? null) ? $metric['values'] : [];
                $latest = is_array(end($values)) ? end($values) : null;
                $value = is_array($latest) ? (float) ($latest['value'] ?? 0) : 0.0;

                match ($name) {
                    'page_impressions' => $metrics['impressions'] = $value,
                    'page_engaged_users' => $metrics['engaged_users'] = $value,
                    'page_post_engagements' => $metrics['post_engagements'] = $value,
                    'page_fans' => $metrics['fans'] = $value,
                    default => null,
                };

                foreach ($values as $point) {
                    if (! is_array($point)) {
                        continue;
                    }
                    $rows[] = [
                        'metric' => $name,
                        'date' => $point['end_time'] ?? null,
                        'value' => $point['value'] ?? 0,
                    ];
                }
            }

            $report = IntegrationReport::query()->create([
                'integration_account_id' => $account->id,
                'report_type' => 'engagement',
                'period_start' => $start,
                'period_end' => $end,
                'metrics' => $metrics,
                'rows' => array_slice($rows, -60),
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
            'report_type' => 'engagement',
            'period_start' => $start,
            'period_end' => $end,
            'metrics' => [
                'impressions' => 0,
                'engaged_users' => 0,
                'post_engagements' => 0,
                'fans' => 0,
                'status' => 'unavailable',
                'message' => $error,
            ],
            'rows' => [],
            'fetched_at' => now(),
        ]);
    }
}
