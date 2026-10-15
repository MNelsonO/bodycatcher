<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;

class FollowUpRequest extends FormRequest
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
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_follow_up_on' => ['nullable', 'date'],
        ];
    }
}
