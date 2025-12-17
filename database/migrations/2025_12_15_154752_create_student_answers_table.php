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
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_attempts_id')
                ->constrained('exam_attempts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('question_id')
                ->constrained('corporate_questions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('options_id')
                ->nullable()
                ->constrained('corporate_question_options')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->longText('subjective_answer')->nullable();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
