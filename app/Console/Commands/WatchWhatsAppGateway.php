<?php

namespace App\Console\Commands;

use App\WhatsApp\EvolutionGateway;
use App\WhatsApp\WebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The gateway can report "open" while its socket has stalled. FlareSend is
 * still linked to the same number, so when FlareSend keeps receiving and
 * the gateway goes quiet, reconnect the gateway from its saved session.
 */
class WatchWhatsAppGateway extends Command
{
    protected $signature = 'whatsapp:gateway-watch {--quiet-minutes=15}';

    protected $description = 'Reconnect the self-hosted WhatsApp gateway if it has silently stopped receiving.';

    public function handle(EvolutionGateway $gateway): int
    {
        if (! $gateway->configured()) {
            return self::SUCCESS;
        }

        $quiet = max(5, (int) $this->option('quiet-minutes'));
        $recent = WebhookEvent::query()->where('created_at', '>=', now()->subHours(6))->orderByDesc('id')->get(['id', 'created_at', 'payload']);
        $lastGateway = $recent->first(fn ($e) => ($e->payload['provider'] ?? null) === 'evolution')?->created_at;
        $lastOther = $recent->first(fn ($e) => ($e->payload['provider'] ?? null) !== 'evolution')?->created_at;

        $state = $gateway->state();
        $stalled = $state !== 'open'
            || ($lastOther !== null && $lastOther->greaterThan(now()->subMinutes($quiet))
                && ($lastGateway === null || $lastGateway->lessThan(now()->subMinutes($quiet))));

        Cache::put('evolution.watch', [
            'checked_at' => now()->toIso8601String(),
            'state' => $state,
            'stalled' => $stalled,
        ], now()->addHour());

        if (! $stalled) {
            $this->info("Gateway healthy ({$state}).");

            return self::SUCCESS;
        }

        // Do not restart more than once every 20 minutes.
        if (! Cache::add('evolution.restarted', true, now()->addMinutes(20))) {
            $this->warn('Gateway looks stalled; restarted recently, waiting.');

            return self::SUCCESS;
        }

        $ok = $gateway->restart();
        Log::warning('WhatsApp gateway looked stalled and was reconnected.', ['state' => $state, 'restart_ok' => $ok]);
        $this->warn('Gateway looked stalled; reconnect '.($ok ? 'sent' : 'failed').'.');

        return self::SUCCESS;
    }
}
