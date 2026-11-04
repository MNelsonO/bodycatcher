<?php

namespace Tests\Feature;

use App\Contacts\Contact;
use App\Conversations\Message;
use App\Leads\Lead;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_includes_whatsapp_demo_order_and_chart_metrics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('metrics.whatsapp.conversations')
                ->has('metrics.demos.in_flight')
                ->has('metrics.orders.bought_count')
                ->has('metrics.charts.leads_7d', 7)
                ->has('metrics.charts.whatsapp_7d', 7)
                ->has('metrics.charts.bought_7d', 7)
                ->has('metrics.board.filters')
                ->has('metrics.board.options.stages')
                ->has('metrics.board.leads')
            );
    }

    public function test_dashboard_filters_board_by_status_hot_and_timeline(): void
    {
        $user = User::factory()->create();
        $desk = crmDesk();
        $this->actingAs($user);

        $hot = Contact::factory()->create(['full_name' => 'Hot Buyer']);
        $cold = Contact::factory()->create(['full_name' => 'Cold Lead']);

        $hotStage = PipelineStage::query()
            ->where('pipeline_id', $desk['pipeline']->id)
            ->where('name', 'Proposal')
            ->first();

        if ($hotStage === null) {
            $hotStage = PipelineStage::factory()->create([
                'pipeline_id' => $desk['pipeline']->id,
                'name' => 'Proposal',
                'kind' => StageKind::Active,
                'position' => 20,
            ]);
        }

        Lead::factory()->create([
            'contact_id' => $hot->id,
            'product_id' => $desk['product']->id,
            'pipeline_id' => $desk['pipeline']->id,
            'current_stage_id' => $hotStage->id,
            'business_problem' => 'Needs laundry OS this month',
        ]);

        Lead::factory()->create([
            'contact_id' => $cold->id,
            'product_id' => $desk['product']->id,
            'pipeline_id' => $desk['pipeline']->id,
            'current_stage_id' => $desk['stage']->id,
            'business_problem' => 'Just browsing',
        ]);

        $this->get(route('dashboard', [
            'kind' => 'active',
            'hot' => '1',
            'timeline' => 'this_month',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('metrics.board.filters.hot', true)
                ->where('metrics.board.filters.kind', 'active')
                ->where('metrics.board.filters.timeline', 'this_month')
                ->where('metrics.board.summary.matched', 1)
                ->where('metrics.board.leads.0.contact_name', 'Hot Buyer')
                ->where('metrics.board.leads.0.hot', true)
            );
    }
}

it('lists chats waiting on a reply, not ones already answered', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subMinutes(90));

    $this->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('waiting', 1)
            ->where('waiting.0.contact_name', 'Amina Wanjiku'));

    Message::query()->create([
        'conversation_id' => $lead->conversation()->firstOrFail()->id,
        'direction' => 'outbound', 'channel' => 'whatsapp', 'origin' => 'manual', 'status' => 'sent',
        'body' => 'Replied', 'sent_at' => now()->subMinutes(5),
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('waiting', 0));
});

it('reports replies sent today and the typical reply time', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();
    whatsappInbound($lead, now()->subMinutes(50));
    Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outbound', 'channel' => 'whatsapp', 'origin' => 'manual', 'status' => 'sent',
        'body' => 'On it', 'sent_at' => now()->subMinutes(30),
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reply_stats.median_minutes', 20)
            ->where('reply_stats.answered', 1)
            ->where('waiting_count', 0));
});
