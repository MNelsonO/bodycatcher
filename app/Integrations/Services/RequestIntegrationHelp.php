<?php

namespace App\Integrations\Services;

use App\Calendar\CalendarActivity;
use App\Integrations\IntegrationAccount;
use App\Integrations\IntegrationCatalog;
use App\Mail\IntegrationHelpRequestMail;
use App\Models\User;
use App\Security\Role;
use App\Settings\DisplayClock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Forwards an integration setup question to the technical supervisor (owner)
 * with email + desk calendar ping.
 */
class RequestIntegrationHelp
{
    public function __construct(
        private DisplayClock $clock,
        private GoogleCalendarSync $google,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function handle(
        User $requester,
        IntegrationAccount $account,
        string $note,
        ?string $stuckOn = null,
    ): array {
        $catalog = IntegrationCatalog::for($account->provider);
        $title = $catalog['title'] ?? $account->label;

        $owner = User::query()->where('role', Role::Owner->value)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($owner === null) {
            return [
                'ok' => false,
                'message' => 'No technical supervisor is available on this desk yet.',
            ];
        }

        $note = mb_substr(trim($note), 0, 2000);
        $stuckOn = $stuckOn ? mb_substr(trim($stuckOn), 0, 240) : null;
        $when = now()->addHour();

        $activity = CalendarActivity::query()->create([
            'user_id' => $owner->id,
            'activity_on' => $this->clock->today(),
            'title' => 'Integration help: '.$title,
            'notes' => trim(implode("\n", array_filter([
                'From: '.$requester->name.' <'.$requester->email.'>',
                $stuckOn ? 'Stuck on: '.$stuckOn : null,
                $note !== '' ? $note : 'Please guide setup for '.$title.'.',
                'Open Integrations to walk them through credentials and a live demo reply.',
            ]))),
            'activity_type' => 'integration_help',
            'color' => 'amber',
        ]);

        try {
            $this->google->pushTimed($activity, $when, $when->copy()->addMinutes(30));
        } catch (Throwable $e) {
            Log::warning('Integration help calendar push failed', ['error' => $e->getMessage()]);
        }

        try {
            if (filled($owner->email)) {
                Mail::to($owner->email)->send(new IntegrationHelpRequestMail(
                    requesterName: $requester->name,
                    requesterEmail: (string) $requester->email,
                    integrationTitle: $title,
                    provider: $account->provider,
                    note: $note !== '' ? $note : 'Please guide me through setup.',
                    stuckOn: $stuckOn,
                    integrationsUrl: url('/integrations'),
                ));
            }
        } catch (Throwable $e) {
            Log::warning('Integration help mail failed', ['error' => $e->getMessage()]);
        }

        return [
            'ok' => true,
            'message' => 'You are being forwarded to a technical supervisor. They will help guide you through '.$title.'.',
        ];
    }
}
