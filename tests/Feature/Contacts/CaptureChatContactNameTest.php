<?php

use App\Contacts\Actions\CaptureChatContactName;
use App\Contacts\Contact;
use App\Leads\Lead;

it('replaces a placeholder contact name when chat says my name is Jane', function () {
    $desk = crmDesk();
    $contact = Contact::factory()->create([
        'full_name' => 'WhatsApp contact',
        'primary_phone' => '+254722100201',
    ]);
    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    $updated = app(CaptureChatContactName::class)->handle(
        $lead,
        'Hi, my name is Jane Wanjiru and I need laundry software',
    );

    expect($updated)->toBeTrue()
        ->and($contact->fresh()?->full_name)->toBe('Jane Wanjiru');
});

it('does not overwrite a desk-saved real name', function () {
    $desk = crmDesk();
    $contact = Contact::factory()->create([
        'full_name' => 'Amina Wanjiku',
    ]);
    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    $updated = app(CaptureChatContactName::class)->handle(
        $lead,
        'My name is Jane',
    );

    expect($updated)->toBeFalse()
        ->and($contact->fresh()?->full_name)->toBe('Amina Wanjiku');
});

it('applies CONTACT_NAME from the live assistant line', function () {
    $desk = crmDesk();
    $contact = Contact::factory()->create([
        'full_name' => 'WhatsApp lead',
    ]);
    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    $updated = app(CaptureChatContactName::class)->applyName($lead, 'Brian Otieno');

    expect($updated)->toBeTrue()
        ->and($contact->fresh()?->full_name)->toBe('Brian Otieno');
});
