<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger');
            $table->string('action');
            $table->unsignedInteger('delay_hours')->nullable();
            $table->string('local_time', 5)->nullable();
            $table->text('body');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('follow_up_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('action');
            $table->text('body');
            $table->timestamp('due_at');
            $table->timestamp('anchor_at')->nullable();
            $table->boolean('cancel_on_reply')->default(false);
            $table->string('status');
            $table->text('held_reason')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_tasks');
        Schema::dropIfExists('automation_rules');
    }
};
