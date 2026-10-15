<?php

namespace App\Leads\Services;

use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\SalesPipelines\StageKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesStageTransitionService
{
    public function __construct(private RecordActivity $activity) {}

    public function transition(Lead $lead, PipelineStage $to, User $actor, ?string $reason = null): Lead
    {
        $reason = $this->clean($reason);

        return DB::transaction(function () use ($lead, $to, $actor, $reason): Lead {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $locked->load('currentStage');
            $from = $locked->currentStage;

            if ($from->id === $to->id) {
                throw ValidationException::withMessages([
                    'stage_id' => 'The lead is already in that stage.',
                ]);
            }

            if ($to->pipeline_id !== $locked->pipeline_id || $to->archived_at !== null) {
                throw ValidationException::withMessages([
                    'stage_id' => 'That stage is not available on this pipeline.',
                ]);
            }

            $permitted = PipelineStageTransition::query()
                ->where('from_stage_id', $from->id)
                ->where('to_stage_id', $to->id)
                ->exists();

            if (! $permitted) {
                throw ValidationException::withMessages([
                    'stage_id' => 'This stage transition is not permitted.',
                ]);
            }

            if ($to->kind === StageKind::Lost && $reason === null) {
                throw ValidationException::withMessages([
                    'lost_reason' => 'A reason is required when moving to a lost stage.',
                ]);
            }

            $closing = $to->kind->isClosed();

            if (! $closing) {
                $conflict = Lead::query()
                    ->where('contact_id', $locked->contact_id)
                    ->where('product_id', $locked->product_id)
                    ->whereNull('closed_at')
                    ->whereKeyNot($locked->id)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'stage_id' => 'Another open lead already exists for this contact and product.',
                    ]);
                }
            }

            $locked->current_stage_id = $to->id;
            $locked->closed_at = $closing ? ($locked->closed_at ?? now()) : null;

            if ($to->kind === StageKind::Lost) {
                $locked->lost_reason = $reason;
            }

            $locked->save();

            LeadStageHistory::query()->create([
                'lead_id' => $locked->id,
                'from_stage_id' => $from->id,
                'to_stage_id' => $to->id,
                'reason' => $reason,
                'actor_user_id' => $actor->id,
            ]);

            $this->activity->handle(
                ActivityEvent::StageChanged,
                $locked,
                $locked->contact,
                $actor,
                [
                    'from_stage' => $from->name,
                    'to_stage' => $to->name,
                    'reason' => $reason,
                ],
            );

            return $locked->refresh();
        });
    }

    private function clean(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        return $trimmed === '' ? null : $trimmed;
    }
}
