<?php

namespace Database\Seeders;

use App\SalesPipelines\Actions\ReshapeKemetSalesPipeline;
use Illuminate\Database\Seeder;

class KemetSalesPipelineSeeder extends Seeder
{
    public function run(): void
    {
        app(ReshapeKemetSalesPipeline::class)->handle();
    }
}
