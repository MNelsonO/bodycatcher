<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = \App\Models\User::factory()->create();
        $desk = crmDesk();
        $this->actingAs($user);

        $hot = \App\Contacts\Contact::factory()->create(['full_name' => 'Hot Buyer']);
        $cold = \App\Contacts\Contact::factory()->create(['full_name' => 'Cold Lead']);

        $hotStage = \App\SalesPipelines\PipelineStage::query()
            ->where('pipeline_id', $desk['pipeline']->id)
            ->where('name', 'Proposal')
            ->first();

        if ($hotStage === null) {
            $hotStage = \App\SalesPipelines\PipelineStage::factory()->create([
                'pipeline_id' => $desk['pipeline']->id,
                'name' => 'Proposal',
                'kind' => \App\SalesPipelines\StageKind::Active,
                'position' => 20,
            ]);
        }

        \App\Leads\Lead::factory()->create([
            'contact_id' => $hot->id,
            'product_id' => $desk['product']->id,
            'pipeline_id' => $desk['pipeline']->id,
            'current_stage_id' => $hotStage->id,
            'business_problem' => 'Needs laundry OS this month',
        ]);

        \App\Leads\Lead::factory()->create([
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
