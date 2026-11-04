<?php

namespace App\WhatsApp;

use App\Conversations\MessageStatus;
use Inertia\Inertia;

class SendOutcomeToast
{
    public static function flash(OutboundSend $outcome): void
    {
        $message = $outcome->message;

        $toast = match (true) {
            $outcome->duplicate => ['type' => 'info', 'message' => 'That send was already recorded. WhatsApp was not called again.'],
            $message->status === MessageStatus::Sent => ['type' => 'success', 'message' => 'WhatsApp accepted the message. Delivery is not confirmed yet.'],
            $message->status === MessageStatus::Unknown => ['type' => 'error', 'message' => 'The request timed out after it might have reached WhatsApp. It was not sent again.'],
            $message->status === MessageStatus::Failed => ['type' => 'error', 'message' => 'WhatsApp rejected the message. It was not marked delivered.'],
            $message->status === MessageStatus::Queued => ['type' => 'warning', 'message' => 'WhatsApp did not accept the message. The same row can be retried.'],
            default => null,
        };

        if ($toast !== null) {
            Inertia::flash('toast', $toast);
        }
    }
}
