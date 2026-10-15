<?php

use App\SalesPipelines\Actions\ReshapeKemetSalesPipeline;
use App\SalesPipelines\DefaultKemetStages;
use App\SalesPipelines\Pipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('name');
        });

        if (Pipeline::query()->where('name', DefaultKemetStages::PIPELINE_NAME)->exists()
            || Pipeline::query()->where('is_default', true)->exists()) {
            app(ReshapeKemetSalesPipeline::class)->handle(
                Pipeline::query()->where('name', DefaultKemetStages::PIPELINE_NAME)->first()
                    ?? Pipeline::query()->where('is_default', true)->first()
            );
        }
    }

    public function down(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
