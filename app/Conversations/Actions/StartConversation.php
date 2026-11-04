<?php

namespace App\Conversations\Actions;

use App\Contacts\Actions\CreateOrUpdateContact;
use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\ContactPhone;
use App\Contacts\InvalidPhoneNumber;
use App\Contacts\PhoneNormalizer;
use App\Contacts\PreferredChannel;
use App\Conversations\Conversation;
use App\Leads\Actions\CreateLead;
use App\Leads\Lead;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\Settings\Product;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens (or reopens) a chat by hand: an existing contact, or a new name and
 * number. A typed name never overwrites a contact that already exists.
 */
class StartConversation
{
    public function __construct(
        private PhoneNormalizer $phones,
        private CreateOrUpdateContact $contacts,
        private CreateLead $leads,
    ) {}

    /**
     * @param  array{contact_id?: int|null, full_name?: string|null, phone?: string|null, product_id?: int|null}  $data
     */
    public function handle(array $data, User $actor): Conversation
    {
        return DB::transaction(function () use ($data, $actor): Conversation {
            $contact = $this->contact($data, $actor);
            $lead = $this->lead($contact, $data['product_id'] ?? null, $actor);

            return $lead->conversation ?? Conversation::query()->create([
                'lead_id' => $lead->id,
                'contact_id' => $contact->id,
            ]);
        });
    }

    /**
     * @param  array{contact_id?: int|null, full_name?: string|null, phone?: string|null}  $data
     */
    private function contact(array $data, User $actor): Contact
    {
        if (! empty($data['contact_id'])) {
            return Contact::query()->findOrFail((int) $data['contact_id'])->survivor();
        }

        try {
            $phone = $this->phones->normalize((string) ($data['phone'] ?? ''));
        } catch (InvalidPhoneNumber $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $existing = ContactPhone::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            return $existing->contact->survivor();
        }

        $name = trim((string) ($data['full_name'] ?? ''));

        return $this->contacts->handle([
            'full_name' => $name !== '' ? $name : $phone,
            'primary_phone' => $phone,
            'preferred_channel' => PreferredChannel::Whatsapp->value,
            'consent_status' => ConsentStatus::Unknown->value,
            'lead_source' => 'manual',
        ], $actor)->contact;
    }

    private function lead(Contact $contact, ?int $productId, User $actor): Lead
    {
        $open = $contact->leads()
            ->open()
            ->when($productId !== null, fn ($query) => $query->where('product_id', $productId))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $pipeline = Pipeline::query()->whereNull('archived_at')->orderByDesc('is_default')->orderBy('id')->first();
        $product = $productId !== null
            ? Product::query()->find($productId)
            : $this->defaultProduct();

        if ($pipeline === null || $product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Add an active product and pipeline before starting a chat.',
            ]);
        }

        return $this->leads->handle([
            'contact_id' => $contact->id,
            'product_id' => $product->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'manual',
        ], $actor);
    }

    private function defaultProduct(): ?Product
    {
        $chosen = DeskSetting::current()->inbound_product_id;

        $query = Product::query()->where('is_active', true)->whereNull('archived_at');

        if ($chosen !== null) {
            $product = (clone $query)->whereKey($chosen)->first();

            if ($product !== null) {
                return $product;
            }
        }

        return $query->orderBy('id')->first();
    }
}
