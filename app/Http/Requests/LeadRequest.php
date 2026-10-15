<?php

namespace App\Http\Requests;

use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        if ($lead instanceof Lead) {
            return $this->user()?->can('update', $lead) ?? false;
        }

        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('lead') === null;

        return [
            'contact_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('contacts', 'id')->whereNull('merged_into_contact_id')],
            'product_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:products,id'],
            'pipeline_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:pipelines,id'],
            'stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'source' => ['nullable', 'string', 'max:255'],
            'business_problem' => ['nullable', 'string', 'max:5000'],
            'current_solution' => ['nullable', 'string', 'max:5000'],
            'branch_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'budget_note' => ['nullable', 'string', 'max:5000'],
            'decision_maker_status' => ['nullable', Rule::enum(DecisionMakerStatus::class)],
            'purchase_timeline_note' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_on' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
