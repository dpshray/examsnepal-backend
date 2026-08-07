<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            // Denormalized snapshot of the exam-type-wide numbers this payout was
            // calculated from, kept even if settings/data change later so a payout
            // is always independently explainable.
            $table->decimal('revenue_amount', 12, 2)->comment('Amortized subscription revenue for this exam type + period');
            $table->decimal('pool_percentage_used', 5, 2);
            $table->decimal('pool_amount', 12, 2)->comment('revenue_amount * pool_percentage_used');
            $table->unsignedInteger('total_unique_completions')->comment('Across all teachers for this exam type + period');

            $table->unsignedInteger('teacher_unique_completions');
            $table->decimal('share_percentage', 8, 4);
            $table->decimal('payout_amount', 12, 2);

            $table->string('status')->default('pending'); // pending | approved | paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'exam_type_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_payouts');
    }
};
