<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('is_active');
            $table->string('external_id')->nullable()->after('source');
            $table->timestamp('synced_at')->nullable()->after('external_id');
            $table->index(['source', 'external_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('general');
            $table->string('vendor')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->date('spent_on');
            $table->string('system_name')->default('expenses');
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
            $table->index(['spent_on', 'category']);
        });

        Schema::create('integration_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // meta_business | google_search_console
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // site_url, page_id, property — never secrets
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'label']);
        });

        Schema::create('integration_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_account_id')->constrained('integration_accounts')->cascadeOnDelete();
            $table->string('report_type'); // traffic | search | engagement | overview
            $table->date('period_start');
            $table->date('period_end');
            $table->json('metrics');
            $table->json('rows')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['integration_account_id', 'report_type', 'period_end']);
        });

        Schema::create('whatsapp_knowledge_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued');
            $table->unsignedInteger('articles_created')->default(0);
            $table->unsignedInteger('messages_scanned')->default(0);
            $table->text('summary')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_knowledge_syncs');
        Schema::dropIfExists('integration_reports');
        Schema::dropIfExists('integration_accounts');
        Schema::dropIfExists('expenses');

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropIndex(['source', 'external_id']);
            $table->dropColumn(['source', 'external_id', 'synced_at']);
        });
    }
};
