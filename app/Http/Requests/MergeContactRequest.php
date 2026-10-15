<?php

namespace App\Http\Requests;

use App\Contacts\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact && ($this->user()?->can('merge', $contact) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contact = $this->route('contact');
        $id = $contact instanceof Contact ? $contact->id : 0;

        return [
            'merged_contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->whereNull('merged_into_contact_id'),
                Rule::notIn([$id]),
            ],
        ];
    }
}
