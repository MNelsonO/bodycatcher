<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoneyAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:120'],
            'account_type' => ['required', Rule::in(['bank', 'mobile', 'cash', 'other'])],
            'account_number' => ['nullable', 'string', 'max:80'],
            'currency' => ['required', 'string', 'size:3'],
            'balance' => ['required', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
