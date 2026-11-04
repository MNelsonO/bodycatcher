<?php

use App\Ai\FakeLlmClient;
use App\Ai\SalesPlaybook;
use App\Conversations\Message;
use App\Settings\Product;

it('builds a playbook that points to kemettech.com and the lead\'s product page', function () {
    $lead = whatsappOpenLead();
    $lead->product->forceFill(['name' => 'Kemet Laundry OS'])->save();

    $block = app(SalesPlaybook::class)->block($lead->fresh());

    expect($block)->toContain('https://kemettech.com')
        ->toContain('https://kemettech.com/products/laundry')
        ->toContain('Mirror their words')
        ->toContain('Published offers');
});

it('falls back to the website when no product page matches', function () {
    $product = Product::factory()->create(['name' => 'Custom Integration', 'slug' => 'custom-integration']);

    expect(app(SalesPlaybook::class)->productFor($product))->toBeNull();
});

it('sends the playbook and a labelled two-sided transcript to the model when reading a lead', function () {
    config(['ai.api_key' => 'k', 'ai.base_url' => 'https://example.test/v1', 'ai.model' => 'm']);
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subMinutes(10));
    Message::query()->create([
        'conversation_id' => $lead->conversation()->firstOrFail()->id,
        'direction' => 'outbound', 'channel' => 'whatsapp', 'origin' => 'manual', 'status' => 'sent',
        'body' => 'Yes, Saturday at 10am works.', 'sent_at' => now()->subMinutes(5),
    ]);

    $fake = app(FakeLlmClient::class);
    $fake->push(json_encode(['summary' => 'Wants a visit. Next best action: confirm Saturday.', 'score' => 60, 'score_reasons' => ['engaged'], 'extraction' => [], 'suggested_stage_id' => null, 'reply_draft' => null]));
    $this->post(route('leads.analyses.store', $lead))->assertRedirect();

    $call = $fake->calls[0] ?? ['system' => '', 'user' => ''];

    expect($call['system'])->toContain('KEMET SALES PLAYBOOK')->toContain('Next best action')
        ->and($call['user'])->toContain('Client: Can you visit the shop on Saturday?')
        ->toContain('Us: Yes, Saturday at 10am works.');
});
