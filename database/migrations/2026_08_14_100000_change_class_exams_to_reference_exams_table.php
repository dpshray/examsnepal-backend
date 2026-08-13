<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classes should link to "Student Exams" (the `exams` table, taken by StudentProfile
     * accounts on the main platform), not "Corporate Exams" (the recruitment exam system).
     * Recreates class_exams from scratch pointed at `exams` instead of `corporate_exams`.
     */
    public function up(): void
    {
        Schema::dropIfExists('class_exams');

        Schema::create('class_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exams');
    }
};
