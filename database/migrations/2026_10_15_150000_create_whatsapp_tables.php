<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key')->unique();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->index('processed_at');
        });

        Schema::create('desk_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('automatic_sends_paused')->default(false);
            $table->foreignId('inbound_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('desk_settings')->insert([
            'automatic_sends_paused' => false,
            'inbound_product_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('automation_paused')->default(false);
            $table->boolean('taken_over')->default(false);
            $table->boolean('needs_personal_attention')->default(false);
        });

        Schema::table('lead_stage_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('channel')->default('desk');
            $table->string('origin')->default('manual');
            $table->string('status')->default('logged');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('provider_message_id')->nullable()->unique();
            $table->json('provider_response')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedSmallInteger('send_attempts')->default(0);
            $table->unsignedBigInteger('logged_by_user_id')->nullable()->change();
        });

        Schema::create('message_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('provider_timestamp');
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'status', 'provider_timestamp']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('provider_media_id');
            $table->string('mime_type')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'provider_media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_status_events');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['provider_message_id']);
            $table->dropColumn([
                'channel',
                'origin',
                'status',
                'idempotency_key',
                'provider_message_id',
                'provider_response',
                'refusal_reason',
                'retryable',
                'send_attempts',
            ]);
            $table->unsignedBigInteger('logged_by_user_id')->nullable(false)->change();
        });

        Schema::table('lead_stage_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['automation_paused', 'taken_over', 'needs_personal_attention']);
        });

        Schema::dropIfExists('desk_settings');
        Schema::dropIfExists('webhook_events');
    }
};
