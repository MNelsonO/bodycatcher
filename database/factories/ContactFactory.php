<?php

namespace Database\Factories;

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\ContactPhone;
use App\Contacts\PreferredChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'primary_phone' => '+2547'.fake()->unique()->numerify('########'),
            'business_name' => fake()->company(),
            'business_type' => 'Laundry',
            'location' => 'Nairobi',
            'email' => fake()->unique()->safeEmail(),
            'preferred_channel' => PreferredChannel::Whatsapp,
            'consent_status' => ConsentStatus::Unknown,
            'opted_out_at' => null,
            'lead_source' => 'referral',
            'merged_into_contact_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contact $contact): void {
            ContactPhone::query()->updateOrCreate(
                ['phone' => $contact->primary_phone],
                [
                    'contact_id' => $contact->id,
                    'is_primary' => true,
                ],
            );
        });
    }
}
