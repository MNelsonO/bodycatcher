<?php

use App\FollowUps\FollowUpTask;
use App\WhatsApp\Labels\WhatsAppLabel;
use Inertia\Testing\AssertableInertia as Assert;

function labelled($lead, string $labelId = '7', string $name = 'Follow up'): void
{
    WhatsAppLabel::query()->updateOrCreate(['label_id' => $labelId], ['name' => $name, 'color' => '3']);
    DB::table('contact_whatsapp_label')->insertOrIgnore(['contact_id' => $lead->contact_id, 'label_id' => $labelId, 'created_at' => now(), 'updated_at' => now()]);
}

it('lists labels with their chats and says who is safe to message', function () {
    $lead = whatsappOpenLead();
    labelled($lead);

    $this->get(route('labels.index', ['label' => '7']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('labels/index')
            ->where('labels.0.name', 'Follow up')
            ->where('labels.0.attached', 1)
            ->where('selected.members.0.contact_name', 'Amina Wanjiku')
            ->where('selected.members.0.skip_reason', 'Never wrote to you'));

    whatsappInbound($lead, now()->subDay());

    $this->get(route('labels.index', ['label' => '7']))
        ->assertInertia(fn (Assert $page) => $page->where('selected.members.0.skip_reason', null));
});

it('drafts personal messages from a template and schedules them spaced out', function () {
    $lead = whatsappOpenLead();
    labelled($lead);
    whatsappInbound($lead, now()->subDay());

    $drafts = $this->postJson(route('labels.drafts', '7'), ['template' => 'Hi {name}, want a free demo this week? {link}'])
        ->assertOk()
        ->json('drafts');

    expect($drafts[0]['body'])->toStartWith('Hi Amina, want a free demo this week? https://kemettech.com');

    $this->post(route('labels.send', '7'), ['messages' => [['lead_id' => $lead->id, 'body' => $drafts[0]['body']]]])
        ->assertRedirect(route('labels.index', ['label' => '7']));

    $task = FollowUpTask::query()->firstOrFail();

    expect($task->scheduled_by_user_id)->not->toBeNull()
        ->and($task->due_at->greaterThan(now()))->toBeTrue()
        ->and($task->cancel_on_reply)->toBeTrue();

    // A second run skips the person who already has a message waiting.
    $this->get(route('labels.index', ['label' => '7']))
        ->assertInertia(fn (Assert $page) => $page->where('selected.members.0.skip_reason', 'Already has a message scheduled'));
});

it('refuses to schedule for someone who never wrote', function () {
    $lead = whatsappOpenLead();
    labelled($lead);

    $this->post(route('labels.send', '7'), ['messages' => [['lead_id' => $lead->id, 'body' => 'Hi there']]]);

    expect(FollowUpTask::query()->count())->toBe(0);
});

it('filters the inbox by label', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    labelled($lead);

    $this->get(route('inbox.index', ['label' => '7']))->assertInertia(fn (Assert $page) => $page->has('conversations', 1));
    $this->get(route('inbox.index', ['label' => '99']))->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
});
