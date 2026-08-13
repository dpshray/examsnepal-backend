<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_exams', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');

            $table->unsignedBigInteger('institute_student_id')->nullable()->after('student_id');
            $table->foreign('institute_student_id')->references('id')->on('institute_students')->onDelete('cascade');

            $table->unique(['exam_id', 'institute_student_id'], 'student_exams_exam_institute_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_exams', function (Blueprint $table) {
            $table->dropUnique('student_exams_exam_institute_student_unique');
            $table->dropForeign(['institute_student_id']);
            $table->dropColumn('institute_student_id');

            $table->dropForeign(['student_id']);
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
        });
    }
};
