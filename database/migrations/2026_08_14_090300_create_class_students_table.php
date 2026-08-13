<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('institute_student_id')->constrained('institute_students')->cascadeOnDelete();
            // pending: student applied, awaiting teacher review.
            // enrolled: active member (either approved, or added directly by the teacher).
            // rejected: teacher declined the application.
            $table->enum('status', ['pending', 'enrolled', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['class_id', 'institute_student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
