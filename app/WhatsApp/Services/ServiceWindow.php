<?php

namespace App\WhatsApp\Services;

use App\Contacts\Contact;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ServiceWindow
{
    public function lastInboundAt(Contact $contact): ?CarbonInterface
    {
        $sentAt = Message::query()
            ->where('direction', MessageDirection::Inbound->value)
            ->where('channel', MessageChannel::WhatsApp->value)
            ->whereHas('conversation', function ($query) use ($contact): void {
                $query->where('contact_id', $contact->id);
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->value('sent_at');

        if ($sentAt instanceof CarbonInterface) {
            return $sentAt;
        }

        if (is_string($sentAt) || is_numeric($sentAt)) {
            return Carbon::parse((string) $sentAt);
        }

        return null;
    }

    public function isOpen(Contact $contact, ?CarbonInterface $now = null): bool
    {
        $last = $this->lastInboundAt($contact);

        if ($last === null) {
            return false;
        }

        $now ??= now();
        $hours = (int) config('whatsapp.service_window_hours');

        return $last->greaterThanOrEqualTo($now->copy()->subHours($hours));
    }
}
