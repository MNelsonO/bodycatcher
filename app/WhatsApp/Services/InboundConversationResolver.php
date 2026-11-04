<?php

namespace App\WhatsApp\Services;

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\ContactPhone;
use App\Contacts\PhoneNormalizer;
use App\Contacts\PreferredChannel;
use App\Conversations\Conversation;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use App\WhatsApp\DeskSetting;
use Illuminate\Validation\ValidationException;

class InboundConversationResolver
{
    public function __construct(
        private PhoneNormalizer $phones,
        private RecordActivity $activity,
    ) {}

    public function resolve(string $waId, ?string $profileName): Lead
    {
        $phone = $this->phones->normalize($waId);
        $contact = $this->contact($phone, $profileName);

        $open = $contact->leads()
            ->open()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return $this->openLead($contact);
    }

    private function contact(string $phone, ?string $profileName): Contact
    {
        $existing = ContactPhone::query()->where('phone', $phone)->first();

        $name = trim((string) $profileName);

        if ($existing !== null) {
            $contact = $existing->contact->survivor();
            $this->refreshProfileName($contact, $phone, $name);

            return $contact;
        }

        $contact = Contact::query()->create([
            'full_name' => $name !== '' ? $name : $phone,
            'primary_phone' => $phone,
            'preferred_channel' => PreferredChannel::Whatsapp,
            'consent_status' => ConsentStatus::Unknown,
            'lead_source' => (string) config('whatsapp.inbound_source'),
            'whatsapp_profile_name' => $name !== '' ? $name : null,
        ]);

        ContactPhone::query()->create([
            'contact_id' => $contact->id,
            'phone' => $phone,
            'is_primary' => true,
        ]);

        $this->activity->handle(ActivityEvent::ContactCreated, null, $contact, null, [
            'source' => 'whatsapp',
        ]);

        return $contact;
    }

    /**
     * Keep the WhatsApp display name current. A contact still named after its
     * phone number takes the profile name; a name typed by the desk is kept.
     */
    private function refreshProfileName(Contact $contact, string $phone, string $name): void
    {
        if ($name === '' || $contact->whatsapp_profile_name === $name && $contact->full_name !== $phone) {
            return;
        }

        $contact->whatsapp_profile_name = $name;

        $placeholder = trim($contact->full_name);
        $digits = preg_replace('/\D+/', '', $placeholder) ?? '';

        if ($placeholder === '' || $placeholder === $phone || ($digits !== '' && $digits === ltrim($phone, '+'))) {
            $contact->full_name = $name;
        }

        $contact->save();
    }

    private function openLead(Contact $contact): Lead
    {
        $pipeline = Pipeline::query()
            ->where('is_default', true)
            ->whereNull('archived_at')
            ->first();

        if ($pipeline === null) {
            $pipeline = Pipeline::query()->whereNull('archived_at')->orderBy('id')->first();
        }

        if ($pipeline === null) {
            throw ValidationException::withMessages([
                'pipeline' => 'No pipeline is available for an inbound WhatsApp lead.',
            ]);
        }

        $product = $this->product();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product' => 'No active product is available for an inbound WhatsApp lead.',
            ]);
        }

        $stage = $this->stage($pipeline);
        $source = (string) config('whatsapp.inbound_source');

        $lead = Lead::query()->create([
            'contact_id' => $contact->id,
            'product_id' => $product->id,
            'pipeline_id' => $pipeline->id,
            'current_stage_id' => $stage->id,
            'source' => $source,
            'decision_maker_status' => DecisionMakerStatus::Unknown,
            'closed_at' => null,
        ]);

        Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $contact->id,
        ]);

        LeadStageHistory::query()->create([
            'lead_id' => $lead->id,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'reason' => 'Opened from an inbound WhatsApp message.',
            'actor_user_id' => null,
        ]);

        $this->activity->handle(ActivityEvent::LeadCreated, $lead, $contact, null, [
            'product' => $product->name,
            'stage' => $stage->name,
            'source' => $source,
        ]);

        return $lead->refresh();
    }

    private function product(): ?Product
    {
        $settings = DeskSetting::current();

        if ($settings->inbound_product_id !== null) {
            $chosen = Product::query()
                ->whereKey($settings->inbound_product_id)
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->first();

            if ($chosen !== null) {
                return $chosen;
            }
        }

        return Product::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->first();
    }

    private function stage(Pipeline $pipeline): PipelineStage
    {
        $preferred = (string) config('whatsapp.inbound_stage_name');

        $stage = $pipeline->stages()
            ->where('name', $preferred)
            ->where('kind', StageKind::Active->value)
            ->whereNull('archived_at')
            ->first();

        if ($stage !== null) {
            return $stage;
        }

        $fallback = $pipeline->stages()
            ->where('kind', StageKind::Active->value)
            ->whereNull('archived_at')
            ->orderBy('position')
            ->first();

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'pipeline' => 'The default pipeline has no active stage for an inbound lead.',
            ]);
        }

        return $fallback;
    }
}
