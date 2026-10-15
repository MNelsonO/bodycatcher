<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;

class LeadStageRequest extends FormRequest
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
            'stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
            'lost_reason' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
