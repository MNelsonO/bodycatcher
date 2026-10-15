<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use App\Settings\DisplayClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ScheduleConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $at = $this->input('next_conversation_at');

        if (! is_string($at) || trim($at) === '') {
            $this->merge(['next_conversation_at' => null]);

            return;
        }

        $timezone = app(DisplayClock::class)->timezone();

        $this->merge([
            'next_conversation_at' => Carbon::parse($at, $timezone)->utc()->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'next_conversation_at' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string', 'max:500'],
        ];
    }
}
