<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->text('default_terms')->nullable()->after('bank_details');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->text('terms_and_conditions')->nullable()->after('notes');
        });

        Schema::create('invoice_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_generation_id')->nullable()->constrained('invoice_generations')->nullOnDelete();
            $table->text('prompt');
            $table->string('status')->default('queued'); // queued|completed|failed|unavailable
            $table->json('result')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_generations');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('terms_and_conditions');
        });

        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn('default_terms');
        });
    }
};
