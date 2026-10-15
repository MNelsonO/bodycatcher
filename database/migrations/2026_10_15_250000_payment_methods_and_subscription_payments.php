<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['name' => 'M-Pesa', 'slug' => 'mpesa', 'position' => 1],
            ['name' => 'Bank transfer', 'slug' => 'bank-transfer', 'position' => 2],
            ['name' => 'Cash', 'slug' => 'cash', 'position' => 3],
            ['name' => 'Card', 'slug' => 'card', 'position' => 4],
            ['name' => 'Cheque', 'slug' => 'cheque', 'position' => 5],
            ['name' => 'Other', 'slug' => 'other', 'position' => 6],
        ];

        foreach ($defaults as $method) {
            DB::table('payment_methods')->insert([
                ...$method,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['billing_subscription_id', 'paid_on']);
            $table->index(['contact_id', 'paid_on']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('paid_at')->constrained('payment_methods')->nullOnDelete();
        });

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->date('last_paid_on')->nullable()->after('next_due_on');
            $table->foreignId('last_payment_method_id')->nullable()->after('last_paid_on')->constrained('payment_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_payment_method_id');
            $table->dropColumn('last_paid_on');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('payment_methods');
    }
};
