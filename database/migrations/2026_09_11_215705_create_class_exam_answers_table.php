<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_id')->constrained('student_exams')->cascadeOnDelete();
            $table->foreignId('class_exam_question_id')->constrained('class_exam_questions')->cascadeOnDelete();
            $table->foreignId('class_exam_question_option_id')->nullable()
                ->constrained('class_exam_question_options')->nullOnDelete();
            $table->longText('answer_text')->nullable();
            $table->string('answer_file_path')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_exam_id', 'class_exam_question_id'], 'class_exam_answers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam_answers');
    }
};
