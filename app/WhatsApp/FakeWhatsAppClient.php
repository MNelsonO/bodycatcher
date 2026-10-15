<?php

namespace App\WhatsApp;

/**
 * In-memory client for tests. It never opens a socket.
 */
class FakeWhatsAppClient implements WhatsAppClient
{
    public int $sends = 0;

    /**
     * @var list<array{to: string, body: string, idempotency_key: string}>
     */
    public array $calls = [];

    /**
     * @var list<WhatsAppSendResult>
     */
    public array $queue = [];

    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        $this->sends++;
        $this->calls[] = [
            'to' => $toE164,
            'body' => $body,
            'idempotency_key' => $idempotencyKey,
        ];

        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        $id = 'wamid.fake.'.$this->sends;

        return WhatsAppSendResult::accepted($id, [
            'messages' => [['id' => $id]],
        ]);
    }

    public function push(WhatsAppSendResult $result): void
    {
        $this->queue[] = $result;
    }
}
