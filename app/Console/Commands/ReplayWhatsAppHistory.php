<?php

namespace App\Console\Commands;

use App\WhatsApp\Services\InboundWhatsAppProcessor;
use App\WhatsApp\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Runs the history batches the gateway already delivered through the
 * importer again. Safe to repeat: known messages are skipped.
 */
class ReplayWhatsAppHistory extends Command
{
    protected $signature = 'whatsapp:replay-history';

    protected $description = 'Re-apply stored gateway history to chats (skips messages already present).';

    public function handle(InboundWhatsAppProcessor $processor): int
    {
        $events = WebhookEvent::query()
            ->whereNotNull('processed_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (WebhookEvent $event) => ($event->payload['provider'] ?? null) === 'evolution');

        $count = 0;

        foreach ($events as $event) {
            $payload = $event->payload;
            $messages = $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];

            if (! is_array($messages) || $messages === []) {
                continue;
            }

            // Old batches stored unresolved privacy ids without the "+".
            foreach ($messages as $i => $message) {
                if (is_array($message) && ($message['kemet_history'] ?? false) === true) {
                    $from = (string) ($message['from'] ?? '');

                    if (strlen($from) >= 14 && ! str_starts_with($from, '+') && ! str_starts_with($from, '254')) {
                        $messages[$i]['from'] = '+'.$from;
                    }
                }
            }

            $payload['entry'][0]['changes'][0]['value']['messages'] = array_values(array_filter(
                $messages,
                fn ($m) => is_array($m) && ($m['kemet_history'] ?? false) === true,
            ));

            if ($payload['entry'][0]['changes'][0]['value']['messages'] === []) {
                continue;
            }

            $processor->replay($payload);
            $count += count($payload['entry'][0]['changes'][0]['value']['messages']);
        }

        $this->info("Re-applied {$count} history messages.");

        return self::SUCCESS;
    }
}
