<?php

use App\Ai\KnowledgeArticle;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Models\User;
use App\Security\Role;

it('mines frequent inbound WhatsApp questions into knowledge notes', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();
    $conversation = Conversation::query()->firstOrCreate(
        ['lead_id' => $lead->id],
        ['contact_id' => $lead->contact_id],
    );

    foreach (range(1, 3) as $i) {
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'channel' => MessageChannel::WhatsApp,
            'origin' => MessageOrigin::Inbound,
            'status' => MessageStatus::Received,
            'body' => 'How much is laundry os monthly?',
            'sent_at' => now()->subMinutes($i),
            'provider_message_id' => 'wamid.faq.'.$i,
            'retryable' => false,
            'send_attempts' => 0,
        ]);
    }

    $this->actingAs($owner)
        ->post('/ai/whatsapp-sync')
        ->assertRedirect();

    expect(KnowledgeArticle::query()->where('source', 'whatsapp_inbox')->count())->toBeGreaterThan(0);
});
