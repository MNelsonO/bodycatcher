<?php

use App\Ai\AnalysisDispatcher;
use App\Ai\AnalysisStatus;
use App\Ai\DraftStatus;
use App\Ai\FakeLlmClient;
use App\Ai\KnowledgeArticle;
use App\Ai\LeadAnalysis;
use App\Ai\LlmConfiguration;
use App\Ai\LlmRequestFailed;
use App\Ai\OpenAiCompatibleClient;
use App\Ai\ReplyDraft;
use App\Ai\Services\KnowledgeRetriever;
use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Conversations\Actions\LogMessage;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\FakeWhatsAppClient;
use Illuminate\Support\Facades\Http;

function aiReady(): void
{
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://llm.test/v1',
        'ai.model' => 'desk-model',
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function aiJson(array $overrides = []): string
{
    $payload = array_replace([
        'summary' => 'Amina loses Saturday orders in a counter book.',
        'extraction' => [
            'business_problem' => 'Saturday orders are written in a counter book and lost.',
            'branch_count' => 4,
            'next_action' => 'Visit the shop on Saturday.',
        ],
        'score' => 64,
        'score_reasons' => ['She asked for a visit.', 'The current book is failing.'],
        'suggested_stage_id' => null,
        'stage_reason' => null,
        'reply_draft' => 'Saturday works. I can come by the shop.',
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

/**
 * @return array{desk: array<string, mixed>, user: User, lead: Lead}
 */
function aiLead(string $message = 'The Saturday counter book loses orders.'): array
{
    $desk = crmDesk();
    $user = crmSalesUser();
    $contact = Contact::factory()->create();
    test()->actingAs($user)->post(route('leads.store'), [
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'stage_id' => $desk['stage']->id,
        'source' => 'Referral',
        'business_problem' => 'Counter book loses Saturday orders.',
        'current_solution' => 'Exercise book',
        'branch_count' => 1,
        'budget_note' => 'No figure given.',
        'decision_maker_status' => 'unknown',
        'purchase_timeline_note' => 'Before December.',
        'next_action' => 'Call the shop.',
        'next_follow_up_on' => null,
    ])->assertRedirect();
    $lead = Lead::query()->firstOrFail();
    app(LogMessage::class)->handle($lead, MessageDirection::Inbound, $message, null, $user);

    return [
        'desk' => $desk,
        'user' => $user,
        'lead' => $lead->fresh() ?? $lead,
    ];
}

it('stores an unavailable analysis when the model is not configured', function () {
    config([
        'ai.api_key' => '',
        'ai.base_url' => '',
        'ai.model' => '',
    ]);
    $setup = aiLead();
    $messages = Message::query()->count();

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    $analysis = LeadAnalysis::query()->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(AnalysisStatus::Unavailable)
        ->and($analysis->summary)->toBeNull()
        ->and($analysis->score)->toBeNull()
        ->and(app(FakeLlmClient::class)->calls)->toBe([])
        ->and(Message::query()->count())->toBe($messages);
});

it('stores a summary, an explained score, a draft, and the knowledge it used', function () {
    aiReady();
    $setup = aiLead();
    KnowledgeArticle::query()->create([
        'title' => 'Counter book',
        'body' => 'A Saturday counter book is a paper order pad.',
        'product_id' => $setup['desk']['product']->id,
        'is_active' => true,
    ]);
    $client = app(FakeLlmClient::class);
    $client->push("```json\n".aiJson()."\n```");

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    $analysis = LeadAnalysis::query()->with('draft')->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis->summary)->toBe('Amina loses Saturday orders in a counter book.')
        ->and($analysis->score)->toBe(64)
        ->and($analysis->score_reasons)->toBe(['She asked for a visit.', 'The current book is failing.'])
        ->and($analysis->knowledge_article_ids)->toBe([KnowledgeArticle::query()->firstOrFail()->id])
        ->and($analysis->draft?->original_body)->toBe('Saturday works. I can come by the shop.')
        ->and($client->calls[0]['user'])->toContain('Counter book');
});

it('drops a stage suggestion that is not a permitted move', function () {
    aiReady();
    $setup = aiLead();
    app(FakeLlmClient::class)->push(aiJson([
        'suggested_stage_id' => $setup['desk']['side']->id,
        'stage_reason' => 'Skip ahead.',
    ]));

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    $analysis = LeadAnalysis::query()->first();

    expect($analysis?->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis?->suggested_stage_id)->toBeNull()
        ->and($analysis?->stage_rejected_reason)->toBe('The suggested stage is not a permitted move from the current stage.')
        ->and($setup['lead']->fresh()?->current_stage_id)->toBe($setup['desk']['stage']->id);
});

it('stores a suggested product id in extraction when the product is active', function () {
    aiReady();
    $setup = aiLead();
    $otherId = $setup['desk']['other']->id;
    app(FakeLlmClient::class)->push(aiJson([
        'suggested_product_id' => $otherId,
        'product_reason' => 'They asked about the other catalog line.',
        'extraction' => [
            'business_problem' => 'Needs a different product fit.',
            'suggested_product_id' => $otherId,
            'product_reason' => 'They asked about the other catalog line.',
        ],
    ]));

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    $analysis = LeadAnalysis::query()->first();

    expect($analysis?->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis?->extraction['suggested_product_id'] ?? null)->toBe($otherId)
        ->and($analysis?->extraction['product_reason'] ?? null)->toBe('They asked about the other catalog line.')
        ->and($setup['lead']->fresh()?->product_id)->toBe($setup['desk']['product']->id);
});

it('marks the analysis failed and keeps the message when the model errors', function () {
    aiReady();
    $setup = aiLead();
    app(FakeLlmClient::class)->pushFailure(new RuntimeException('model timed out'));

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    expect(LeadAnalysis::query()->first()?->status)->toBe(AnalysisStatus::Failed)
        ->and(LeadAnalysis::query()->first()?->error)->toBe('model timed out')
        ->and(Message::query()->where('body', 'The Saturday counter book loses orders.')->exists())->toBeTrue();
});

it('applies only the extracted fields the operator selects', function () {
    aiReady();
    $setup = aiLead();
    app(FakeLlmClient::class)->push(aiJson());
    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();
    $analysis = LeadAnalysis::query()->firstOrFail();

    $this->post(route('leads.analyses.apply', [$setup['lead'], $analysis]), [
        'fields' => ['business_problem'],
    ])->assertRedirect();

    $lead = $setup['lead']->fresh();

    expect($lead?->business_problem)->toBe('Saturday orders are written in a counter book and lost.')
        ->and($lead?->branch_count)->toBe(1)
        ->and($lead?->next_action)->toBe('Call the shop.');
});

it('applies a permitted suggested stage through the transition service', function () {
    aiReady();
    $setup = aiLead();
    app(FakeLlmClient::class)->push(aiJson([
        'suggested_stage_id' => $setup['desk']['won']->id,
        'stage_reason' => 'They asked to proceed.',
    ]));
    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();
    $analysis = LeadAnalysis::query()->firstOrFail();

    $this->post(route('leads.analyses.stage', [$setup['lead'], $analysis]))->assertRedirect();

    expect($setup['lead']->fresh()?->current_stage_id)->toBe($setup['desk']['won']->id)
        ->and($setup['lead']->fresh()?->stageHistory()->count())->toBe(2);
});

it('keeps the model wording when a draft is edited and sends only after approval', function () {
    aiReady();
    whatsappConfig();
    $setup = aiLead();
    whatsappInbound($setup['lead']);
    app(FakeLlmClient::class)->push(aiJson());
    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();
    $draft = ReplyDraft::query()->firstOrFail();

    $this->post(route('leads.drafts.update', [$setup['lead'], $draft]), [
        'intent' => 'save',
        'body' => 'I can pass by the shop on Saturday morning.',
    ])->assertRedirect();

    $draft->refresh();

    expect($draft->original_body)->toBe('Saturday works. I can come by the shop.')
        ->and($draft->body)->toBe('I can pass by the shop on Saturday morning.')
        ->and($draft->status)->toBe(DraftStatus::Pending)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $this->post(route('leads.drafts.update', [$setup['lead'], $draft]), [
        'intent' => 'send',
        'body' => 'I can pass by the shop on Saturday morning.',
    ])->assertRedirect();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($draft->fresh()?->status)->toBe(DraftStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->calls[0]['body'])->toBe('I can pass by the shop on Saturday morning.');

    $this->post(route('leads.drafts.update', [$setup['lead'], $draft]), [
        'intent' => 'send',
        'body' => 'I can pass by the shop on Saturday morning.',
    ])->assertInvalid(['body']);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('refuses an approved reply when the contact has opted out', function () {
    aiReady();
    whatsappConfig();
    $setup = aiLead();
    whatsappInbound($setup['lead']);
    $setup['lead']->contact->forceFill([
        'consent_status' => ConsentStatus::OptedOut,
    ])->save();
    app(FakeLlmClient::class)->push(aiJson());
    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();
    $draft = ReplyDraft::query()->firstOrFail();

    $this->post(route('leads.drafts.update', [$setup['lead'], $draft]), [
        'intent' => 'send',
        'body' => 'Saturday works.',
    ])->assertInvalid(['body']);

    expect($draft->fresh()?->status)->toBe(DraftStatus::Pending)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);
});

it('leaves a queued analysis when the queue push fails and retries it later', function () {
    aiReady();
    $setup = aiLead();
    app(FakeLlmClient::class)->push(aiJson());
    app()->instance(AnalysisDispatcher::class, new class implements AnalysisDispatcher
    {
        public function dispatch(int $analysisId): void
        {
            throw new RuntimeException('redis down');
        }
    });

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    expect(LeadAnalysis::query()->first()?->status)->toBe(AnalysisStatus::Queued)
        ->and(app(FakeLlmClient::class)->calls)->toBe([]);

    $this->artisan('ai:retry')->assertSuccessful();

    expect(LeadAnalysis::query()->first()?->status)->toBe(AnalysisStatus::Completed);
});

it('does not start a second analysis while one is queued', function () {
    aiReady();
    $setup = aiLead();
    $dispatcher = new class implements AnalysisDispatcher
    {
        public int $calls = 0;

        public function dispatch(int $analysisId): void
        {
            $this->calls++;
        }
    };
    app()->instance(AnalysisDispatcher::class, $dispatcher);

    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();
    $this->post(route('leads.analyses.store', $setup['lead']))->assertRedirect();

    expect(LeadAnalysis::query()->count())->toBe(1)
        ->and($dispatcher->calls)->toBe(1);
});

it('lets an owner save knowledge and refuses a sales user', function () {
    $sales = crmSalesUser();
    $owner = User::factory()->owner()->create();

    $this->actingAs($sales)
        ->post(route('ai.articles.store'), [
            'title' => 'Counter book',
            'body' => 'Paper pads lose Saturday orders.',
            'product_id' => 'none',
            'is_active' => '1',
        ])->assertForbidden();

    $this->actingAs($owner)
        ->post(route('ai.articles.store'), [
            'title' => 'Counter book',
            'body' => 'Paper pads lose Saturday orders.',
            'product_id' => 'none',
            'is_active' => '1',
        ])->assertRedirect(route('ai.settings'));

    expect(KnowledgeArticle::query()->where('title', 'Counter book')->exists())->toBeTrue();
});

it('shows whether the model is configured without revealing the key', function () {
    config([
        'ai.api_key' => 'super-secret-key',
        'ai.base_url' => 'https://llm.test/v1',
        'ai.model' => 'desk-model',
    ]);

    $this->actingAs(crmSalesUser())
        ->get(route('ai.settings'))
        ->assertOk()
        ->assertDontSee('super-secret-key')
        ->assertInertia(fn ($page) => $page
            ->where('configured', true)
            ->where('variables.0.key', 'LLM_API_KEY')
            ->where('variables.0.configured', true)
            ->missing('variables.0.value'));
});

it('retrieves the note that shares the conversation and skips an inactive one', function () {
    $setup = aiLead('The Saturday counter book loses orders.');
    KnowledgeArticle::query()->create([
        'title' => 'Counter book',
        'body' => 'A Saturday counter book is a paper order pad.',
        'is_active' => true,
    ]);
    KnowledgeArticle::query()->create([
        'title' => 'Hidden counter book',
        'body' => 'A Saturday counter book that is inactive.',
        'is_active' => false,
    ]);
    KnowledgeArticle::query()->create([
        'title' => 'Solar',
        'body' => 'Panels and inverters for farms.',
        'is_active' => true,
    ]);

    $found = app(KnowledgeRetriever::class)->forLead($setup['lead']);

    expect($found)->toHaveCount(1)
        ->and($found->first()?->title)->toBe('Counter book');
});

it('includes a product note even when the words do not overlap', function () {
    $setup = aiLead('Can you call me tomorrow?');
    KnowledgeArticle::query()->create([
        'title' => 'Install notes',
        'body' => 'Bring a printed quote.',
        'product_id' => $setup['desk']['product']->id,
        'is_active' => true,
    ]);

    $found = app(KnowledgeRetriever::class)->forLead($setup['lead']);

    expect($found)->toHaveCount(1)
        ->and($found->first()?->title)->toBe('Install notes');
});

it('calls an OpenAI-compatible endpoint with the configured model', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://llm.test/v1',
        'ai.model' => 'desk-model',
    ]);
    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => '{"summary":"Saturday orders are lost."}'],
            ]],
        ]),
    ]);

    $text = (new OpenAiCompatibleClient(new LlmConfiguration))->complete('Read the lead.', 'Conversation');

    expect($text)->toContain('Saturday orders are lost.');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://llm.test/v1/chat/completions'
            && $request['model'] === 'desk-model'
            && $request->hasHeader('Authorization', 'Bearer test-key');
    });
});

it('treats a failed model response as a request failure', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://llm.test/v1',
        'ai.model' => 'desk-model',
    ]);
    Http::fake([
        'https://llm.test/v1/*' => Http::response('no', 503),
    ]);

    expect(fn () => (new OpenAiCompatibleClient(new LlmConfiguration))->complete('Read the lead.', 'Conversation'))
        ->toThrow(LlmRequestFailed::class);
});
