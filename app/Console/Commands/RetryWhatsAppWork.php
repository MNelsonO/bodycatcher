<?php

namespace App\Console\Commands;

use App\Conversations\Message;
use App\Conversations\MessageStatus;
use App\WhatsApp\Services\InboundWhatsAppProcessor;
use App\WhatsApp\Services\SendWhatsAppMessage;
use App\WhatsApp\WebhookEvent;
use Illuminate\Console\Command;
use Throwable;

class RetryWhatsAppWork extends Command
{
    protected $signature = 'whatsapp:retry';

    protected $description = 'Process stored WhatsApp webhooks and retry queued sends without depending on Redis';

    public function handle(InboundWhatsAppProcessor $processor, SendWhatsAppMessage $sender): int
    {
        WebhookEvent::query()
            ->whereNull('processed_at')
            ->orderBy('id')
            ->each(function (WebhookEvent $event) use ($processor): void {
                try {
                    $processor->process($event->id);
                } catch (Throwable $exception) {
                    $event->processing_error = $exception->getMessage();
                    $event->save();
                    $this->error($exception->getMessage());
                }
            });

        Message::query()
            ->where('status', MessageStatus::Queued->value)
            ->where('retryable', true)
            ->orderBy('id')
            ->each(function (Message $message) use ($sender): void {
                $sender->retry($message->id);
            });

        $this->info('WhatsApp retry pass finished.');

        return self::SUCCESS;
    }
}
