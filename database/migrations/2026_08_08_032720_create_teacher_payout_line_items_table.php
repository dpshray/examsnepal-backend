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
        Schema::create('teacher_payout_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_payout_id')->constrained('teacher_payouts')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->unsignedInteger('unique_completions');
            $table->decimal('contribution_percentage', 8, 4)->comment('Share of this teacher\'s own payout attributable to this exam');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_payout_line_items');
    }
};
