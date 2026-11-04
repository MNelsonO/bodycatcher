<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Label links for chats the desk cannot place yet (WhatsApp privacy
        // ids). They attach once a live message reveals the phone number.
        Schema::create('whatsapp_pending_labels', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id');
            $table->string('label_id');
            $table->timestamps();
            $table->unique(['chat_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_pending_labels');
    }
};
