<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'activity_type' => ['required', Rule::in(['task', 'visit', 'call', 'delivery', 'other'])],
            'color' => ['required', Rule::in(['blue', 'white'])],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ];
    }
}
