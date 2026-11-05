<?php

namespace App\Console\Commands;

use App\Conversations\Message;
use App\Leads\Interest\InterestClassifier;
use App\Leads\Lead;
use App\WhatsApp\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Backfills ad details from stored webhooks, then sorts every lead that has
 * not been set by hand into a Kemet system from its ad or what it wrote.
 */
class ClassifyLeadInterest extends Command
{
    protected $signature = 'leads:classify-interest';

    protected $description = 'Attach Click-to-WhatsApp ads to leads and classify leads by system.';

    public function handle(InterestClassifier $classifier): int
    {
        $ads = 0;

        foreach (WebhookEvent::query()->orderBy('id')->cursor() as $event) {
            $payload = $event->payload;
            $raw = $payload['raw'] ?? null;
            $data = is_array($raw) ? ($raw['data'] ?? $raw) : null;
            $message = is_array($data) && is_array($data['message'] ?? null) ? $data['message'] : null;
            $ad = $message !== null ? $classifier->adFromMessage($message) : null;

            if ($ad === null) {
                continue;
            }

            $ids = [];

            foreach ($payload['entry'][0]['changes'][0]['value']['messages'] ?? [] as $row) {
                if (is_array($row) && is_string($row['id'] ?? null)) {
                    $ids[] = $row['id'];
                }
            }

            if (is_array($data['key'] ?? null) && is_string($data['key']['id'] ?? null)) {
                $ids[] = $data['key']['id'];
            }

            $stored = $ids === [] ? null : Message::query()->with('conversation.lead')->whereIn('provider_message_id', $ids)->first();
            $lead = $stored?->conversation?->lead;

            if ($lead instanceof Lead) {
                $fresh = $lead->ad_first_seen_at === null;
                $classifier->recordAd($lead, $ad);

                if ($fresh) {
                    $lead->ad_first_seen_at = $event->created_at ?? now();
                    $lead->saveQuietly();
                    $ads++;
                }
            }
        }

        $classified = 0;

        foreach (Lead::query()->with('conversation')->where(fn ($q) => $q->whereNull('interest_source')->orWhere('interest_source', 'chat'))->cursor() as $lead) {
            if ($classifier->classifyFromChat($lead) !== null) {
                $classified++;
            }
        }

        $unknown = Lead::query()->whereNull('interest_system')->count();
        $this->info("Ads attached: {$ads}. Leads with a system from chat: {$classified}. Still unknown: {$unknown}.");

        return self::SUCCESS;
    }
}
