<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('settings');
        });

        Schema::table('desk_settings', function (Blueprint $table) {
            $table->string('messaging_driver', 32)->nullable()->after('live_assistant_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('integration_accounts', function (Blueprint $table) {
            $table->dropColumn('credentials');
        });

        Schema::table('desk_settings', function (Blueprint $table) {
            $table->dropColumn('messaging_driver');
        });
    }
};
