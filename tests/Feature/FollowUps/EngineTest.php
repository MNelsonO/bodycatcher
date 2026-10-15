<?php

use App\Contacts\ConsentStatus;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\FollowUps\AutomationRule;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\FollowUps\FollowUpTrigger;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\Models\User;
use App\WhatsApp\FakeWhatsAppClient;
use App\WhatsApp\WhatsAppSendResult;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Inertia\Testing\AssertableInertia as Assert;

it('sends one no-response follow-up and does not send it again', function () {
    $lead = followUpReadyLead();
    $rule = followUpRule([
        'body' => 'Hi {contact}, checking on {business} about {action}.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $client = app(FakeWhatsAppClient::class);
    $task = FollowUpTask::query()->firstOrFail();
    $sent = Message::query()->where('origin', MessageOrigin::Automatic)->firstOrFail();

    expect($client->sends)->toBe(1)
        ->and($client->calls[0]['body'])->toBe('Hi Amina Wanjiku, checking on Westlands Wash about book a visit.')
        ->and($client->calls[0]['to'])->toBe('+254722100201')
        ->and($client->calls[0]['idempotency_key'])->toBe('follow-up-task-'.$task->id)
        ->and($task->status)->toBe(FollowUpTaskStatus::Sent)
        ->and($task->idempotency_key)->toBe('no-response:'.$rule->id.':'.$lead->id.':'.followUpLastOutboundId($lead))
        ->and($sent->status)->toBe(MessageStatus::Sent);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect($client->sends)->toBe(1)
        ->and(Message::query()->where('origin', MessageOrigin::Automatic)->count())->toBe(1)
        ->and(FollowUpTask::query()->where('status', FollowUpTaskStatus::Sent)->count())->toBe(1);
});

it('cancels a pending no-response task when the contact replies on WhatsApp', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(3));
    followUpOutbound($lead, now()->subHour());
    followUpRule(['delay_hours' => 48]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();

    expect($task->status)->toBe(FollowUpTaskStatus::Pending)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Saturday still works.', 'wamid.reply.follow-up'))
        ->assertOk();

    $task->refresh();

    expect($task->status)->toBe(FollowUpTaskStatus::Cancelled)
        ->and($task->failure_reason)->toBe('The contact replied, so this no-response follow-up was cancelled.');

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and(FollowUpTask::query()->where('status', FollowUpTaskStatus::Pending)->count())->toBe(0);
});

it('refuses a due follow-up when the contact has opted out', function () {
    $lead = followUpReadyLead();
    $lead->contact->update([
        'consent_status' => ConsentStatus::OptedOut,
        'opted_out_at' => now(),
    ]);
    followUpRule();

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and($task->status)->toBe(FollowUpTaskStatus::Refused)
        ->and($task->failure_reason)->toContain('opted out')
        ->and(Message::query()->where('status', MessageStatus::Refused)->count())->toBe(1);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and(FollowUpTask::query()->where('status', FollowUpTaskStatus::Refused)->count())->toBe(1);
});

it('holds a paused lead and sends after automation resumes', function () {
    $lead = followUpReadyLead();
    $lead->automation_paused = true;
    $lead->save();
    followUpRule();

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and($task->status)->toBe(FollowUpTaskStatus::Pending)
        ->and($task->held_reason)->toBe('Automatic sends are paused on this lead.');

    $lead->automation_paused = false;
    $lead->save();
    $this->artisan('follow-ups:run')->assertSuccessful();

    $task->refresh();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($task->status)->toBe(FollowUpTaskStatus::Sent)
        ->and($task->held_reason)->toBeNull();
});

it('schedules a follow-up date at Nairobi time and waits until that instant', function () {
    $this->travelTo(Carbon::parse('2026-10-15 05:59:00', 'UTC'));

    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHour());
    $lead->update(['next_follow_up_on' => '2026-10-15']);
    $rule = followUpRule([
        'trigger' => FollowUpTrigger::FollowUpDue,
        'action' => FollowUpAction::SendWhatsApp,
        'delay_hours' => null,
        'local_time' => '09:00',
        'body' => 'Calling about the Westlands shop.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();

    expect($task->status)->toBe(FollowUpTaskStatus::Pending)
        ->and($task->cancel_on_reply)->toBeFalse()
        ->and($task->due_at->copy()->utc()->format('Y-m-d H:i:s'))->toBe('2026-10-15 06:00:00')
        ->and($task->idempotency_key)->toBe('follow-up-due:'.$rule->id.':'.$lead->id.':2026-10-15')
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $this->travelTo(Carbon::parse('2026-10-15 06:00:00', 'UTC'));
    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($task->fresh()?->status)->toBe(FollowUpTaskStatus::Sent);
});

it('keeps a desk task pending at the Nairobi follow-up time', function () {
    $this->travelTo(Carbon::parse('2026-10-15 06:00:00', 'UTC'));

    $lead = whatsappOpenLead();
    $lead->update(['next_follow_up_on' => '2026-10-15']);
    followUpRule([
        'trigger' => FollowUpTrigger::FollowUpDue,
        'action' => FollowUpAction::OperatorTask,
        'delay_hours' => null,
        'local_time' => '09:00',
        'body' => 'Call the Westlands shop.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();

    expect($task->status)->toBe(FollowUpTaskStatus::Pending)
        ->and($task->due_at->copy()->utc()->format('Y-m-d H:i:s'))->toBe('2026-10-15 06:00:00')
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $this->actingAs(User::query()->firstOrFail())
        ->post(route('follow-ups.complete', $task))
        ->assertRedirect();

    expect($task->fresh()?->status)->toBe(FollowUpTaskStatus::Done)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);
});

it('replaces the pending task when the follow-up date changes', function () {
    $lead = whatsappOpenLead();
    $lead->update(['next_follow_up_on' => '2026-10-16']);
    $rule = followUpRule([
        'trigger' => FollowUpTrigger::FollowUpDue,
        'action' => FollowUpAction::OperatorTask,
        'delay_hours' => null,
        'local_time' => '09:00',
        'body' => 'Call the shop.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $first = FollowUpTask::query()->firstOrFail();

    expect($first->idempotency_key)->toBe('follow-up-due:'.$rule->id.':'.$lead->id.':2026-10-16')
        ->and($first->status)->toBe(FollowUpTaskStatus::Pending);

    $lead->update(['next_follow_up_on' => '2026-10-20']);
    $this->artisan('follow-ups:run')->assertSuccessful();

    $second = FollowUpTask::query()->where('status', FollowUpTaskStatus::Pending)->firstOrFail();

    expect($first->fresh()?->status)->toBe(FollowUpTaskStatus::Cancelled)
        ->and($first->fresh()?->failure_reason)->toBe('A newer follow-up replaced this task.')
        ->and($second->idempotency_key)->toBe('follow-up-due:'.$rule->id.':'.$lead->id.':2026-10-20')
        ->and(FollowUpTask::query()->count())->toBe(2);
});

it('lets an owner save a rule and refuses a sales user', function () {
    $sales = crmSalesUser();
    $payload = [
        'name' => 'Quiet hours',
        'trigger' => 'no_response',
        'action' => 'operator_task',
        'delay_hours' => 4,
        'local_time' => '',
        'body' => 'Call them back.',
        'product_id' => 'none',
        'is_active' => '1',
    ];

    $this->actingAs($sales)->post(route('follow-ups.store'), $payload)->assertForbidden();
    expect(AutomationRule::query()->count())->toBe(0);

    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('follow-ups.store'), $payload)
        ->assertRedirect(route('follow-ups.index'));

    $rule = AutomationRule::query()->firstOrFail();

    expect($rule->name)->toBe('Quiet hours')
        ->and($rule->trigger)->toBe(FollowUpTrigger::NoResponse)
        ->and($rule->action)->toBe(FollowUpAction::OperatorTask)
        ->and($rule->delay_hours)->toBe(4)
        ->and($rule->local_time)->toBeNull()
        ->and($rule->is_active)->toBeTrue();

    $this->actingAs($sales)
        ->put(route('follow-ups.update', $rule), $payload)
        ->assertForbidden();

    $this->actingAs($sales)
        ->get(route('follow-ups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('follow-ups/index')
            ->where('can_manage', false)
            ->has('rules', 1)
        );

    $this->actingAs($owner)
        ->get(route('follow-ups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage', true)
            ->where('rules.0.name', 'Quiet hours')
        );
});

it('sends a stage-delay follow-up from the hours stored on the stage', function () {
    $lead = whatsappOpenLead();
    $lead->currentStage->update(['default_follow_up_delay_hours' => 1]);
    $history = LeadStageHistory::query()->where('lead_id', $lead->id)->firstOrFail();
    $history->created_at = now()->subHours(2);
    $history->save();
    whatsappInbound($lead, now()->subHours(3));
    followUpRule([
        'trigger' => FollowUpTrigger::StageDelay,
        'delay_hours' => null,
        'body' => 'Still in the new-lead stage.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();
    $rule = AutomationRule::query()->firstOrFail();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($task->status)->toBe(FollowUpTaskStatus::Sent)
        ->and($task->cancel_on_reply)->toBeTrue()
        ->and($task->idempotency_key)->toBe('stage-delay:'.$rule->id.':'.$lead->id.':'.$history->id);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('skips a stage that has no delay stored', function () {
    $lead = whatsappOpenLead();
    $lead->currentStage->update(['default_follow_up_delay_hours' => null]);
    whatsappInbound($lead, now()->subHour());
    followUpRule([
        'trigger' => FollowUpTrigger::StageDelay,
        'delay_hours' => 4,
        'body' => 'This number on the rule is ignored.',
    ]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(FollowUpTask::query()->count())->toBe(0)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);
});

it('cancels pending tasks when the rule is turned off', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(3));
    followUpOutbound($lead, now()->subHour());
    $rule = followUpRule(['delay_hours' => 48]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();
    expect($task->status)->toBe(FollowUpTaskStatus::Pending);

    $rule->is_active = false;
    $rule->save();
    $this->artisan('follow-ups:run')->assertSuccessful();

    expect($task->fresh()?->status)->toBe(FollowUpTaskStatus::Cancelled)
        ->and($task->fresh()?->failure_reason)->toBe('The automation rule is inactive.')
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);
});

it('cancels pending tasks when the lead is closed', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(3));
    followUpOutbound($lead, now()->subHour());
    followUpRule(['delay_hours' => 48]);

    $this->artisan('follow-ups:run')->assertSuccessful();

    $lead->closed_at = now();
    $lead->save();
    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(FollowUpTask::query()->first()?->status)->toBe(FollowUpTaskStatus::Cancelled)
        ->and(FollowUpTask::query()->first()?->failure_reason)->toBe('The lead is closed.');
});

it('does not resend a follow-up that timed out or failed', function () {
    $lead = followUpReadyLead();
    followUpRule();
    app(FakeWhatsAppClient::class)->push(WhatsAppSendResult::unknown(['error' => 'cURL error 28: Operation timed out']));

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(FollowUpTask::query()->first()?->status)->toBe(FollowUpTaskStatus::Sent)
        ->and(Message::query()->where('origin', MessageOrigin::Automatic)->first()?->status)->toBe(MessageStatus::Unknown)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1);

    FollowUpTask::query()->delete();
    Message::query()->where('origin', MessageOrigin::Automatic)->delete();
    app(FakeWhatsAppClient::class)->push(WhatsAppSendResult::failed(['error' => 'rejected']));

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(FollowUpTask::query()->first()?->status)->toBe(FollowUpTaskStatus::Failed)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(2);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(2)
        ->and(FollowUpTask::query()->where('status', FollowUpTaskStatus::Failed)->count())->toBe(1);
});

it('shows follow-up tasks on the lead', function () {
    $lead = whatsappOpenLead();
    $lead->update(['next_follow_up_on' => '2026-10-20']);
    followUpRule([
        'name' => 'Call back',
        'trigger' => FollowUpTrigger::FollowUpDue,
        'action' => FollowUpAction::OperatorTask,
        'delay_hours' => null,
        'local_time' => '09:00',
        'body' => 'Call the shop.',
    ]);
    $this->artisan('follow-ups:run')->assertSuccessful();

    $this->actingAs(User::query()->firstOrFail())
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('lead.follow_ups', 1)
            ->where('lead.follow_ups.0.rule', 'Call back')
            ->where('lead.follow_ups.0.action', 'operator_task')
            ->where('lead.follow_ups.0.status', 'pending')
        );
});

it('registers the follow-up runner every minute', function () {
    $event = collect(Schedule::events())->first(
        fn (Event $event): bool => str_contains((string) $event->command, 'follow-ups:run'),
    );

    expect($event)->toBeInstanceOf(Event::class);

    if (! $event instanceof Event) {
        return;
    }

    expect($event->expression)->toBe('* * * * *');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function followUpRule(array $overrides = []): AutomationRule
{
    return AutomationRule::query()->create(array_merge([
        'name' => 'No reply',
        'trigger' => FollowUpTrigger::NoResponse,
        'action' => FollowUpAction::SendWhatsApp,
        'delay_hours' => 1,
        'local_time' => null,
        'body' => 'Hi {contact}, still looking?',
        'product_id' => null,
        'is_active' => true,
    ], $overrides));
}

function followUpOutbound(Lead $lead, CarbonInterface $sentAt): Message
{
    $conversation = $lead->conversation()->firstOrFail();

    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Outbound,
        'channel' => MessageChannel::WhatsApp,
        'origin' => MessageOrigin::Manual,
        'status' => MessageStatus::Sent,
        'body' => 'Saturday works for a visit.',
        'sent_at' => $sentAt,
        'provider_message_id' => 'wamid.out.'.uniqid(),
        'retryable' => false,
        'send_attempts' => 1,
    ]);
}

function followUpReadyLead(): Lead
{
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(3));
    followUpOutbound($lead, now()->subHours(2));
    $lead->contact->update(['business_name' => 'Westlands Wash']);
    $lead->update(['next_action' => 'book a visit']);

    return $lead->fresh() ?? $lead;
}

function followUpLastOutboundId(Lead $lead): int
{
    return (int) Message::query()
        ->where('conversation_id', $lead->conversation()->firstOrFail()->id)
        ->where('direction', MessageDirection::Outbound)
        ->where('origin', MessageOrigin::Manual)
        ->value('id');
}
