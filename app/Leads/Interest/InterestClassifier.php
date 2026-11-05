<?php

namespace App\Leads\Interest;

use App\Conversations\Message;
use App\Leads\Lead;

/**
 * Works out which Kemet system a lead is about: from the Click-to-WhatsApp
 * ad that started the chat, otherwise from what the person wrote. When it
 * cannot tell, it leaves the lead unclassified so the desk asks first.
 */
class InterestClassifier
{
    /**
     * @return array<string, string> key => display name
     */
    public function systems(): array
    {
        $systems = [];

        foreach ((array) config('kemet.company.products', []) as $product) {
            if (isset($product['key'])) {
                $systems[(string) $product['key']] = (string) $product['name'];
            }
        }

        return $systems;
    }

    public function label(?string $key): ?string
    {
        return $key === null ? null : ($this->systems()[$key] ?? $key);
    }

    /**
     * The system a text points to, or null when nothing or several tie.
     */
    public function fromText(string $text): ?string
    {
        $text = ' '.mb_strtolower($text).' ';
        $scores = [];

        foreach ((array) config('kemet.company.products', []) as $product) {
            $key = $product['key'] ?? null;

            if (! is_string($key)) {
                continue;
            }

            foreach ((array) ($product['signals'] ?? $product['match'] ?? []) as $word) {
                $pattern = '/(?<![\\p{L}\\p{N}])'.preg_quote(mb_strtolower((string) $word), '/').'(?![\\p{L}\\p{N}])/u';
                $count = preg_match_all($pattern, $text) ?: 0;

                if ($count > 0) {
                    $scores[$key] = ($scores[$key] ?? 0) + $count;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        $top = array_values($scores);

        return count($top) > 1 && $top[0] === $top[1] ? null : (string) array_key_first($scores);
    }

    /**
     * Ad details from a WhatsApp message (externalAdReply on any part).
     *
     * @param  array<string, mixed>  $message
     * @return array{id: ?string, title: ?string, body: ?string, url: ?string, source: ?string}|null
     */
    public function adFromMessage(array $message): ?array
    {
        foreach ($message as $part) {
            $context = is_array($part) && is_array($part['contextInfo'] ?? null) ? $part['contextInfo'] : null;
            $ad = $context !== null && is_array($context['externalAdReply'] ?? null) ? $context['externalAdReply'] : null;

            if ($ad === null) {
                continue;
            }

            $string = fn ($value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

            return [
                'id' => $string($ad['sourceId'] ?? $ad['ctwaClid'] ?? null),
                'title' => $string($ad['title'] ?? null),
                'body' => $string($ad['body'] ?? null),
                'url' => $string($ad['sourceUrl'] ?? $ad['mediaUrl'] ?? null),
                'source' => $string($context['entryPointConversionSource'] ?? $ad['sourceType'] ?? null),
            ];
        }

        return null;
    }

    /**
     * Records an ad on the lead (first ad wins) and classifies from it.
     *
     * @param  array{id: ?string, title: ?string, body: ?string, url: ?string, source: ?string}  $ad
     */
    public function recordAd(Lead $lead, array $ad): void
    {
        if ($lead->ad_first_seen_at === null) {
            $lead->ad_id = $ad['id'];
            $lead->ad_title = $ad['title'] !== null ? mb_substr($ad['title'], 0, 250) : null;
            $lead->ad_body = $ad['body'] !== null ? mb_substr($ad['body'], 0, 3000) : null;
            $lead->ad_url = $ad['url'] !== null ? mb_substr($ad['url'], 0, 1000) : null;
            $lead->ad_first_seen_at = now();
        }

        if ($lead->interest_source !== 'manual') {
            $system = $this->fromText(($ad['title'] ?? '').' '.($ad['body'] ?? '').' '.($ad['url'] ?? ''));

            if ($system !== null) {
                $lead->interest_system = $system;
                $lead->interest_source = 'ad';
            }
        }

        $lead->saveQuietly();
    }

    /**
     * Classifies an unclassified lead from what the client wrote.
     */
    public function classifyFromChat(Lead $lead): ?string
    {
        if (in_array($lead->interest_source, ['manual', 'ad'], true) || $lead->conversation === null) {
            return $lead->interest_system;
        }

        $text = Message::query()
            ->where('conversation_id', $lead->conversation->id)
            ->where('direction', 'inbound')
            ->where('body', '!=', '')
            ->orderBy('sent_at')
            ->limit(30)
            ->pluck('body')
            ->implode(' ');

        if ($lead->ad_body !== null || $lead->ad_title !== null) {
            $text = $lead->ad_title.' '.$lead->ad_body.' '.$text;
        }

        $system = $this->fromText($text);

        if ($system !== null && $system !== $lead->interest_system) {
            $lead->interest_system = $system;
            $lead->interest_source = $lead->ad_body !== null ? 'ad' : 'chat';
            $lead->saveQuietly();
        }

        return $lead->interest_system;
    }

    public function setManually(Lead $lead, ?string $system): void
    {
        $lead->interest_system = $system !== null && isset($this->systems()[$system]) ? $system : null;
        $lead->interest_source = $lead->interest_system === null ? null : 'manual';
        $lead->saveQuietly();
    }
}
