<?php

use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Services\MessagingGuard;
use App\WhatsApp\Services\SendSafety;

beforeEach(function () {
    config([
        'whatsapp.driver' => 'evolution',
        'whatsapp.evolution.api_key' => 'k',
        'whatsapp.evolution.base_url' => 'http://127.0.0.1:8080',
    ]);
});

function automaticSent(Lead $lead, string $body, $at): void
{
    Message::query()->create([
        'conversation_id' => $lead->conversation()->firstOrFail()->id,
        'direction' => 'outbound', 'channel' => 'whatsapp', 'origin' => 'automatic', 'status' => 'sent',
        'body' => $body, 'sent_at' => $at,
    ]);
}

it('never sends an automatic message to someone who has not written first', function () {
    $lead = whatsappOpenLead();

    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Automatic, 'Hi'))->toContain('written to you first');

    whatsappInbound($lead);
    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Automatic, 'Hi'))->toBeNull();
});

it('spaces automatic sends out and caps them per hour', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    automaticSent($lead, 'one', now()->subSeconds(5));
    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Automatic, 'two'))->toContain('between automatic messages');

    config(['whatsapp.safety.auto_min_gap_seconds' => 0, 'whatsapp.safety.auto_per_chat_gap_seconds' => 0, 'whatsapp.safety.auto_per_hour' => 3]);
    automaticSent($lead, 'a', now()->subMinutes(10));
    automaticSent($lead, 'b', now()->subMinutes(20));
    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Automatic, 'c'))->toContain('last hour');
});

it('refuses the same automatic text to many chats in an hour', function () {
    config(['whatsapp.safety.auto_min_gap_seconds' => 0, 'whatsapp.safety.auto_same_text_chats_per_hour' => 1]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    automaticSent($lead, 'Promo: 50% off', now()->subMinutes(30));

    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Automatic, 'Promo: 50% off'))->toContain('same text');
});

it('stops a manual burst and is off for the official Meta API', function () {
    config(['whatsapp.safety.manual_burst_per_10_minutes' => 2]);
    $lead = whatsappOpenLead();
    $user = User::query()->first();

    foreach (range(1, 2) as $i) {
        Message::query()->create([
            'conversation_id' => $lead->conversation()->firstOrFail()->id,
            'direction' => 'outbound', 'channel' => 'whatsapp', 'origin' => 'manual', 'status' => 'sent',
            'body' => "m{$i}", 'sent_at' => now()->subMinute(), 'logged_by_user_id' => $user->id,
        ]);
    }

    expect(app(MessagingGuard::class)->refusal($lead, MessageOrigin::Manual, 'next'))->toContain('last 10 minutes');

    config(['whatsapp.driver' => 'meta']);
    expect(app(SendSafety::class)->reason($lead, MessageOrigin::Manual, 'next'))->toBeNull();
});
