<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // True for exams created directly inside a class (Class Management
            // → Exams → Create Exam). These are scoped to that class's
            // enrolled students only and must stay out of the general
            // "Student Exams" list.
            $table->boolean('is_class_exam')->default(false)->after('exam_mode');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('is_class_exam');
        });
    }
};
