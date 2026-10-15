<?php

namespace Database\Seeders;

use App\Contacts\Actions\CreateOrUpdateContact;
use App\Contacts\ConsentStatus;
use App\Contacts\ContactPhone;
use App\Conversations\Actions\LogMessage;
use App\Conversations\MessageDirection;
use App\Leads\Actions\AddLeadNote;
use App\Leads\Actions\CreateLead;
use App\Leads\Actions\SetFollowUp;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\Leads\Services\SalesStageTransitionService;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\Security\Role;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (ContactPhone::query()->where('phone', '+254722100201')->exists()) {
            $this->command->info('Demo data is already present.');

            return;
        }

        $this->call(KemetSalesPipelineSeeder::class);

        $actor = User::query()->where('role', Role::Owner->value)->first();

        if ($actor === null) {
            throw new RuntimeException('Seed the owner first with php artisan db:seed, then run DemoDataSeeder.');
        }

        $pipeline = Pipeline::query()->where('name', 'Kemet Tech sales')->firstOrFail();
        $laundry = Product::query()->updateOrCreate(
            ['slug' => 'kemet-laundry-os'],
            [
                'name' => 'Kemet Laundry OS',
                'description' => 'Branch operations desk for laundry and dry-cleaning businesses: orders, pickup routes, and branch performance.',
                'pricing_notes' => 'Operator maintains the quote. Sample note only: priced per branch after a demo, never invented by the desk.',
                'is_active' => true,
                'archived_at' => null,
            ],
        );
        $routes = Product::query()->updateOrCreate(
            ['slug' => 'kemet-route-desk'],
            [
                'name' => 'Kemet Route Desk',
                'description' => 'Crew routing for contract cleaning companies that run several sites a day.',
                'pricing_notes' => 'Leave the commercial figure blank until the operator writes it. This sample has no price.',
                'is_active' => true,
                'archived_at' => null,
            ],
        );

        $clock = app(DisplayClock::class);
        $today = $clock->today();
        $overdue = $clock->now()->subDay()->toDateString();
        $upcoming = $clock->now()->addDays(3)->toDateString();

        $contacts = app(CreateOrUpdateContact::class);
        $leads = app(CreateLead::class);
        $notes = app(AddLeadNote::class);
        $messages = app(LogMessage::class);
        $followUps = app(SetFollowUp::class);

        $amina = $contacts->handle([
            'full_name' => 'Amina Wanjiku',
            'primary_phone' => '0722 100 201',
            'business_name' => 'FreshFold Laundry',
            'business_type' => 'Laundry',
            'location' => 'Westlands, Nairobi',
            'email' => 'amina@freshfold.co.ke',
            'preferred_channel' => 'whatsapp',
            'consent_status' => ConsentStatus::OptedIn->value,
            'lead_source' => 'Referral from a Westlands tailor',
        ], $actor)->contact;

        $aminaLead = $leads->handle([
            'contact_id' => $amina->id,
            'product_id' => $laundry->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Referral',
            'business_problem' => 'Walk-in tickets are still written in a counter book, so the Saturday rush loses orders.',
            'current_solution' => 'Exercise book and an M-Pesa till.',
            'branch_count' => 1,
            'decision_maker_status' => DecisionMakerStatus::Yes->value,
            'purchase_timeline_note' => 'Wants something in place before the December wedding season.',
            'budget_note' => 'Has not given a figure. Do not guess one.',
        ], $actor);
        $followUps->handle($aminaLead, 'Call about a Westlands branch pilot', $today, $actor);
        $messages->handle($aminaLead, MessageDirection::Inbound, 'Habari, I was given your number by Peter at the tailor shop. Can Kemet handle a single laundry shop first?', null, $actor);
        $notes->handle($aminaLead, 'Sample record. Amina asked for a phone call, not a quote.', $actor);

        $brian = $contacts->handle([
            'full_name' => 'Brian Otieno',
            'primary_phone' => '0733 200 302',
            'business_name' => 'Sparkle & Mop Ltd',
            'business_type' => 'Commercial cleaning',
            'location' => 'Kisumu',
            'email' => 'brian@sparklemop.co.ke',
            'preferred_channel' => 'phone',
            'consent_status' => ConsentStatus::OptedOut->value,
            'lead_source' => 'Website inquiry',
        ], $actor)->contact;

        $brianLead = $leads->handle([
            'contact_id' => $brian->id,
            'product_id' => $routes->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Website',
            'business_problem' => 'Asked about crew routing, then said he does not want sales calls.',
            'decision_maker_status' => DecisionMakerStatus::Unknown->value,
        ], $actor);
        $this->moveTo($brianLead, 'Lost', $actor, 'Contact opted out of sales messages.');
        $notes->handle($brianLead->refresh(), 'Sample record. Do not call. Consent is opted out.', $actor);

        $catherine = $contacts->handle([
            'full_name' => 'Catherine Mwangi',
            'primary_phone' => '0711 300 403',
            'business_name' => 'Hilltop Dry Cleaners',
            'business_type' => 'Dry cleaning',
            'location' => 'Thika',
            'email' => 'catherine@hilltopdry.co.ke',
            'preferred_channel' => 'email',
            'consent_status' => ConsentStatus::Unknown->value,
            'lead_source' => 'Walk-in at a Thika suppliers meeting',
        ], $actor)->contact;

        $catherineLead = $leads->handle([
            'contact_id' => $catherine->id,
            'product_id' => $laundry->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Walk-in',
            'business_problem' => 'Garment tickets go missing between the counter and the pressing line.',
            'current_solution' => 'Paper tickets with a carbon copy.',
            'branch_count' => 2,
            'decision_maker_status' => DecisionMakerStatus::No->value,
            'purchase_timeline_note' => 'Her brother signs supplier contracts. He is in Mombasa this week.',
        ], $actor);
        $catherineLead = $this->moveTo($catherineLead, 'Qualified', $actor, null);
        $followUps->handle($catherineLead, 'Email the Thika branch questions she asked for', $overdue, $actor);

        $david = $contacts->handle([
            'full_name' => 'David Kamau',
            'primary_phone' => '0701 400 504',
            'business_name' => 'Pwani Cleaning Co',
            'business_type' => 'Contract cleaning',
            'location' => 'Nyali, Mombasa',
            'email' => 'david@pwanicleaning.co.ke',
            'preferred_channel' => 'whatsapp',
            'consent_status' => ConsentStatus::OptedIn->value,
            'lead_source' => 'Mombasa hospitality exhibition',
        ], $actor)->contact;

        $davidLaundry = $leads->handle([
            'contact_id' => $david->id,
            'product_id' => $laundry->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Exhibition',
            'business_problem' => 'They do not run a laundry. The laundry product was the wrong fit.',
            'decision_maker_status' => DecisionMakerStatus::Yes->value,
        ], $actor);
        $this->moveTo($davidLaundry, 'Lost', $actor, 'They clean sites; they do not operate a laundry.');

        $davidRoutes = $leads->handle([
            'contact_id' => $david->id,
            'product_id' => $routes->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Exhibition',
            'business_problem' => 'Supervisors still dispatch crews on a WhatsApp group each morning.',
            'current_solution' => 'A shared WhatsApp group and a paper site list.',
            'branch_count' => 14,
            'decision_maker_status' => DecisionMakerStatus::Yes->value,
            'purchase_timeline_note' => 'Can look at a demo after the Nyali hotel contract starts.',
            'budget_note' => 'No budget discussed.',
        ], $actor);
        $davidRoutes = $this->moveTo($davidRoutes, 'Demo', $actor, null);
        $followUps->handle($davidRoutes, 'Confirm who from Nyali will join the demo', $upcoming, $actor);
        $messages->handle($davidRoutes, MessageDirection::Outbound, 'David, confirming we can walk through Kemet Route Desk with your Nyali supervisors. Tell me a morning that works.', null, $actor);

        $esther = $contacts->handle([
            'full_name' => 'Esther Njeri',
            'primary_phone' => '0720 500 605',
            'business_name' => 'LinenLoop Serviced Apartments',
            'business_type' => 'Laundry',
            'location' => 'Nairobi CBD',
            'email' => 'esther@linenloop.co.ke',
            'preferred_channel' => 'whatsapp',
            'consent_status' => ConsentStatus::OptedIn->value,
            'lead_source' => 'Operator outreach',
        ], $actor)->contact;

        $estherLead = $leads->handle([
            'contact_id' => $esther->id,
            'product_id' => $laundry->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Operator outreach',
            'business_problem' => 'Four apartment blocks share one laundry and nobody can see which block a bag came from.',
            'current_solution' => 'Coloured clothespins per block.',
            'branch_count' => 4,
            'decision_maker_status' => DecisionMakerStatus::Yes->value,
            'purchase_timeline_note' => 'Board meets on the first Tuesday of next month.',
            'budget_note' => 'She asked for a per-branch figure. None has been written down yet.',
        ], $actor);
        $estherLead = $this->moveTo($estherLead, 'Proposal', $actor, null);
        $notes->handle($estherLead, 'Sample record. Proposal was discussed verbally. The price field stays empty until Nelson writes the real figure.', $actor);
        $messages->handle($estherLead, MessageDirection::Inbound, 'Please send the branch breakdown before our Tuesday meeting. I still need to see it in writing.', null, $actor);

        $grace = $contacts->handle([
            'full_name' => 'Grace Achieng',
            'primary_phone' => '0745 600 706',
            'business_name' => 'FreshFold Laundry',
            'business_type' => 'Laundry',
            'location' => 'Eldoret',
            'email' => 'grace@freshfold-eldoret.co.ke',
            'preferred_channel' => 'phone',
            'consent_status' => ConsentStatus::OptedIn->value,
            'lead_source' => 'Same trading name as the Westlands shop, different owner',
        ], $actor)->contact;

        $graceLead = $leads->handle([
            'contact_id' => $grace->id,
            'product_id' => $laundry->id,
            'pipeline_id' => $pipeline->id,
            'source' => 'Referral',
            'business_problem' => 'Eldoret shop is a separate business from the Westlands FreshFold. She heard Amina was looking at software.',
            'current_solution' => 'Counter book.',
            'branch_count' => 1,
            'decision_maker_status' => DecisionMakerStatus::Yes->value,
        ], $actor);
        $this->moveTo($graceLead, 'Reached', $actor, null);
        $notes->handle($graceLead->refresh(), 'Sample record. Do not merge with Amina Wanjiku. Same business name, different phone, different town.', $actor);

        $this->command->info('Sample Kenyan prospects loaded. They are demo data, not live customers.');
    }

    private function moveTo(Lead $lead, string $targetName, User $actor, ?string $reason): Lead
    {
        $transitions = app(SalesStageTransitionService::class);
        $guard = 0;

        while ($guard < 20) {
            $lead->refresh()->load('currentStage');

            if ($lead->currentStage->name === $targetName) {
                return $lead;
            }

            $stages = PipelineStage::query()
                ->where('pipeline_id', $lead->pipeline_id)
                ->orderBy('position')
                ->get();

            $target = $stages->firstWhere('name', $targetName);

            if (! $target instanceof PipelineStage) {
                throw new RuntimeException('Demo stage ['.$targetName.'] was not found.');
            }

            $direct = PipelineStageTransition::query()
                ->where('from_stage_id', $lead->current_stage_id)
                ->where('to_stage_id', $target->id)
                ->exists();

            if ($direct) {
                return $transitions->transition($lead, $target, $actor, $reason)->refresh();
            }

            $index = $stages->search(fn (PipelineStage $stage): bool => $stage->id === $lead->current_stage_id);
            $next = is_int($index) ? $stages->get($index + 1) : null;

            if (! $next instanceof PipelineStage) {
                throw new RuntimeException('No forward step from '.$lead->currentStage->name.' toward '.$targetName.'.');
            }

            $transitions->transition($lead, $next, $actor, null);
            $guard++;
        }

        throw new RuntimeException('Could not reach demo stage '.$targetName.'.');
    }
}
