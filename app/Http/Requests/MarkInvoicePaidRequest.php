<?php

namespace App\Http\Requests;

use App\Billing\Invoice;
use App\Billing\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkInvoicePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && ($this->user() !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_on' => ['nullable', 'date'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists(PaymentMethod::class, 'id')->where('is_active', true)],
        ];
    }
}
