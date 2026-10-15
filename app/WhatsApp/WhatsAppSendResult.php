<?php

namespace App\WhatsApp;

final class WhatsAppSendResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public SendAttempt $attempt,
        public ?string $providerMessageId,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function accepted(string $providerMessageId, array $raw): self
    {
        return new self(SendAttempt::Accepted, $providerMessageId, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function transient(array $raw): self
    {
        return new self(SendAttempt::Transient, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function unknown(array $raw): self
    {
        return new self(SendAttempt::Unknown, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(array $raw): self
    {
        return new self(SendAttempt::Failed, null, $raw);
    }
}
