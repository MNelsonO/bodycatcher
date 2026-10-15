<?php

use App\Billing\Services\InvoiceService;
use App\Contacts\Contact;
use App\Conversations\Actions\LogMessage;
use App\Conversations\MessageDirection;
use App\Expenses\Expense;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Support\Carbon;

it('shows the reports page with product and spend metrics for a date range', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Laundry OS Report',
        'slug' => 'laundry-os-report',
    ]);

    $spentOn = now()->toDateString();

    Expense::query()->create([
        'created_by_user_id' => $user->id,
        'title' => 'Ads boost',
        'category' => 'ads',
        'amount' => 2500,
        'tax_amount' => 0,
        'currency' => 'KES',
        'spent_on' => $spentOn,
        'system_name' => 'expenses',
    ]);

    expect(Expense::query()->count())->toBe(1)
        ->and((float) Expense::query()->sum('amount'))->toBe(2500.0);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($user)
        ->get("/reports?from={$from}&to={$to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('report.filters.from', $from)
            ->where('report.filters.to', $to)
            ->has('report.products')
            ->has('report.daily')
            ->has('report.summary.leads')
            ->where('report.summary.spend', 2500)
            ->where('report.products.0.product_name', $product->name)
        );
});

it('builds advanced multi-sector business reports', function () {
    $user = User::factory()->create();
    $desk = crmDesk();
    $this->actingAs($user);

    $contact = Contact::factory()->create([
        'country_code' => 'KE',
        'country' => 'Kenya',
        'conversation_area' => 'Nairobi laundry ads',
    ]);

    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
        'source' => 'Referral',
    ]);

    app(LogMessage::class)->handle(
        $lead,
        MessageDirection::Inbound,
        'Need a laundry system demo',
        null,
        $user,
    );

    $invoice = app(InvoiceService::class)->create([
        'contact_id' => $contact->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency' => 'KES',
        'notes' => 'Report fixture',
    ], [[
        'description' => 'Laundry OS month',
        'quantity' => 1,
        'unit_price' => 10000,
        'tax_rate' => 16,
    ]], $user);
    app(InvoiceService::class)->markPaid($invoice, now()->toDateString());

    Expense::query()->create([
        'created_by_user_id' => $user->id,
        'title' => 'Meta ads',
        'category' => 'ads',
        'amount' => 1500,
        'tax_amount' => 0,
        'currency' => 'KES',
        'spent_on' => now()->toDateString(),
        'system_name' => 'expenses',
    ]);

    $clock = app(DisplayClock::class);
    $to = $clock->today();
    $from = Carbon::parse($to, $clock->timezone())->subDays(14)->toDateString();

    $this->get("/reports?from={$from}&to={$to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('report.sectors.sales')
            ->has('report.sectors.billing')
            ->has('report.sectors.subscriptions')
            ->has('report.sectors.money')
            ->has('report.sectors.messaging')
            ->has('report.sectors.ai')
            ->has('report.sectors.follow_ups')
            ->has('report.sectors.contacts')
            ->has('report.sectors.integrations')
            ->where('report.summary.bought_revenue', 11600)
            ->where('report.summary.spend', 1500)
            ->where('report.summary.net_position', 10100)
            ->where('report.summary.messages', 1)
            ->where('report.sectors.contacts.total', fn ($total) => $total >= 1)
            ->where('report.sectors.messaging.inbound', 1)
            ->where('report.sectors.billing.paid_revenue', 11600)
            ->where('report.sectors.sales.new_in_range', fn ($total) => $total >= 1)
        );
});
