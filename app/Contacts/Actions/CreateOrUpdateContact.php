<?php

namespace App\Contacts\Actions;

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\ContactPhone;
use App\Contacts\ContactWriteResult;
use App\Contacts\PhoneNormalizer;
use App\Contacts\PreferredChannel;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrUpdateContact
{
    public function __construct(
        private PhoneNormalizer $phones,
        private RecordActivity $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?Contact $existing = null): ContactWriteResult
    {
        return DB::transaction(function () use ($data, $actor, $existing): ContactWriteResult {
            $primary = $this->phones->normalize((string) $data['primary_phone']);
            $additional = $this->additionalPhones($data, $primary);
            $numbers = collect([$primary])->merge($additional);

            $matches = ContactPhone::query()
                ->whereIn('phone', $numbers->all())
                ->with('contact.mergedInto')
                ->get()
                ->map(fn (ContactPhone $phone): Contact => $phone->contact->survivor())
                ->unique('id')
                ->values();

            if ($existing !== null) {
                $existing = $existing->survivor();
                $others = $matches->reject(fn (Contact $contact): bool => $contact->id === $existing->id);

                if ($others->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'primary_phone' => 'That phone number already belongs to another contact. Merge the contacts if they are the same person.',
                    ]);
                }

                $this->fill($existing, $data, $primary);
                $existing->save();
                $this->syncPhones($existing, $primary, $additional);

                return new ContactWriteResult($existing->refresh(), false);
            }

            if ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'primary_phone' => 'These phone numbers match more than one contact. Merge those contacts before saving.',
                ]);
            }

            if ($matches->count() === 1) {
                /** @var Contact $contact */
                $contact = $matches->first();
                $this->fill($contact, $data, $primary);
                $contact->save();
                $this->syncPhones($contact, $primary, $additional);

                return new ContactWriteResult($contact->refresh(), true);
            }

            $contact = new Contact;
            $this->fill($contact, $data, $primary);
            $contact->save();
            $this->syncPhones($contact, $primary, $additional);

            $this->activity->handle(
                ActivityEvent::ContactCreated,
                null,
                $contact,
                $actor,
                ['full_name' => $contact->full_name],
            );

            return new ContactWriteResult($contact->refresh(), false);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function additionalPhones(array $data, string $primary): array
    {
        $raw = $data['additional_phones'] ?? [];
        $values = is_array($raw) ? $raw : [];
        $phones = [];

        foreach ($values as $phone) {
            if (! is_string($phone) || trim($phone) === '') {
                continue;
            }

            $normalized = $this->phones->normalize($phone);

            if ($normalized === $primary || in_array($normalized, $phones, true)) {
                continue;
            }

            $phones[] = $normalized;
        }

        return $phones;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Contact $contact, array $data, string $primary): void
    {
        $consent = ConsentStatus::from((string) $data['consent_status']);
        $optedOutAt = $contact->opted_out_at;

        if ($consent === ConsentStatus::OptedOut) {
            $optedOutAt ??= now();
        } else {
            $optedOutAt = null;
        }

        $contact->fill([
            'full_name' => trim((string) $data['full_name']),
            'primary_phone' => $primary,
            'business_name' => $this->nullableString($data['business_name'] ?? null),
            'business_type' => $this->nullableString($data['business_type'] ?? null),
            'location' => $this->nullableString($data['location'] ?? null),
            'email' => $this->nullableString($data['email'] ?? null),
            'preferred_channel' => PreferredChannel::from((string) $data['preferred_channel']),
            'consent_status' => $consent,
            'opted_out_at' => $optedOutAt,
            'lead_source' => $this->nullableString($data['lead_source'] ?? null),
        ]);
    }

    /**
     * @param  list<string>  $additional
     */
    private function syncPhones(Contact $contact, string $primary, array $additional): void
    {
        $keep = collect([$primary])->merge($additional);

        ContactPhone::query()
            ->where('contact_id', $contact->id)
            ->whereNotIn('phone', $keep->all())
            ->delete();

        foreach ($keep as $phone) {
            ContactPhone::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'contact_id' => $contact->id,
                    'is_primary' => $phone === $primary,
                ],
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
