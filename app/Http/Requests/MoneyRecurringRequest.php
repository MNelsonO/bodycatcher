<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoneyRecurringRequest extends FormRequest
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
            'direction' => ['required', Rule::in(['outflow', 'inflow'])],
            'title' => ['required', 'string', 'max:160'],
            'party_name' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'cadence' => ['required', Rule::in(['monthly', 'weekly', 'yearly'])],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'next_due_on' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'ended'])],
            'money_account_id' => ['nullable', 'integer', 'exists:money_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
