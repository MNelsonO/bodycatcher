<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desk_settings', function (Blueprint $table) {
            $table->boolean('live_assistant_enabled')->default(false)->after('inbound_product_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('contact_id');
            $table->text('topic_summary')->nullable()->after('topic');
            $table->foreignId('focused_product_id')->nullable()->after('topic_summary')
                ->constrained('products')->nullOnDelete();
            $table->timestamp('topic_updated_at')->nullable()->after('focused_product_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->date('marked_on')->nullable()->after('next_follow_up_on');
            $table->string('marked_label')->nullable()->after('marked_on');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['marked_on', 'marked_label']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('focused_product_id');
            $table->dropColumn(['topic', 'topic_summary', 'topic_updated_at']);
        });

        Schema::table('desk_settings', function (Blueprint $table) {
            $table->dropColumn('live_assistant_enabled');
        });
    }
};
