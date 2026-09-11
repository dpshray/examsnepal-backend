<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_exam_section_id')->constrained('class_exam_sections')->cascadeOnDelete();
            $table->string('question_type')->default('mcq'); // 'mcq' | 'subjective'
            $table->longText('question');
            $table->text('description')->nullable(); // explanation (mcq) / instructions (subjective)
            $table->decimal('full_marks', 8, 2)->default(1);
            $table->boolean('is_negative_marking')->default(false);
            $table->decimal('negative_mark', 5, 2)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam_questions');
    }
};
