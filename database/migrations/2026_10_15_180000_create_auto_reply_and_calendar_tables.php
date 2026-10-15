<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->string('match_type'); // contains | exact | any
            $table->string('match_value')->nullable();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->string('reply_mode'); // template | llm
            $table->text('template_body')->nullable();
            $table->boolean('require_open_window')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'priority']);
        });

        Schema::create('calendar_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('activity_on');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('activity_type')->default('task');
            $table->string('color')->default('blue');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('external_calendar_id')->nullable();
            $table->timestamps();
            $table->index(['activity_on', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_activities');
        Schema::dropIfExists('auto_reply_rules');
    }
};
