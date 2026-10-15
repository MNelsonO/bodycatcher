<?php

namespace App\WhatsApp\Services;

use App\Contacts\ConsentStatus;
use App\Conversations\MessageOrigin;
use App\Leads\Lead;
use App\WhatsApp\DeskSetting;

class MessagingGuard
{
    public function __construct(private ServiceWindow $window) {}

    /**
     * A non-null string is a refusal. The caller must persist it and must not queue a send.
     */
    public function refusal(Lead $lead, MessageOrigin $origin): ?string
    {
        $lead->loadMissing('contact');
        $contact = $lead->contact;

        if ($contact->consent_status === ConsentStatus::OptedOut) {
            return 'This contact opted out. No WhatsApp message can be sent.';
        }

        if (! $this->window->isOpen($contact)) {
            return 'The 24-hour customer-service window is closed. Free-form text needs a template, and this desk does not send templates yet.';
        }

        if ($origin === MessageOrigin::Automatic) {
            $pause = $this->pauseReason($lead);

            if ($pause !== null) {
                return $pause;
            }
        }

        if (! $this->driverConfigured()) {
            return 'WhatsApp is not configured for the active messaging driver, so nothing was sent.';
        }

        return null;
    }

    public function driverConfigured(): bool
    {
        return match ((string) config('whatsapp.driver', 'meta')) {
            'flaresend' => filled(config('whatsapp.flaresend.api_key')),
            'twilio' => filled(config('whatsapp.twilio.account_sid'))
                && filled(config('whatsapp.twilio.auth_token'))
                && filled(config('whatsapp.twilio.from')),
            default => filled(config('whatsapp.token')) && filled(config('whatsapp.phone_number_id')),
        };
    }

    /**
     * Pause, takeover, and personal attention hold an automatic send. They do not refuse it.
     */
    public function pauseReason(Lead $lead): ?string
    {
        if (DeskSetting::current()->automatic_sends_paused) {
            return 'Automatic sends are paused for the whole desk.';
        }

        if ($lead->automation_paused) {
            return 'Automatic sends are paused on this lead.';
        }

        if ($lead->taken_over) {
            return 'This lead is taken over. Automatic sends are blocked.';
        }

        if ($lead->needs_personal_attention) {
            return 'This lead needs personal attention. Automatic sends are blocked.';
        }

        return null;
    }
}
