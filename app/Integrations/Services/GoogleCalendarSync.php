<?php

namespace App\Integrations\Services;

use App\Calendar\CalendarActivity;
use App\Integrations\IntegrationAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a desk calendar activity into Google Calendar when the integration
 * is activated and GOOGLE_CALENDAR_* credentials are present.
 */
class GoogleCalendarSync
{
    public function account(): ?IntegrationAccount
    {
        return IntegrationAccount::query()
            ->where('provider', 'google_calendar')
            ->orderBy('id')
            ->first();
    }

    public function activated(): bool
    {
        $account = $this->account();

        return $account !== null && $account->is_active;
    }

    public function configured(): bool
    {
        return filled(config('integrations.google_calendar.access_token'))
            && filled(config('integrations.google_calendar.calendar_id'));
    }

    /**
     * @return array{ok: bool, external_id: string|null, message: string}
     */
    public function push(CalendarActivity $activity): array
    {
        if (! $this->activated()) {
            return [
                'ok' => false,
                'external_id' => null,
                'message' => 'Google Calendar is not activated on Integrations.',
            ];
        }

        if (! $this->configured()) {
            return [
                'ok' => false,
                'external_id' => null,
                'message' => 'Set GOOGLE_CALENDAR_ACCESS_TOKEN and GOOGLE_CALENDAR_ID to push events.',
            ];
        }

        $calendarId = rawurlencode((string) config('integrations.google_calendar.calendar_id'));
        $token = (string) config('integrations.google_calendar.access_token');
        $date = $activity->activity_on?->toDateString() ?? now()->toDateString();

        $payload = [
            'summary' => $activity->title,
            'description' => $activity->notes ?? '',
            'start' => ['date' => $date],
            'end' => ['date' => $date],
        ];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", $payload);

            if (! $response->successful()) {
                $message = 'Google Calendar rejected the event (HTTP '.$response->status().').';
                $this->recordError($message);

                return ['ok' => false, 'external_id' => null, 'message' => $message];
            }

            $externalId = (string) ($response->json('id') ?? '');
            if ($externalId !== '') {
                $activity->external_calendar_id = $externalId;
                $activity->save();
            }

            $account = $this->account();
            if ($account !== null) {
                $account->last_synced_at = now();
                $account->last_sync_error = null;
                $account->save();
            }

            return [
                'ok' => true,
                'external_id' => $externalId !== '' ? $externalId : null,
                'message' => 'Pushed to Google Calendar.',
            ];
        } catch (Throwable $exception) {
            Log::warning('Google Calendar push failed.', ['error' => $exception->getMessage()]);
            $this->recordError($exception->getMessage());

            return [
                'ok' => false,
                'external_id' => null,
                'message' => 'Could not reach Google Calendar.',
            ];
        }
    }

    /**
     * @return array{activated: bool, configured: bool, label: string, last_synced_at: string|null, last_sync_error: string|null, calendar_id_set: bool}
     */
    public function status(): array
    {
        $account = $this->account();

        return [
            'activated' => $this->activated(),
            'configured' => $this->configured(),
            'label' => $account?->label ?? 'Google Calendar',
            'last_synced_at' => $account?->last_synced_at?->toIso8601String(),
            'last_sync_error' => $account?->last_sync_error,
            'calendar_id_set' => filled(config('integrations.google_calendar.calendar_id')),
        ];
    }

    private function recordError(string $message): void
    {
        $account = $this->account();

        if ($account === null) {
            return;
        }

        $account->last_sync_error = mb_substr($message, 0, 500);
        $account->save();
    }
}
