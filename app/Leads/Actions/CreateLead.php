<?php

namespace App\Leads\Actions;

use App\Contacts\Contact;
use App\Conversations\Conversation;
use App\Leads\ActivityEvent;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLead
{
    public function __construct(private RecordActivity $activity) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $contact = Contact::query()->findOrFail((int) $data['contact_id'])->survivor();

            if ($contact->merged_into_contact_id !== null) {
                throw ValidationException::withMessages([
                    'contact_id' => 'That contact was merged into another record.',
                ]);
            }

            $product = Product::query()->findOrFail((int) $data['product_id']);

            if (! $product->is_active || $product->archived_at !== null) {
                throw ValidationException::withMessages([
                    'product_id' => 'Choose an active product.',
                ]);
            }

            $pipeline = Pipeline::query()->findOrFail((int) $data['pipeline_id']);

            if ($pipeline->archived_at !== null) {
                throw ValidationException::withMessages([
                    'pipeline_id' => 'Choose an active pipeline.',
                ]);
            }

            $stage = $this->stage($pipeline, $data['stage_id'] ?? null);
            $reason = $this->nullableString($data['lost_reason'] ?? null);

            if ($stage->kind === StageKind::Lost && $reason === null) {
                throw ValidationException::withMessages([
                    'lost_reason' => 'A reason is required when opening a lead in a lost stage.',
                ]);
            }

            $openExists = Lead::query()
                ->where('contact_id', $contact->id)
                ->where('product_id', $product->id)
                ->whereNull('closed_at')
                ->exists();

            if ($openExists && ! $stage->kind->isClosed()) {
                throw ValidationException::withMessages([
                    'product_id' => 'This contact already has an open lead for that product.',
                ]);
            }

            if ($openExists && $stage->kind->isClosed()) {
                throw ValidationException::withMessages([
                    'product_id' => 'This contact already has an open lead for that product. Close it before opening another.',
                ]);
            }

            $lead = Lead::query()->create([
                'contact_id' => $contact->id,
                'product_id' => $product->id,
                'pipeline_id' => $pipeline->id,
                'current_stage_id' => $stage->id,
                'source' => $this->nullableString($data['source'] ?? null),
                'business_problem' => $this->nullableString($data['business_problem'] ?? null),
                'current_solution' => $this->nullableString($data['current_solution'] ?? null),
                'branch_count' => $data['branch_count'] ?? null,
                'budget_note' => $this->nullableString($data['budget_note'] ?? null),
                'decision_maker_status' => DecisionMakerStatus::from((string) ($data['decision_maker_status'] ?? DecisionMakerStatus::Unknown->value)),
                'purchase_timeline_note' => $this->nullableString($data['purchase_timeline_note'] ?? null),
                'next_action' => $this->nullableString($data['next_action'] ?? null),
                'next_follow_up_on' => $this->nullableString($data['next_follow_up_on'] ?? null),
                'lost_reason' => $stage->kind === StageKind::Lost ? $reason : null,
                'closed_at' => $stage->kind->isClosed() ? now() : null,
            ]);

            Conversation::query()->create([
                'lead_id' => $lead->id,
                'contact_id' => $contact->id,
            ]);

            LeadStageHistory::query()->create([
                'lead_id' => $lead->id,
                'from_stage_id' => null,
                'to_stage_id' => $stage->id,
                'reason' => $reason ?? 'Lead opened',
                'actor_user_id' => $actor->id,
            ]);

            $this->activity->handle(
                ActivityEvent::LeadCreated,
                $lead,
                $contact,
                $actor,
                [
                    'product' => $product->name,
                    'stage' => $stage->name,
                ],
            );

            if ($lead->next_follow_up_on !== null) {
                $this->activity->handle(
                    ActivityEvent::FollowUpChanged,
                    $lead,
                    $contact,
                    $actor,
                    [
                        'from' => null,
                        'to' => $lead->next_follow_up_on->toDateString(),
                    ],
                );
            }

            return $lead->refresh();
        });
    }

    private function stage(Pipeline $pipeline, mixed $stageId): PipelineStage
    {
        if ($stageId !== null && $stageId !== '') {
            $stage = $pipeline->stages()->whereKey((int) $stageId)->whereNull('archived_at')->first();

            if ($stage === null) {
                throw ValidationException::withMessages([
                    'stage_id' => 'Choose a stage on the selected pipeline.',
                ]);
            }

            return $stage;
        }

        $stage = $pipeline->stages()
            ->where('kind', StageKind::Active->value)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->first();

        if ($stage === null) {
            throw ValidationException::withMessages([
                'pipeline_id' => 'This pipeline has no active stage to start from.',
            ]);
        }

        return $stage;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
