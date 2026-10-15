<?php

use App\Contacts\Contact;
use App\Contacts\ContactMerge;
use App\Contacts\ContactNote;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Lead;

function crmContactPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Amina Wanjiku',
        'primary_phone' => '0712 345 678',
        'additional_phones' => '',
        'business_name' => 'FreshFold Laundry',
        'business_type' => 'Laundry',
        'location' => 'Westlands, Nairobi',
        'country_code' => 'KE',
        'conversation_area' => 'Nairobi Facebook laundry ads',
        'email' => 'amina@freshfold.co.ke',
        'preferred_channel' => 'whatsapp',
        'consent_status' => 'opted_in',
        'lead_source' => 'Referral',
    ], $overrides);
}

it('normalizes a Kenyan phone and does not insert a duplicate', function () {
    $this->actingAs(crmSalesUser());

    $this->post(route('contacts.store'), crmContactPayload())->assertRedirect();
    $this->post(route('contacts.store'), crmContactPayload([
        'primary_phone' => '+254712345678',
        'full_name' => 'Amina W. Updated',
    ]))->assertRedirect();

    expect(Contact::query()->count())->toBe(1);
    expect(Contact::query()->first()?->primary_phone)->toBe('+254712345678');
    expect(Contact::query()->first()?->full_name)->toBe('Amina W. Updated');
});

it('does not merge two contacts who share a name but not a phone', function () {
    $this->actingAs(crmSalesUser());

    $this->post(route('contacts.store'), crmContactPayload([
        'full_name' => 'Grace Achieng',
        'primary_phone' => '0711111111',
        'business_name' => 'FreshFold Laundry',
    ]))->assertRedirect();

    $this->post(route('contacts.store'), crmContactPayload([
        'full_name' => 'Grace Achieng',
        'primary_phone' => '0722222222',
        'business_name' => 'FreshFold Laundry',
        'email' => 'grace.eldoret@freshfold.co.ke',
        'location' => 'Eldoret',
    ]))->assertRedirect();

    expect(Contact::query()->count())->toBe(2);
    expect(ContactMerge::query()->count())->toBe(0);
});

it('keeps leads and messages on the surviving contact after a manual merge', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $survivor = Contact::factory()->create(['full_name' => 'Amina Wanjiku', 'primary_phone' => '+254722100201']);
    $merged = Contact::factory()->create(['full_name' => 'Amina W.', 'primary_phone' => '+254733200302']);

    $lead = Lead::factory()->create([
        'contact_id' => $merged->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    $conversation = Conversation::query()->create([
        'lead_id' => $lead->id,
        'contact_id' => $merged->id,
    ]);

    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Inbound,
        'body' => 'Please call the Westlands shop.',
        'sent_at' => now(),
        'logged_by_user_id' => $user->id,
    ]);

    $this->post(route('contacts.merge', $survivor), [
        'merged_contact_id' => $merged->id,
    ])->assertRedirect(route('contacts.show', $survivor));

    expect($lead->fresh()?->contact_id)->toBe($survivor->id);
    expect($conversation->fresh()?->contact_id)->toBe($survivor->id);
    expect(Message::query()->find($message->id)?->body)->toBe('Please call the Westlands shop.');
    expect(Contact::query()->find($merged->id)?->merged_into_contact_id)->toBe($survivor->id);
    expect(ContactMerge::query()->count())->toBe(1);
});

it('classifies a contact by country and conversation area', function () {
    $this->actingAs(crmSalesUser());

    $this->post(route('contacts.store'), crmContactPayload([
        'full_name' => 'Grace Namukasa',
        'primary_phone' => '+256772100200',
        'country_code' => 'UG',
        'conversation_area' => 'Kampala Facebook laundry demos',
        'location' => 'Kampala',
        'email' => 'grace@kampala-laundry.ug',
    ]))->assertRedirect();

    $contact = Contact::query()->first();

    expect($contact?->country_code)->toBe('UG')
        ->and($contact?->country)->toBe('Uganda')
        ->and($contact?->conversation_area)->toBe('Kampala Facebook laundry demos')
        ->and($contact?->primary_phone)->toBe('+256772100200');
});

it('stores client notes the AI context can read', function () {
    $user = crmSalesUser();
    $this->actingAs($user);

    $contact = Contact::factory()->create([
        'country_code' => 'TZ',
        'country' => 'Tanzania',
        'conversation_area' => 'Dar es Salaam Meta ads',
    ]);

    $this->post(route('contacts.notes.store', $contact), [
        'body' => 'Prefers Swahili openers. Quote in TZS. Came from Dar Meta ads.',
    ])->assertRedirect(route('contacts.show', $contact));

    expect(ContactNote::query()->where('contact_id', $contact->id)->count())->toBe(1);

    $contact->load('notes');
    $block = $contact->aiContextBlock();

    expect($block)->toContain('Tanzania')
        ->and($block)->toContain('Dar es Salaam Meta ads')
        ->and($block)->toContain('Prefers Swahili openers');
});

it('filters contacts by country', function () {
    $this->actingAs(crmSalesUser());

    Contact::factory()->create([
        'full_name' => 'Kenya Client',
        'primary_phone' => '+254711000001',
        'country_code' => 'KE',
        'country' => 'Kenya',
    ]);
    Contact::factory()->create([
        'full_name' => 'Uganda Client',
        'primary_phone' => '+256772000002',
        'country_code' => 'UG',
        'country' => 'Uganda',
    ]);

    $this->get(route('contacts.index', ['country' => 'UG']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contacts/index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.full_name', 'Uganda Client')
            ->where('filters.country', 'UG'));
});

it('accepts international phone numbers without a Kenyan local form', function () {
    $this->actingAs(crmSalesUser());

    $this->post(route('contacts.store'), crmContactPayload([
        'full_name' => 'Ada Okoro',
        'primary_phone' => '+2348012345678',
        'country_code' => 'NG',
        'conversation_area' => 'Lagos Instagram laundry ads',
        'location' => 'Lagos',
        'email' => 'ada@lagos-fold.ng',
    ]))->assertRedirect();

    expect(Contact::query()->first()?->primary_phone)->toBe('+2348012345678')
        ->and(Contact::query()->first()?->country)->toBe('Nigeria');
});
