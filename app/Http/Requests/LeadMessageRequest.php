<?php

namespace App\Http\Requests;

use App\Conversations\MessageDirection;
use App\Leads\Lead;
use App\Settings\DisplayClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeadMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $sentAt = $this->input('sent_at');

        if (! is_string($sentAt) || trim($sentAt) === '') {
            return;
        }

        $timezone = app(DisplayClock::class)->timezone();

        $this->merge([
            'sent_at' => Carbon::parse($sentAt, $timezone)->utc()->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(MessageDirection::class)],
            'body' => ['required', 'string', 'max:5000'],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
