<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_exam_question_id')->constrained('class_exam_questions')->cascadeOnDelete();
            $table->text('option');
            $table->boolean('value')->default(false); // is_correct
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam_question_options');
    }
};
