<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('summary')->nullable();
            $table->json('extraction')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('score_reasons')->nullable();
            $table->foreignId('suggested_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->text('stage_reason')->nullable();
            $table->text('stage_rejected_reason')->nullable();
            $table->json('knowledge_article_ids')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'status']);
        });

        Schema::create('reply_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_analysis_id')->unique()->constrained('lead_analyses')->cascadeOnDelete();
            $table->text('original_body');
            $table->text('body');
            $table->string('status');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reply_drafts');
        Schema::dropIfExists('lead_analyses');
        Schema::dropIfExists('knowledge_articles');
    }
};
