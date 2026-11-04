<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->index('last_message_at');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('whatsapp_profile_name')->nullable();
        });

        Schema::table('follow_up_tasks', function (Blueprint $table) {
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind');
            $table->string('status');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('starts_at');
            $table->index(['lead_id', 'starts_at']);
        });

        $latest = DB::table('messages')
            ->selectRaw('conversation_id, max(sent_at) as last_at')
            ->groupBy('conversation_id')
            ->get();

        foreach ($latest as $row) {
            DB::table('conversations')->where('id', $row->conversation_id)->update([
                'last_message_at' => $row->last_at,
                'last_read_at' => $row->last_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');

        Schema::table('follow_up_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_by_user_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('whatsapp_profile_name');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['last_message_at']);
            $table->dropColumn(['last_message_at', 'last_read_at']);
        });
    }
};
