<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->string('category', 32)->default('fact')->after('body');
            $table->index('category');
        });

        Schema::create('knowledge_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('source', 32)->default('inbound');
            $table->text('question');
            $table->text('context_snippet')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('stage_name')->nullable();
            $table->string('lead_status')->nullable();
            $table->text('answer')->nullable();
            $table->string('lesson_category', 32)->nullable();
            $table->foreignId('article_id')->nullable()->constrained('knowledge_articles')->nullOnDelete();
            $table->foreignId('answered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_gaps');

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
