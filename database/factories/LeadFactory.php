<?php

namespace Database\Factories;

use App\Contacts\Contact;
use App\Leads\DecisionMakerStatus;
use App\Leads\Lead;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\Settings\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'product_id' => Product::factory(),
            'pipeline_id' => Pipeline::factory(),
            'current_stage_id' => PipelineStage::factory(),
            'source' => 'referral',
            'business_problem' => null,
            'current_solution' => null,
            'branch_count' => null,
            'budget_note' => null,
            'decision_maker_status' => DecisionMakerStatus::Unknown,
            'purchase_timeline_note' => null,
            'next_action' => null,
            'next_follow_up_on' => null,
            'lost_reason' => null,
            'closed_at' => null,
        ];
    }
}
