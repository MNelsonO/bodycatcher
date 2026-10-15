<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in([
                'business_problem',
                'current_solution',
                'branch_count',
                'budget_note',
                'decision_maker_status',
                'purchase_timeline_note',
                'next_action',
                'next_follow_up_on',
            ])],
        ];
    }
}
