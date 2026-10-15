<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('follow_up_rank')->default(0)->after('next_follow_up_on');
            $table->string('follow_up_rank_reason')->nullable()->after('follow_up_rank');
            $table->string('follow_up_source', 32)->nullable()->after('follow_up_rank_reason');
            $table->timestamp('chat_schedule_captured_at')->nullable()->after('follow_up_source');
            $table->index('follow_up_rank');
            $table->index('follow_up_source');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['follow_up_rank']);
            $table->dropIndex(['follow_up_source']);
            $table->dropColumn([
                'follow_up_rank',
                'follow_up_rank_reason',
                'follow_up_source',
                'chat_schedule_captured_at',
            ]);
        });
    }
};
