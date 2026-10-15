<?php

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Leads\Lead;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function crmSalesUser(): User
{
    return User::factory()->create();
}

/**
 * @return array{pipeline: Pipeline, stage: PipelineStage, won: PipelineStage, lost: PipelineStage, side: PipelineStage, product: Product, other: Product}
 */
function crmDesk(): array
{
    $pipeline = Pipeline::factory()->create(['name' => 'Kemet Tech sales', 'is_default' => true]);
    $stage = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'New',
        'kind' => StageKind::Active,
        'position' => 1,
    ]);
    $won = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Won',
        'kind' => StageKind::Won,
        'position' => 2,
    ]);
    $lost = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Lost',
        'kind' => StageKind::Lost,
        'position' => 3,
    ]);
    $side = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Side door',
        'kind' => StageKind::Active,
        'position' => 4,
    ]);

    foreach ([$won, $lost] as $target) {
        PipelineStageTransition::query()->create([
            'pipeline_id' => $pipeline->id,
            'from_stage_id' => $stage->id,
            'to_stage_id' => $target->id,
        ]);
    }

    return [
        'pipeline' => $pipeline,
        'stage' => $stage,
        'won' => $won,
        'lost' => $lost,
        'side' => $side,
        'product' => Product::factory()->create(['name' => 'Kemet Laundry OS', 'slug' => 'kemet-laundry-os']),
        'other' => Product::factory()->create(['name' => 'Kemet Route Desk', 'slug' => 'kemet-route-desk']),
    ];
}

function whatsappConfig(): void
{
    config([
        'whatsapp.token' => 'test-token',
        'whatsapp.phone_number_id' => '123456',
        'whatsapp.app_secret' => 'test-secret',
        'whatsapp.verify_token' => 'verify-me',
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postWhatsAppWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $digest = 'sha256='.hash_hmac('sha256', $body, (string) config('whatsapp.app_secret'));

    return test()->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature ?? $digest,
    ], $body);
}

/**
 * @return array<string, mixed>
 */
function whatsappTextPayload(string $from, string $text, string $wamid, ?int $timestamp = null, string $name = 'Amina Wanjiku'): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '254700000000', 'phone_number_id' => '123456'],
                    'contacts' => [[
                        'profile' => ['name' => $name],
                        'wa_id' => $from,
                    ]],
                    'messages' => [[
                        'from' => $from,
                        'id' => $wamid,
                        'timestamp' => (string) ($timestamp ?? now()->timestamp),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function whatsappStatusPayload(string $wamid, string $status, ?int $timestamp = null): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'WABA',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'statuses' => [[
                        'id' => $wamid,
                        'status' => $status,
                        'timestamp' => (string) ($timestamp ?? now()->timestamp),
                        'recipient_id' => '254722100201',
                    ]],
                ],
            ]],
        ]],
    ];
}

function whatsappOpenLead(): Lead
{
    whatsappConfig();
    $desk = crmDesk();
    $user = crmSalesUser();
    $contact = Contact::factory()->create([
        'full_name' => 'Amina Wanjiku',
        'primary_phone' => '+254722100201',
        'consent_status' => ConsentStatus::Unknown,
    ]);

    test()->actingAs($user)->post(route('leads.store'), [
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'stage_id' => $desk['stage']->id,
        'source' => 'Referral',
        'decision_maker_status' => 'unknown',
    ])->assertRedirect();

    return Lead::query()->firstOrFail();
}

function whatsappInbound(Lead $lead, ?CarbonInterface $sentAt = null): Message
{
    $conversation = $lead->conversation()->firstOrFail();

    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Inbound,
        'channel' => MessageChannel::WhatsApp,
        'origin' => MessageOrigin::Inbound,
        'status' => MessageStatus::Received,
        'body' => 'Can you visit the shop on Saturday?',
        'sent_at' => $sentAt ?? now()->subHour(),
        'provider_message_id' => 'wamid.in.'.uniqid(),
    ]);
}
