<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;

class ResolveOperatorEscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('send_reply') === '') {
            $this->merge(['send_reply' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'correction' => ['required', 'string', 'max:4000'],
            'send_reply' => ['nullable', 'string', 'max:4000'],
            'resume_automation' => ['sometimes', 'boolean'],
        ];
    }
}
