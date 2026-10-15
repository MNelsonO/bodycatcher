<?php

namespace App\Http\Requests;

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\PreferredChannel;
use App\Contacts\Rules\KenyanPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        if ($contact instanceof Contact) {
            return $this->user()?->can('update', $contact) ?? false;
        }

        return $this->user()?->can('create', Contact::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('additional_phones', []);

        if (is_string($raw)) {
            $lines = preg_split('/[\r\n,;]+/', $raw) ?: [];
            $this->merge([
                'additional_phones' => array_values(array_filter(array_map(
                    fn (string $line): string => trim($line),
                    array_map('strval', $lines),
                ), fn (string $line): bool => $line !== '')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'primary_phone' => ['required', 'string', 'max:40', new KenyanPhone],
            'additional_phones' => ['nullable', 'array'],
            'additional_phones.*' => ['string', 'max:40', new KenyanPhone],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_channel' => ['required', Rule::enum(PreferredChannel::class)],
            'consent_status' => ['required', Rule::enum(ConsentStatus::class)],
            'lead_source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
