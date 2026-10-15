<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\InteractionDirection;
use App\Leads\InteractionType;
use App\Leads\Lead;
use App\Leads\LeadInteraction;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordLeadInteraction
{
    public function __construct(private RecordActivity $activity) {}

    /**
     * @param  array{type: string, direction?: string|null, body: string, occurred_at?: string|null, duration_minutes?: int|null, outcome?: string|null}  $data
     */
    public function handle(Lead $lead, array $data, User $actor): LeadInteraction
    {
        $type = InteractionType::from($data['type']);
        $direction = InteractionDirection::from(
            filled($data['direction'] ?? null)
                ? (string) $data['direction']
                : ($type === InteractionType::Note ? InteractionDirection::None->value : InteractionDirection::Outbound->value),
        );

        $occurredAt = filled($data['occurred_at'] ?? null)
            ? Carbon::parse((string) $data['occurred_at'])
            : now();

        $interaction = LeadInteraction::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'user_id' => $actor->id,
            'type' => $type,
            'direction' => $direction,
            'body' => trim((string) $data['body']),
            'occurred_at' => $occurredAt,
            'duration_minutes' => isset($data['duration_minutes']) && $data['duration_minutes'] !== null
                ? (int) $data['duration_minutes']
                : null,
            'outcome' => filled($data['outcome'] ?? null) ? trim((string) $data['outcome']) : null,
        ]);

        $this->activity->handle(
            ActivityEvent::InteractionRecorded,
            $lead,
            $lead->contact,
            $actor,
            [
                'interaction_id' => $interaction->id,
                'type' => $type->value,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );

        return $interaction;
    }
}
