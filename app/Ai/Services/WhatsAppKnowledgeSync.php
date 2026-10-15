<?php

namespace App\Ai\Services;

use App\Ai\KnowledgeArticle;
use App\Ai\WhatsAppKnowledgeSyncRun as SyncRun;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Models\User;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls WhatsApp Business profile copy from Meta (when configured) and
 * mines frequent inbound customer questions from desk conversations into
 * knowledge articles the live assistant can reuse.
 */
class WhatsAppKnowledgeSync
{
    public function run(?User $actor = null): SyncRun
    {
        $run = SyncRun::query()->create([
            'status' => 'running',
            'requested_by_user_id' => $actor?->id,
        ]);

        try {
            $created = 0;
            $scanned = 0;

            $created += $this->pullMetaBusinessProfile();
            [$mined, $scanned] = $this->mineConversationFaqs();
            $created += $mined;

            $run->status = 'completed';
            $run->articles_created = $created;
            $run->messages_scanned = $scanned;
            $run->summary = "Created {$created} knowledge notes from WhatsApp business data and {$scanned} scanned messages.";
            $run->completed_at = now();
            $run->save();
        } catch (Throwable $exception) {
            Log::warning('WhatsApp knowledge sync failed.', ['error' => $exception->getMessage()]);
            $run->status = 'failed';
            $run->error = $exception->getMessage();
            $run->completed_at = now();
            $run->save();
        }

        return $run;
    }

    private function pullMetaBusinessProfile(): int
    {
        $token = (string) config('whatsapp.token');
        $phoneNumberId = (string) config('whatsapp.phone_number_id');

        if ($token === '' || $phoneNumberId === '') {
            return 0;
        }

        $url = rtrim((string) config('whatsapp.graph_url'), '/')
            .'/'
            .config('whatsapp.graph_version')
            .'/'
            .$phoneNumberId;

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get($url, [
                'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status,platform_type',
            ]);

        if ($response->failed()) {
            return 0;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $name = is_string($data['verified_name'] ?? null) ? $data['verified_name'] : 'WhatsApp Business';
        $phone = is_string($data['display_phone_number'] ?? null) ? $data['display_phone_number'] : '';
        $quality = is_string($data['quality_rating'] ?? null) ? $data['quality_rating'] : 'unknown';
        $externalId = 'meta-waba-profile-'.$phoneNumberId;

        $body = implode("\n", array_filter([
            "WhatsApp Business profile synced from Meta.",
            $phone !== '' ? "Display number: {$phone}" : null,
            "Verified name: {$name}",
            "Quality rating: {$quality}",
            'Use this as ground truth for how the business presents itself on WhatsApp.',
            'Inbound product for new leads: '.(DeskSetting::current()->inboundProduct?->name ?? 'first active product'),
        ]));

        KnowledgeArticle::query()->updateOrCreate(
            ['source' => 'whatsapp_meta', 'external_id' => $externalId],
            [
                'title' => 'WhatsApp Business · '.$name,
                'body' => $body,
                'product_id' => DeskSetting::current()->inbound_product_id,
                'is_active' => true,
                'synced_at' => now(),
            ],
        );

        return 1;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function mineConversationFaqs(): array
    {
        $messages = Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'body']);

        $counts = [];

        foreach ($messages as $message) {
            $normalized = $this->normalize((string) $message->body);

            if ($normalized === '' || mb_strlen($normalized) < 8) {
                continue;
            }

            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        arsort($counts);
        $created = 0;

        foreach (array_slice($counts, 0, 25, true) as $text => $hits) {
            if ($hits < 2) {
                continue;
            }

            $externalId = 'wa-faq-'.substr(hash('sha256', $text), 0, 24);

            KnowledgeArticle::query()->updateOrCreate(
                ['source' => 'whatsapp_inbox', 'external_id' => $externalId],
                [
                    'title' => 'Customer ask · '.Str::limit($text, 60, '…'),
                    'body' => "Frequent WhatsApp inbound (seen {$hits} times):\n\n{$text}\n\nDraft a grounded reply using product notes. Do not invent prices.",
                    'product_id' => null,
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );
            $created++;
        }

        return [$created, $messages->count()];
    }

    private function normalize(string $body): string
    {
        $body = preg_replace('/\s+/', ' ', trim($body)) ?? '';

        return mb_strtolower($body);
    }
}
