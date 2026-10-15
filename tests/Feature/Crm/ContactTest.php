<?php

use App\Contacts\Contact;
use App\Contacts\ContactMerge;
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
