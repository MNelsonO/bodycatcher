<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('institution')->nullable(); // bank / M-Pesa / cash
            $table->string('account_type')->default('bank'); // bank | mobile | cash | other
            $table->string('account_number')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->decimal('balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('money_debts', function (Blueprint $table) {
            $table->id();
            $table->string('direction'); // payable = I owe them | receivable = they owe me
            $table->string('party_name');
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->date('due_on')->nullable();
            $table->string('status')->default('open'); // open | partial | settled
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['direction', 'status', 'due_on']);
        });

        Schema::create('money_recurrings', function (Blueprint $table) {
            $table->id();
            $table->string('direction'); // outflow = I pay | inflow = I receive
            $table->string('title');
            $table->string('party_name')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('cadence')->default('monthly'); // monthly | weekly | yearly
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-28 for monthly
            $table->date('next_due_on');
            $table->string('status')->default('active'); // active | paused | ended
            $table->foreignId('money_account_id')->nullable()->constrained('money_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_due_on', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_recurrings');
        Schema::dropIfExists('money_debts');
        Schema::dropIfExists('money_accounts');
    }
};
