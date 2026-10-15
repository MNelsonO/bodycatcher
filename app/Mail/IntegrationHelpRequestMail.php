<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IntegrationHelpRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $requesterEmail,
        public string $integrationTitle,
        public string $provider,
        public string $note,
        public ?string $stuckOn,
        public string $integrationsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Integration help: '.$this->integrationTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.integration-help-request',
            with: [
                'requesterName' => $this->requesterName,
                'requesterEmail' => $this->requesterEmail,
                'integrationTitle' => $this->integrationTitle,
                'provider' => $this->provider,
                'note' => $this->note,
                'stuckOn' => $this->stuckOn,
                'integrationsUrl' => $this->integrationsUrl,
            ],
        );
    }
}
