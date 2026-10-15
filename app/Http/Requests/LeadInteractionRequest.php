<?php

namespace App\Http\Requests;

use App\Leads\InteractionDirection;
use App\Leads\InteractionType;
use App\Leads\Lead;
use App\Settings\DisplayClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeadInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $occurredAt = $this->input('occurred_at');

        if (! is_string($occurredAt) || trim($occurredAt) === '') {
            return;
        }

        $timezone = app(DisplayClock::class)->timezone();

        $this->merge([
            'occurred_at' => Carbon::parse($occurredAt, $timezone)->utc()->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InteractionType::class)],
            'direction' => ['nullable', Rule::enum(InteractionDirection::class)],
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'outcome' => ['nullable', 'string', 'max:255'],
        ];
    }
}
