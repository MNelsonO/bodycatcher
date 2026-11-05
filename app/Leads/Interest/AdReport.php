<?php

namespace App\Leads\Interest;

use App\Conversations\MessageDirection;
use App\Leads\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the Ads page: leads per system and per Click-to-WhatsApp ad,
 * with how many got a reply, were won or lost.
 */
class AdReport
{
    public function __construct(private InterestClassifier $classifier) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?int $days): array
    {
        $leads = Lead::query()
            ->with(['currentStage', 'conversation', 'contact'])
            ->when($days !== null, fn ($q) => $q->where('leads.created_at', '>=', now()->subDays((int) $days)))
            ->get();

        $replied = DB::table('messages')
            ->where('direction', MessageDirection::Outbound->value)
            ->where('body', '!=', '')
            ->whereIn('conversation_id', $leads->pluck('conversation.id')->filter()->all())
            ->distinct()
            ->pluck('conversation_id')
            ->flip();

        $stats = function (Collection $group) use ($replied): array {
            $won = $group->filter(fn (Lead $lead) => $lead->outcome() === 'won')->count();

            return [
                'leads' => $group->count(),
                'replied' => $group->filter(fn (Lead $lead) => $lead->conversation !== null && $replied->has($lead->conversation->id))->count(),
                'won' => $won,
                'lost' => $group->filter(fn (Lead $lead) => $lead->outcome() === 'lost')->count(),
                'open' => $group->filter(fn (Lead $lead) => $lead->outcome() === 'open')->count(),
                'from_ads' => $group->whereNotNull('ad_first_seen_at')->count(),
                'conversion' => $group->count() > 0 ? round($won / $group->count() * 100, 1) : 0.0,
            ];
        };

        $systems = collect($this->classifier->systems())
            ->map(fn (string $name, string $key) => ['key' => $key, 'name' => $name] + $stats($leads->where('interest_system', $key)))
            ->filter(fn (array $row) => $row['leads'] > 0 || in_array($row['key'], ['laundry', 'hotel', 'pos', 'carwash'], true))
            ->sortByDesc('leads')
            ->values()
            ->all();

        $ads = $leads->whereNotNull('ad_first_seen_at')
            ->groupBy(fn (Lead $lead) => $lead->ad_id ?? 'title:'.($lead->ad_title ?? 'Untitled ad'))
            ->map(function (Collection $group): array {
                /** @var Lead $first */
                $first = $group->sortBy('ad_first_seen_at')->first();
                $system = $group->pluck('interest_system')->filter()->countBy()->sortDesc()->keys()->first();

                return [
                    'id' => $first->ad_id,
                    'title' => $first->ad_title ?? 'Untitled ad',
                    'body' => $first->ad_body !== null ? mb_strimwidth($first->ad_body, 0, 160, '…') : null,
                    'url' => $first->ad_url,
                    'system' => $system,
                    'system_label' => $this->classifier->label(is_string($system) ? $system : null),
                    'last_seen' => $group->max('ad_first_seen_at')?->toDateString(),
                ];
            });

        $adRows = $ads->map(function (array $ad, string $groupKey) use ($leads, $stats): array {
            $group = $leads->whereNotNull('ad_first_seen_at')->filter(fn (Lead $lead) => ($lead->ad_id ?? 'title:'.($lead->ad_title ?? 'Untitled ad')) === $groupKey);

            return $ad + $stats($group);
        })->sortByDesc('leads')->values()->all();

        $unknown = $leads->whereNull('interest_system')
            ->sortByDesc(fn (Lead $lead) => $lead->conversation?->last_message_at->timestamp ?? 0)
            ->take(25)
            ->map(fn (Lead $lead): array => [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation?->id,
                'name' => $lead->contact->full_name,
                'stage' => $lead->currentStage->name,
            ])
            ->values()
            ->all();

        return [
            'totals' => $stats($leads) + ['unknown' => $leads->whereNull('interest_system')->count()],
            'systems' => $systems,
            'ads' => $adRows,
            'unknown' => $unknown,
        ];
    }
}
