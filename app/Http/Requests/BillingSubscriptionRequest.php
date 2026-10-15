<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('product_id') === '' || $this->input('product_id') === 'none') {
            $this->merge(['product_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'label' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cadence' => ['required', Rule::in(['monthly'])],
            'next_due_on' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'paused', 'ended'])],
            'alert_days_before' => ['required', 'integer', 'min:0', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
