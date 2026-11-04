<?php

namespace App\Http\Controllers;

use App\Calendar\CalendarActivity;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Lead;
use App\Leads\Services\CommandCentre;
use App\Leads\Services\CommandCentreBoard;
use App\Settings\DisplayClock;
use App\WhatsApp\EvolutionGateway;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CommandCentre $commandCentre, DisplayClock $clock, EvolutionGateway $gateway, CommandCentreBoard $board): Response
    {
        return Inertia::render('dashboard', [
            'metrics' => $commandCentre->metrics([
                'kind' => $request->string('kind')->toString(),
                'stage_id' => $request->string('stage_id')->toString(),
                'product_id' => $request->string('product_id')->toString(),
                'hot' => $request->string('hot')->toString(),
                'follow_up' => $request->string('follow_up')->toString(),
                'timeline' => $request->string('timeline')->toString(),
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
                'q' => $request->string('q')->toString(),
            ]),
            'waiting' => fn () => $this->waiting($clock),
            'waiting_count' => fn () => $this->waitingCount(),
            'going_cold' => fn () => $this->goingCold($board),
            'reply_stats' => fn () => $this->replyStats($clock),
            'agenda' => fn () => $this->agenda($request, $clock),
            'whatsapp_link' => fn () => $this->whatsappLink($gateway),
        ]);
    }

    /**
     * Chats where the client spoke last and nobody has answered yet, over
     * the last week, longest wait first.
     *
     * @return list<array<string, mixed>>
     */
    private function waiting(DisplayClock $clock): array
    {
        $rows = [];

        $conversations = Conversation::query()
            ->with(['latestMessage', 'contact', 'lead.currentStage'])
            ->where('last_message_at', '>=', now()->subDays(7))
            ->orderBy('last_message_at')
            ->limit(200)
            ->get();

        foreach ($conversations as $conversation) {
            $latest = $conversation->latestMessage;

            if ($latest === null || $latest->direction !== MessageDirection::Inbound) {
                continue;
            }

            $rows[] = [
                'id' => $conversation->id,
                'contact_name' => $conversation->contact->full_name,
                'stage' => $conversation->lead->currentStage->name,
                'body' => mb_strimwidth($latest->body, 0, 90, '…'),
                'waiting_for' => $latest->sent_at->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE),
                'minutes' => (int) $latest->sent_at->diffInMinutes(now()),
            ];

            if (count($rows) >= 8) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function agenda(Request $request, DisplayClock $clock): array
    {
        return CalendarActivity::query()
            ->where('user_id', $request->user()?->id)
            ->whereDate('activity_on', $clock->today())
            ->orderByRaw('start_time is null desc')
            ->orderBy('start_time')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (CalendarActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'time' => $activity->start_time,
                'type' => $activity->activity_type,
                'location' => $activity->location,
                'lead_id' => $activity->lead_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{driver: string, state: string}
     */
    private function whatsappLink(EvolutionGateway $gateway): array
    {
        $driver = (string) config('whatsapp.driver');

        return [
            'driver' => $driver,
            'state' => $driver === 'evolution' ? $gateway->state() : 'n/a',
        ];
    }

    private function waitingCount(): int
    {
        return Conversation::query()
            ->with('latestMessage')
            ->where('last_message_at', '>=', now()->subDays(7))
            ->get()
            ->filter(fn (Conversation $conversation) => $conversation->latestMessage?->direction === MessageDirection::Inbound)
            ->count();
    }

    /**
     * Hot leads nobody has talked to for three days or more.
     *
     * @return array<int, array<string, mixed>>
     */
    private function goingCold(CommandCentreBoard $board): array
    {
        $cutoff = now()->subDays(3);

        return $board->hotLeads()
            ->with(['contact', 'currentStage', 'conversation'])
            ->get()
            ->map(function (Lead $lead) use ($cutoff): ?array {
                $last = $lead->conversation?->last_message_at;

                if ($last !== null && $last->greaterThan($cutoff)) {
                    return null;
                }

                return [
                    'id' => $lead->id,
                    'conversation_id' => $lead->conversation?->id,
                    'contact_name' => $lead->contact->full_name,
                    'stage' => $lead->currentStage->name,
                    'silent_days' => $last === null ? null : (int) $last->diffInDays(now()),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => $row['silent_days'] ?? 999)
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * Replies sent today, and the typical (median) time to answer a client
     * over the last 7 days, from chats that hold both sides.
     *
     * @return array{replies_today: int, median_minutes: int|null, answered: int}
     */
    private function replyStats(DisplayClock $clock): array
    {
        [$start] = $clock->todayUtcRange();

        $repliesToday = Message::query()
            ->where('direction', MessageDirection::Outbound->value)
            ->where('channel', 'whatsapp')
            ->where('sent_at', '>=', $start)
            ->count();

        $messages = Message::query()
            ->where('channel', 'whatsapp')
            ->where('sent_at', '>=', now()->subDays(7))
            ->orderBy('conversation_id')
            ->orderBy('sent_at')
            ->get(['conversation_id', 'direction', 'sent_at']);

        $waits = [];
        $pending = [];

        foreach ($messages as $message) {
            $cid = $message->conversation_id;

            if ($message->direction === MessageDirection::Inbound) {
                $pending[$cid] ??= $message->sent_at;
            } elseif (isset($pending[$cid])) {
                $minutes = (int) $pending[$cid]->diffInMinutes($message->sent_at);

                // Ignore replies to chats left overnight for days; they skew the typical time.
                if ($minutes <= 60 * 24 * 2) {
                    $waits[] = $minutes;
                }

                unset($pending[$cid]);
            }
        }

        sort($waits);
        $count = count($waits);
        $median = $count === 0 ? null : $waits[intdiv($count, 2)];

        return ['replies_today' => $repliesToday, 'median_minutes' => $median, 'answered' => $count];
    }
}
