<?php

namespace App\Http\Requests;

use App\WhatsApp\DeskSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WhatsAppSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $productId = $this->input('inbound_product_id');

        if ($productId === '' || $productId === 'none') {
            $this->merge(['inbound_product_id' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', DeskSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'automatic_sends_paused' => ['required', 'boolean'],
            'inbound_product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
        ];
    }
}
