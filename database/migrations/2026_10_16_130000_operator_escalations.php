<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('source', 32)->default('live_assistant');
            $table->text('reason');
            $table->text('inbound_snippet')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->text('operator_correction')->nullable();
            $table->foreignId('calendar_activity_id')->nullable()->constrained('calendar_activities')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_escalations');
    }
};
