<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'address_line1' => ['nullable', 'string', 'max:160'],
            'address_line2' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
