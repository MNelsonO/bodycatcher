<?php

namespace App\Ai\Services;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Leads\Actions\RecordActivity;
use App\Leads\Actions\SetFollowUp;
use App\Leads\ActivityEvent;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApplyLeadExtraction
{
    public function __construct(
        private SetFollowUp $followUps,
        private RecordActivity $activity,
    ) {}

    /**
     * @param  list<string>  $fields
     */
    public function apply(LeadAnalysis $analysis, User $actor, array $fields): Lead
    {
        if ($analysis->status !== AnalysisStatus::Completed) {
            throw ValidationException::withMessages([
                'fields' => 'This analysis has no fields to apply.',
            ]);
        }

        $lead = Lead::query()->findOrFail($analysis->lead_id);
        $extraction = $analysis->extraction ?? [];
        $applied = [];

        foreach (['business_problem', 'current_solution', 'budget_note', 'purchase_timeline_note'] as $field) {
            if (! in_array($field, $fields, true)) {
                continue;
            }

            $value = $extraction[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $lead->{$field} = trim($value);
            $applied[] = $field;
        }

        if (in_array('branch_count', $fields, true) && is_int($extraction['branch_count'] ?? null)) {
            $lead->branch_count = $extraction['branch_count'];
            $applied[] = 'branch_count';
        }

        if (in_array('decision_maker_status', $fields, true) && is_string($extraction['decision_maker_status'] ?? null)) {
            $status = DecisionMakerStatus::tryFrom($extraction['decision_maker_status']);

            if ($status !== null) {
                $lead->decision_maker_status = $status;
                $applied[] = 'decision_maker_status';
            }
        }

        $followUp = array_values(array_intersect($fields, ['next_action', 'next_follow_up_on']));

        if ($applied !== []) {
            $lead->save();
        }

        if ($followUp !== []) {
            $action = in_array('next_action', $followUp, true) && is_string($extraction['next_action'] ?? null)
                ? $extraction['next_action']
                : $lead->next_action;
            $date = in_array('next_follow_up_on', $followUp, true) && is_string($extraction['next_follow_up_on'] ?? null)
                ? $extraction['next_follow_up_on']
                : $lead->next_follow_up_on?->toDateString();

            if ($action !== $lead->next_action || $date !== $lead->next_follow_up_on?->toDateString()) {
                $this->followUps->handle($lead, is_string($action) ? $action : null, is_string($date) ? $date : null, $actor);
                $applied = [...$applied, ...$followUp];
            }
        }

        if ($applied === []) {
            throw ValidationException::withMessages([
                'fields' => 'Nothing in the analysis matched those fields.',
            ]);
        }

        $this->activity->handle(ActivityEvent::ExtractionApplied, $lead, $lead->contact, $actor, [
            'analysis_id' => $analysis->id,
            'fields' => array_values(array_unique($applied)),
        ]);

        return $lead->refresh();
    }
}
