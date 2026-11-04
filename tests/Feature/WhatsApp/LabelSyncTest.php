<?php

use App\Leads\ActivityLog;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\Settings\DisplayClock;
use App\WhatsApp\Labels\LabelSync;
use App\WhatsApp\Labels\WhatsAppLabel;

beforeEach(function () {
    config([
        'whatsapp.evolution.base_url' => 'http://127.0.0.1:8080',
        'whatsapp.evolution.api_key' => 'k',
        'whatsapp.evolution.webhook_token' => 'secret-token',
    ]);
});

it('stores WhatsApp labels, links them to the contact and sets a follow-up', function () {
    $lead = whatsappOpenLead();

    $this->postJson('/webhooks/evolution/secret-token', ['event' => 'labels.edit', 'data' => ['id' => '7', 'name' => 'Follow up', 'color' => 3, 'deleted' => false]])->assertOk();
    $this->postJson('/webhooks/evolution/secret-token', ['event' => 'labels.association', 'data' => ['type' => 'add', 'chatId' => '254722100201@s.whatsapp.net', 'labelId' => '7']])->assertOk();

    expect(WhatsAppLabel::query()->where('label_id', '7')->value('name'))->toBe('Follow up')
        ->and(DB::table('contact_whatsapp_label')->where('contact_id', $lead->contact_id)->where('label_id', '7')->exists())->toBeTrue()
        ->and($lead->refresh()->next_follow_up_on?->toDateString())->toBe(app(DisplayClock::class)->today())
        ->and(ActivityLog::query()->where('event', 'whatsapp_label_changed')->count())->toBe(1);

    $this->get(route('inbox.show', $lead->conversation))
        ->assertInertia(fn ($page) => $page->where('thread.contact.labels.0.name', 'Follow up'));

    $this->postJson('/webhooks/evolution/secret-token', ['event' => 'labels.association', 'data' => ['type' => 'remove', 'chatId' => '254722100201@s.whatsapp.net', 'labelId' => '7']])->assertOk();
    expect(DB::table('contact_whatsapp_label')->where('contact_id', $lead->contact_id)->exists())->toBeFalse();
});

it('marks the lead won for "Order complete" when the pipeline allows it', function () {
    $lead = whatsappOpenLead();
    $desk = ['won' => PipelineStage::query()->where('kind', 'won')->firstOrFail()];
    PipelineStageTransition::query()->firstOrCreate(['pipeline_id' => $lead->pipeline_id, 'from_stage_id' => $lead->current_stage_id, 'to_stage_id' => $desk['won']->id]);

    $this->postJson('/webhooks/evolution/secret-token', ['event' => 'labels.edit', 'data' => ['id' => '5', 'name' => 'Order complete']])->assertOk();
    $this->postJson('/webhooks/evolution/secret-token', ['event' => 'labels.association', 'data' => ['type' => 'add', 'chatId' => '254722100201@s.whatsapp.net', 'labelId' => '5']])->assertOk();

    expect($lead->refresh()->current_stage_id)->toBe($desk['won']->id);
});

it('does not treat "New customer" as a closed deal', function () {
    $label = new WhatsAppLabel(['label_id' => '1', 'name' => 'New customer']);

    expect(app(LabelSync::class)->actionFor($label))->toBeNull();
});
