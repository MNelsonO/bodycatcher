<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceGenerationRequest extends FormRequest
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
            'prompt' => ['required_without:from_generation_id', 'nullable', 'string', 'max:4000'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'from_generation_id' => ['nullable', 'integer', 'exists:invoice_generations,id'],
        ];
    }
}
