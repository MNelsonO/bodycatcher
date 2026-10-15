<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->default('Kemet Tech');
            $table->string('trading_name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Kenya');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('bank_details')->nullable();
            $table->decimal('default_tax_rate', 5, 2)->default(16);
            $table->string('currency', 3)->default('KES');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft'); // draft|sent|unpaid|paid|overdue|void
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3)->default('KES');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('share_token', 64)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 12, 2)->default(0);
            $table->decimal('line_tax', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(16);
            $table->string('cadence')->default('monthly');
            $table->date('next_due_on');
            $table->string('status')->default('active'); // active|paused|ended
            $table->unsignedSmallInteger('alert_days_before')->default(3);
            $table->text('notes')->nullable();
            $table->foreignId('last_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'next_due_on']);
        });

        Schema::create('tax_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // invoice|subscription|manual|system
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('description');
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->date('occurred_on');
            $table->string('system_name')->default('billing');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['occurred_on', 'system_name']);
            $table->index(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_ledger_entries');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('business_profiles');
    }
};
