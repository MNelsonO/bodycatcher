<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Which Kemet system the person is interested in (laundry, hotel,
            // pos, carwash…) and how the desk knows: ad, chat, asked, manual.
            $table->string('interest_system')->nullable();
            $table->string('interest_source')->nullable();
            // The Click-to-WhatsApp ad that started the chat, if any.
            $table->string('ad_id')->nullable();
            $table->string('ad_title')->nullable();
            $table->text('ad_body')->nullable();
            $table->string('ad_url', 1024)->nullable();
            $table->timestamp('ad_first_seen_at')->nullable();
            $table->index('interest_system');
            $table->index('ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['interest_system']);
            $table->dropIndex(['ad_id']);
            $table->dropColumn(['interest_system', 'interest_source', 'ad_id', 'ad_title', 'ad_body', 'ad_url', 'ad_first_seen_at']);
        });
    }
};
