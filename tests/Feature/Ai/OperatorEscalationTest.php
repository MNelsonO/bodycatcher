<?php

use App\Ai\FakeLlmClient;
use App\Ai\Jobs\RunConditionalAutoReply;
use App\Ai\KnowledgeArticle;
use App\Ai\OperatorEscalation;
use App\Ai\Services\InboundAssistantRouter;
use App\Calendar\CalendarActivity;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Mail\OperatorEscalationMail;
use App\Models\User;
use App\Security\Role;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('escalates a tricky live reply, pauses automation, emails, and calendars', function () {
    Mail::fake();

    $owner = User::factory()->create([
        'role' => Role::Owner->value,
        'email' => 'nelson@kemet.test',
    ]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $settings = DeskSetting::current();
    $settings->live_assistant_enabled = true;
    $settings->save();

    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
        'ai.provider' => 'openai',
    ]);

    app(FakeLlmClient::class)->push(
        "ESCALATE: Customer wants a custom contract discount I cannot invent.\nSUGGESTED_REPLY: Let me confirm with the team and get back to you.\nMEMORY: Needs human pricing approval.",
    );

    app(InboundAssistantRouter::class)->handle($lead->fresh(), 'Give me 40% off and a custom SLA or we walk.');

    $lead->refresh();
    $escalation = OperatorEscalation::query()->where('lead_id', $lead->id)->first();

    expect($escalation)->not->toBeNull()
        ->and($escalation?->status)->toBe('open')
        ->and($lead->needs_personal_attention)->toBeTrue()
        ->and($lead->automation_paused)->toBeTrue()
        ->and(Message::query()->where('origin', MessageOrigin::Automatic->value)->count())->toBe(0)
        ->and(CalendarActivity::query()->where('lead_id', $lead->id)->where('activity_type', 'escalation')->exists())->toBeTrue();

    Mail::assertSent(OperatorEscalationMail::class, function (OperatorEscalationMail $mail) use ($owner): bool {
        return $mail->hasTo($owner->email);
    });
});

it('learns from an operator correction and resumes automation', function () {
    Mail::fake();
    $owner = User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $escalation = OperatorEscalation::query()->create([
        'lead_id' => $lead->id,
        'status' => 'open',
        'source' => 'live_assistant',
        'reason' => 'Unclear pricing ask',
        'inbound_snippet' => 'What is your best price?',
        'suggested_reply' => 'I will confirm pricing with the team.',
    ]);

    $lead->update([
        'needs_personal_attention' => true,
        'automation_paused' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('leads.escalations.resolve', [$lead, $escalation]), [
            'correction' => 'Never invent discounts. Offer a call with Nelson for pricing.',
            'send_reply' => '',
            'resume_automation' => '1',
        ])
        ->assertRedirect(route('leads.show', $lead));

    $lead->refresh();
    $escalation->refresh();

    expect($escalation->status)->toBe('resolved')
        ->and($escalation->operator_correction)->toContain('Never invent discounts')
        ->and($lead->needs_personal_attention)->toBeFalse()
        ->and($lead->automation_paused)->toBeFalse()
        ->and(KnowledgeArticle::query()->where('source', 'operator_correction')->where('external_id', 'escalation-'.$escalation->id)->exists())->toBeTrue()
        ->and($lead->conversation?->fresh()?->memory_summary)->toContain('Operator correction');
});

it('does not auto-reply while an escalation pause is active', function () {
    Queue::fake([RunConditionalAutoReply::class]);
    $lead = whatsappOpenLead();
    $lead->update([
        'needs_personal_attention' => true,
        'automation_paused' => true,
    ]);

    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
    ]);

    $settings = DeskSetting::current();
    $settings->live_assistant_enabled = true;
    $settings->save();

    app(InboundAssistantRouter::class)->handle($lead->fresh(), 'Hello again');

    expect(app(FakeLlmClient::class)->calls)->toBe([]);
});
