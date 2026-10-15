<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('location');
            $table->string('country')->nullable()->after('country_code');
            $table->string('conversation_area')->nullable()->after('country');
            $table->index('country_code');
            $table->index('country');
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['contact_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_notes');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropIndex(['country']);
            $table->dropColumn(['country_code', 'country', 'conversation_area']);
        });
    }
};
