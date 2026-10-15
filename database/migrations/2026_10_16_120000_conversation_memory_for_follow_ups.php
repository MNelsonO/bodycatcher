<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('memory_summary')->nullable()->after('topic_summary');
        });

        Schema::table('automation_rules', function (Blueprint $table) {
            $table->boolean('use_conversation_memory')->default(true)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn('use_conversation_memory');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('memory_summary');
        });
    }
};
