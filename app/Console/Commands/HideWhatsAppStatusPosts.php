<?php

namespace App\Console\Commands;

use App\Conversations\Message;
use App\WhatsApp\Services\InboundPayloadNormalizer;
use App\WhatsApp\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds stored messages that were really Status posts or invisible system
 * messages (judged from the raw webhook body) and blanks them, which hides
 * them everywhere in the desk. Use --dry-run to only count.
 */
class HideWhatsAppStatusPosts extends Command
{
    protected $signature = 'whatsapp:hide-status-posts {--dry-run}';

    protected $description = 'Hide stored messages that were WhatsApp Status posts or system messages.';

    public function handle(InboundPayloadNormalizer $normalizer): int
    {
        $ids = [];

        foreach (WebhookEvent::query()->orderBy('id')->cursor() as $event) {
            $raw = $event->payload['raw'] ?? null;
            $data = is_array($raw) ? ($raw['data'] ?? $raw) : null;
            $message = is_array($data) && is_array($data['message'] ?? null) ? $data['message'] : null;

            if ($message === null || ! ($normalizer->isStatusMessage($message) || $normalizer->isSystemMessage($message))) {
                continue;
            }

            foreach ($event->payload['entry'][0]['changes'][0]['value']['messages'] ?? [] as $row) {
                if (is_array($row) && is_string($row['id'] ?? null)) {
                    $ids[] = $row['id'];
                }
            }

            if (is_string($data['id'] ?? null)) {
                $ids[] = $data['id'];
            }
        }

        $query = Message::query()
            ->whereIn('provider_message_id', array_values(array_unique($ids)))
            ->where('direction', 'inbound')
            ->where('body', '!=', '');

        $count = (clone $query)->count();
        $chats = (clone $query)->distinct()->count('conversation_id');

        if ($this->option('dry-run')) {
            $this->info("Would hide {$count} messages in {$chats} chats.");

            return self::SUCCESS;
        }

        $hidden = DB::transaction(fn () => $query->update(['body' => '']));
        $this->info("Hid {$hidden} messages in {$chats} chats.");

        return self::SUCCESS;
    }
}
