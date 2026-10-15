<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoneyDebtRequest extends FormRequest
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
            'direction' => ['required', Rule::in(['payable', 'receivable'])],
            'party_name' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'due_on' => ['nullable', 'date'],
            'money_account_id' => ['nullable', 'integer', 'exists:money_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
