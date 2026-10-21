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

        if ($this->enforcesServiceWindow() && ! $this->window->isOpen($contact)) {
            if ($this->canSendMetaTemplateOutsideWindow()) {
                // SendWhatsAppMessage will deliver as an approved Cloud API template.
                return null;
            }

            $driver = (string) config('whatsapp.driver', 'meta');

            if ($driver === 'meta') {
                return 'The 24-hour customer-service window is closed. Set WHATSAPP_TEMPLATE_NAME to an approved Meta template (with a body variable for your message), or wait until the contact messages you again.';
            }

            return 'The 24-hour customer-service window is closed. Free-form WhatsApp text needs a recent inbound message.';
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

    /**
     * Meta Cloud API and Twilio enforce the customer-care window.
     * FlareSend (session/Baileys) does not — free-form text is always allowed.
     */
    public function enforcesServiceWindow(): bool
    {
        return match ((string) config('whatsapp.driver', 'meta')) {
            'flaresend' => false,
            default => true,
        };
    }

    public function canSendMetaTemplateOutsideWindow(): bool
    {
        return (string) config('whatsapp.driver', 'meta') === 'meta'
            && filled(config('whatsapp.template.name'));
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

        if ($lead->conversations_stopped) {
            return 'Future conversations are stopped on this lead.';
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
