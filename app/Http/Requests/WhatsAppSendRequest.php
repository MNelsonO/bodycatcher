<?php

namespace App\Http\Requests;

use App\Leads\Lead;
use Illuminate\Foundation\Http\FormRequest;

class WhatsAppSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4096'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
