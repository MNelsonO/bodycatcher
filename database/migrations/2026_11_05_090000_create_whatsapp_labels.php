<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_labels', function (Blueprint $table) {
            $table->id();
            $table->string('label_id')->unique();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('contact_whatsapp_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('label_id');
            $table->timestamps();
            $table->unique(['contact_id', 'label_id']);
            $table->index('label_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_whatsapp_label');
        Schema::dropIfExists('whatsapp_labels');
    }
};
