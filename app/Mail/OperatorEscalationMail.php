<?php

namespace App\Mail;

use App\Ai\OperatorEscalation;
use App\Leads\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OperatorEscalation $escalation,
        public Lead $lead,
        public string $leadUrl,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->lead->contact->full_name;

        return new Envelope(
            subject: 'Kemet desk needs you: '.$name,
        );
    }

    public function content(): Content
    {
        $this->lead->loadMissing(['contact', 'product', 'currentStage']);

        return new Content(
            markdown: 'mail.operator-escalation',
            with: [
                'contact' => $this->lead->contact->full_name,
                'business' => $this->lead->contact->business_name,
                'phone' => $this->lead->contact->primary_phone,
                'status' => $this->lead->outcome(),
                'product' => $this->lead->product->name,
                'stage' => $this->lead->currentStage->name,
                'reason' => $this->escalation->reason,
                'snippet' => $this->escalation->inbound_snippet,
                'suggested' => $this->escalation->suggested_reply,
                'leadUrl' => $this->leadUrl,
            ],
        );
    }
}
