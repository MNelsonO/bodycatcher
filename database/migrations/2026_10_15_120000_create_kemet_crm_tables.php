<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('sales')->after('email');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('pricing_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index('is_default');
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->unsignedInteger('position');
            $table->unsignedInteger('default_follow_up_delay_hours')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['pipeline_id', 'position']);
        });

        Schema::create('pipeline_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['from_stage_id', 'to_stage_id']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('primary_phone');
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('location')->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_channel');
            $table->string('consent_status');
            $table->timestamp('opted_out_at')->nullable();
            $table->string('lead_source')->nullable();
            $table->foreignId('merged_into_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamps();
            $table->index('primary_phone');
            $table->index('business_name');
            $table->index('lead_source');
            $table->index('consent_status');
        });

        Schema::create('contact_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['contact_id', 'is_primary']);
        });

        Schema::create('contact_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survivor_contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('merged_contact_id')->unique()->constrained('contacts')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->string('source')->nullable();
            $table->text('business_problem')->nullable();
            $table->text('current_solution')->nullable();
            $table->unsignedInteger('branch_count')->nullable();
            $table->text('budget_note')->nullable();
            $table->string('decision_maker_status')->default('unknown');
            $table->text('purchase_timeline_note')->nullable();
            $table->text('next_action')->nullable();
            $table->date('next_follow_up_on')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index('source');
            $table->index('next_follow_up_on');
            $table->index('closed_at');
            $table->index(['pipeline_id', 'current_stage_id']);
        });

        DB::statement('CREATE UNIQUE INDEX leads_one_open_contact_product ON leads (contact_id, product_id) WHERE closed_at IS NULL');

        Schema::create('lead_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('pipeline_stages')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->text('body');
            $table->timestamp('sent_at');
            $table->foreignId('logged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['conversation_id', 'sent_at']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
            $table->index(['contact_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('lead_stage_histories');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contact_merges');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('pipeline_stage_transitions');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('products');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
