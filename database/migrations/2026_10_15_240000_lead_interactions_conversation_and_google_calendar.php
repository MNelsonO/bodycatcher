<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('next_conversation_at')->nullable()->after('next_follow_up_on');
            $table->boolean('conversations_stopped')->default(false)->after('needs_personal_attention');
            $table->index('next_conversation_at');
        });

        Schema::create('lead_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type'); // phone_call | meeting | note | visit | other
            $table->string('direction')->default('outbound'); // inbound | outbound | none
            $table->text('body');
            $table->timestamp('occurred_at');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'occurred_at']);
            $table->index(['contact_id', 'occurred_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_interactions');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['next_conversation_at']);
            $table->dropColumn(['next_conversation_at', 'conversations_stopped']);
        });
    }
};
