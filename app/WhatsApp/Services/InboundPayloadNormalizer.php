<?php

namespace App\WhatsApp\Services;

/**
 * Turns FlareSend and Twilio webhook bodies into the Meta-shaped payload
 * that InboundWhatsAppProcessor already understands.
 */
class InboundPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromFlareSend(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        if ($this->isStatusOrGroup($payload, $data)) {
            // Status posts and group messages are not chats with a client.
            return $this->withRaw($this->wrap('', 'ignored-'.hash('sha256', json_encode($payload) ?: ''), 'text', '', (int) now()->timestamp, ''), $payload);
        }
        $from = $this->digits($this->jid((string) ($data['from'] ?? $payload['from'] ?? '')));
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $type = is_string($message['type'] ?? null) ? $message['type'] : 'text';
        $text = $this->textFrom($message, $data);

        if ($text === '' && $message !== []) {
            $text = '[media]';
        }

        $timestamp = $this->timestamp($data['timestamp'] ?? $payload['timestamp'] ?? null);
        $id = is_string($data['id'] ?? null) && $data['id'] !== ''
            ? $data['id']
            : (is_string($message['id'] ?? null) && $message['id'] !== ''
                ? $message['id']
                : 'flaresend-'.hash('sha256', $from.'|'.$text.'|'.$timestamp));
        $outbound = $this->sentByOwner($payload, $data);

        if ($outbound) {
            // A message the account owner sent from their own WhatsApp. The
            // chat partner is the recipient, never the owner's own number.
            $to = $this->recipient($payload, $data);

            // Baileys-style payloads put the chat partner in "from" even when
            // the owner wrote the message.
            if ($to === '') {
                $to = $from;
            }
            $normalized = $this->wrap($to, $id, $type, $text, (int) $timestamp, '');
            $normalized['entry'][0]['changes'][0]['value']['messages'][0]['kemet_outbound'] = true;
            unset($normalized['entry'][0]['changes'][0]['value']['contacts']);

            return $this->withRaw($normalized, $payload);
        }

        $name = $this->displayName($payload, $data, $message, $from);

        return $this->withRaw($this->wrap($from, $id, $type, $text, (int) $timestamp, $name), $payload);
    }

    /**
     * FlareSend delivers a contact's Status post as if the poster sent it to
     * us; the only tell is a sender-key message for "status@broadcast".
     * Group messages carry the same kind of key with a "@g.us" group id.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function isStatusOrGroup(array $payload, array $data): bool
    {
        $jids = [
            (string) ($data['from'] ?? $payload['from'] ?? ''),
            (string) ($data['key']['remoteJid'] ?? ''),
            (string) ($data['chatId'] ?? ''),
        ];

        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $skdm = $message['senderKeyDistributionMessage'] ?? null;

        if (is_array($skdm)) {
            $jids[] = (string) ($skdm['groupId'] ?? 'status@broadcast');
        }

        if ($this->isStatusMessage($message) || $this->isSystemMessage($message)) {
            return true;
        }

        foreach ($jids as $jid) {
            if (str_starts_with($jid, 'status@') || str_ends_with($jid, '@broadcast') || str_ends_with($jid, '@g.us') || str_ends_with($jid, '@newsletter')) {
                return true;
            }
        }

        return false;
    }

    /**
     * WhatsApp marks Status posts with contextInfo.statusSourceType on the
     * media or text, even when no sender-key message is attached.
     *
     * @param  array<string, mixed>  $message
     */
    public function isStatusMessage(array $message): bool
    {
        foreach ($message as $part) {
            if (is_array($part) && is_array($part['contextInfo'] ?? null) && array_key_exists('statusSourceType', $part['contextInfo'])) {
                return true;
            }
        }

        return is_array($message['messageContextInfo'] ?? null) && array_key_exists('statusSourceType', $message['messageContextInfo']);
    }

    /**
     * Invisible system traffic: edits, deletions, reactions, key shares,
     * disappearing-message settings. Nothing a person wrote.
     *
     * @param  array<string, mixed>  $message
     */
    public function isSystemMessage(array $message): bool
    {
        $content = array_diff(array_keys($message), ['messageContextInfo', 'senderKeyDistributionMessage']);

        if ($content === []) {
            return $message !== [];
        }

        return array_intersect($content, ['protocolMessage', 'reactionMessage', 'editedMessage', 'pollUpdateMessage', 'keepInChatMessage', 'encReactionMessage', 'deviceSentMessage']) !== []
            && count($content) === count(array_intersect($content, ['protocolMessage', 'reactionMessage', 'editedMessage', 'pollUpdateMessage', 'keepInChatMessage', 'encReactionMessage', 'deviceSentMessage']));
    }

    /**
     * FlareSend does not document owner-sent messages, so accept the common
     * markers: an explicit fromMe flag (also Baileys' key.fromMe), a sent-type
     * event name, or a message with a recipient but no sender.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function sentByOwner(array $payload, array $data): bool
    {
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        foreach ([$data['fromMe'] ?? null, $data['from_me'] ?? null, $data['isFromMe'] ?? null, $key['fromMe'] ?? null, $payload['fromMe'] ?? null] as $flag) {
            if ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true') {
                return true;
            }
        }

        $event = mb_strtolower((string) ($payload['event'] ?? ''));

        if (in_array($event, ['message_sent', 'message.sent', 'message_create', 'message.create', 'messages.sent', 'message_outgoing'], true)) {
            return true;
        }

        $from = $this->digits($this->jid((string) ($data['from'] ?? $payload['from'] ?? '')));

        return $from === '' && $this->recipient($payload, $data) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function recipient(array $payload, array $data): string
    {
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        foreach ([
            $data['to'] ?? null,
            $data['recipient'] ?? null,
            $data['chatId'] ?? null,
            $data['chat_id'] ?? null,
            $data['remoteJid'] ?? null,
            $key['remoteJid'] ?? null,
            $payload['to'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && ! str_contains($candidate, '@g.us')) {
                $digits = $this->digits($this->jid($candidate));

                if ($digits !== '') {
                    return $digits;
                }
            }
        }

        return '';
    }

    /**
     * Keep the provider's original body (trimmed) so the payload shape can be
     * checked later. The processor ignores this key.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withRaw(array $normalized, array $payload): array
    {
        $json = json_encode($payload, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE) ?: '';
        $normalized['raw'] = strlen($json) <= 20000 ? $payload : ['truncated' => mb_substr($json, 0, 20000)];

        return $normalized;
    }

    /**
     * Evolution API (Baileys). "messages.upsert" is a live message, sent or
     * received; "messages.set" is the history synced when the phone links.
     * Groups, status updates and channels are ignored.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function fromEvolution(array $payload): ?array
    {
        $event = mb_strtolower(str_replace('_', '.', (string) ($payload['event'] ?? '')));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        $history = $event === 'messages.set';

        if (! in_array($event, ['messages.upsert', 'messages.set', 'send.message'], true)) {
            return null;
        }

        $items = $history ? ($data['messages'] ?? $data) : (array_is_list($data) ? $data : [$data]);
        $messages = [];
        $profiles = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = is_array($item['key'] ?? null) ? $item['key'] : [];
            $jid = $this->evolutionChatJid($key, $item);

            if ($jid === null) {
                continue;
            }

            $id = is_string($key['id'] ?? null) ? $key['id'] : '';
            $message = is_array($item['message'] ?? null) ? $item['message'] : [];

            if ($this->isStatusMessage($message) || $this->isSystemMessage($message)) {
                continue;
            }
            $text = $this->textFrom($message, $item);
            $type = $text !== '' ? 'text' : 'unknown';

            if ($id === '' || ($text === '' && $message === [])) {
                continue;
            }

            if ($text === '') {
                $text = '[media]';
                $type = 'text';
            }

            $fromMe = ($key['fromMe'] ?? false) === true || $event === 'send.message';
            $timestamp = $this->timestamp($item['messageTimestamp'] ?? null);
            $row = ['from' => $jid, 'id' => $id, 'timestamp' => (string) $timestamp, 'type' => $type, 'text' => ['body' => $text]];

            if ($fromMe) {
                $row['kemet_outbound'] = true;
            } else {
                $name = $this->cleanName((string) ($item['pushName'] ?? ''));

                if ($name !== null) {
                    $profiles[$jid] = ['profile' => ['name' => $name], 'wa_id' => $jid];
                }
            }

            if ($history) {
                $row['kemet_history'] = true;
            }

            $messages[] = $row;
        }

        if ($messages === []) {
            return null;
        }

        return $this->withRaw([
            'object' => 'whatsapp_business_account',
            'provider' => 'evolution',
            'entry' => [[
                'id' => 'INBOUND',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => array_values($profiles),
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ], $history ? ['event' => $payload['event'] ?? null, 'count' => count($messages)] : $payload);
    }

    /**
     * The chat partner's number. Newer WhatsApp ids are privacy "@lid" ids;
     * prefer the phone-number id when Baileys provides it.
     *
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $item
     */
    private function evolutionChatJid(array $key, array $item): ?string
    {
        $remote = (string) ($key['remoteJid'] ?? '');

        if ($remote === '' || str_ends_with($remote, '@g.us') || str_starts_with($remote, 'status@') || str_ends_with($remote, '@newsletter') || str_ends_with($remote, '@broadcast')) {
            return null;
        }

        if (str_ends_with($remote, '@lid')) {
            foreach ([$key['remoteJidAlt'] ?? null, $key['senderPn'] ?? null, $item['senderPn'] ?? null] as $alt) {
                if (is_string($alt) && str_contains($alt, '@s.whatsapp.net')) {
                    $remote = $alt;
                    break;
                }
            }
        }

        $digits = $this->digits($this->jid($remote));

        if ($digits === '') {
            return null;
        }

        // An unresolved privacy id: keep it as "+<id>", the form contacts
        // created from such chats already use, so it matches the same contact.
        return str_ends_with($remote, '@lid') ? '+'.$digits : $digits;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromTwilio(array $payload): array
    {
        $fromRaw = (string) ($payload['WaId'] ?? $payload['From'] ?? '');
        $from = $this->digits(str_replace('whatsapp:', '', $fromRaw));
        $text = (string) ($payload['Body'] ?? '');
        $id = (string) ($payload['MessageSid'] ?? ('twilio-'.hash('sha256', $from.'|'.$text)));
        $name = $this->cleanName((string) ($payload['ProfileName'] ?? ''))
            ?? $this->fallbackName($from);
        $timestamp = now()->timestamp;

        return $this->wrap($from, $id, 'text', $text, $timestamp, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function wrap(string $from, string $id, string $type, string $text, int $timestamp, string $name): array
    {
        $message = [
            'from' => $from,
            'id' => $id,
            'timestamp' => (string) $timestamp,
            'type' => $type,
        ];

        if ($type === 'text') {
            $message['text'] = ['body' => $text];
        }

        return [
            'object' => 'whatsapp_business_account',
            'provider' => 'normalized',
            'entry' => [[
                'id' => 'INBOUND',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [[
                            'profile' => ['name' => $name],
                            'wa_id' => $from,
                        ]],
                        'messages' => [$message],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $data
     */
    private function textFrom(array $message, array $data): string
    {
        if (is_string($message['text'] ?? null) && $message['text'] !== '') {
            return $message['text'];
        }

        $extended = $message['extendedTextMessage'] ?? null;

        if (is_array($extended) && is_string($extended['text'] ?? null)) {
            return $extended['text'];
        }

        if (is_string($message['conversation'] ?? null)) {
            return $message['conversation'];
        }

        foreach (['imageMessage', 'videoMessage', 'documentMessage'] as $media) {
            $block = $message[$media] ?? null;

            if (is_array($block) && is_string($block['caption'] ?? null) && $block['caption'] !== '') {
                return $block['caption'];
            }
        }

        return is_string($data['text'] ?? null) ? $data['text'] : '';
    }

    private function timestamp(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $parsed = strtotime($value);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return now()->timestamp;
    }

    /**
     * Pull a display name from the fields FlareSend / Baileys-style payloads use.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $message
     */
    private function displayName(array $payload, array $data, array $message, string $from): string
    {
        foreach ($this->nameCandidates($payload, $data, $message) as $candidate) {
            $clean = $this->cleanName($candidate);

            if ($clean !== null) {
                return $clean;
            }
        }

        $fromText = $this->nameFromMessageText($this->textFrom($message, $data));

        if ($fromText !== null) {
            return $fromText;
        }

        return $this->fallbackName($from);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $message
     * @return list<string>
     */
    private function nameCandidates(array $payload, array $data, array $message): array
    {
        $candidates = [];
        // Prefer WhatsApp username / push name. Formal (personal) chats often have
        // no verified business name — verifiedBiz* is last-resort only.
        $keys = [
            'pushName', 'pushname', 'notify', 'senderName', 'profileName',
            'contactName', 'ProfileName', 'displayName', 'name',
            'verifiedName', 'verifiedBizName',
        ];

        foreach ([$data, $message, $payload] as $block) {
            foreach ($keys as $key) {
                $value = $block[$key] ?? null;

                if (is_string($value)) {
                    $candidates[] = $value;
                }
            }
        }

        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        foreach ($keys as $field) {
            $value = $key[$field] ?? null;

            if (is_string($value)) {
                $candidates[] = $value;
            }
        }

        $this->collectNamedStrings($payload, $candidates);

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $into
     */
    private function collectNamedStrings(array $node, array &$into, int $depth = 0): void
    {
        if ($depth > 4) {
            return;
        }

        foreach ($node as $key => $value) {
            $keyLower = mb_strtolower((string) $key);

            if (is_string($value) && (
                str_contains($keyLower, 'name')
                || $keyLower === 'notify'
                || $keyLower === 'pushname'
            )) {
                $into[] = $value;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $this->collectNamedStrings($value, $into, $depth + 1);
            }
        }
    }

    private function nameFromMessageText(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/\b(?:my name is|i am|i\'m|this is)\s+([A-Za-z][A-Za-z\'\-]+(?:\s+[A-Za-z][A-Za-z\'\-]+)?)\b/iu', $text, $match) !== 1) {
            return null;
        }

        return $this->cleanName($match[1] ?? '');
    }

    private function cleanName(string $value): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($name === '') {
            return null;
        }

        $lower = mb_strtolower($name);
        $junk = [
            'whatsapp contact',
            'whatsapp lead',
            'unknown',
            'null',
            'undefined',
            'contact',
        ];

        if (in_array($lower, $junk, true)) {
            return null;
        }

        // Ignore raw JIDs / bare phone strings masquerading as names.
        if (str_contains($name, '@') || preg_match('/^\+?\d{8,15}$/', $name) === 1) {
            return null;
        }

        return mb_substr($name, 0, 120);
    }

    private function fallbackName(string $from): string
    {
        if ($from === '') {
            return 'WhatsApp contact';
        }

        return 'WhatsApp +'.$from;
    }

    private function jid(string $value): string
    {
        $at = strpos($value, '@');

        return $at === false ? $value : substr($value, 0, $at);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
