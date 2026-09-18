<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_assignment_id')->constrained('class_assignments')->cascadeOnDelete();
            $table->foreignId('institute_student_id')->constrained('institute_students')->cascadeOnDelete();
            $table->enum('type', ['pdf', 'image', 'text']);
            $table->longText('content_text')->nullable();
            $table->string('file_path')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_assignment_id', 'institute_student_id'], 'class_assignment_submissions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assignment_submissions');
    }
};
