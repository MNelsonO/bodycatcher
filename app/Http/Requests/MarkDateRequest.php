<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'marked_on' => ['required', 'date'],
            'marked_label' => ['nullable', 'string', 'max:120'],
            'add_to_calendar' => ['sometimes', 'boolean'],
        ];
    }
}
