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

        Schema::table('follow_up_tasks', function (Blueprint $table) {
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->string('start_time', 5)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('location')->nullable();
        });

        // Existing history counts as read, so the inbox does not open with
        // every past message flagged as unread.
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
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'duration_minutes', 'location']);
        });

        Schema::table('follow_up_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_by_user_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['last_message_at']);
            $table->dropColumn(['last_message_at', 'last_read_at']);
        });
    }
};
