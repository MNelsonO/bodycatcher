<?php

namespace App\Http\Controllers;

use App\Calendar\CalendarActivity;
use App\Conversations\Conversation;
use App\Conversations\MessageDirection;
use App\Leads\Services\CommandCentre;
use App\Settings\DisplayClock;
use App\WhatsApp\EvolutionGateway;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CommandCentre $commandCentre, DisplayClock $clock, EvolutionGateway $gateway): Response
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
}
