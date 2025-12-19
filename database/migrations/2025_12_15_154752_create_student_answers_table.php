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
            $table->id(); // This creates bigint unsigned auto_increment

            // exam_attempts_id - check if exam_attempts.id is int or bigint
            $table->unsignedBigInteger('exam_attempts_id');
            $table->foreign('exam_attempts_id')
                ->references('id')
                ->on('exam_attempts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // question_id - bigint unsigned to match corporate_questions
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')
                ->references('id')
                ->on('corporate_questions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // options_id - bigint unsigned to match corporate_question_options
            $table->unsignedBigInteger('options_id')->nullable();
            $table->foreign('options_id')
                ->references('id')
                ->on('corporate_question_options')
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
