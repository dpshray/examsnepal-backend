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
        Schema::table('answersheets', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropForeign(['student_id']);
            $table->dropColumn([
                'exam_id',
                'student_id',
                'correct_answer_submitted', 
                'choosed_option_value']);
            $table->unsignedBigInteger('student_exam_id')->after('id');
            $table->foreign('student_exam_id')->references('id')->on('student_exams')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('selected_option_id')->nullable()->after('question_id');
            $table->foreign('selected_option_id')->references('id')->on('option_questions')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('answersheets', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('set null');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('set null');
            
            $table->boolean('correct_answer_submitted')->nullable()->default(null);
            $table->integer('choosed_option_value')->nullable();

            $table->dropForeign(['student_exam_id']);
            $table->dropColumn('student_exam_id');

            $table->dropColumn('is_correct');
        });
    }
};
