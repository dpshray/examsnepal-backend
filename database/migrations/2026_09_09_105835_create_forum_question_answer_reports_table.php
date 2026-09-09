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
        Schema::create('forum_question_answer_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('forum_question_id');
            $table->foreign('forum_question_id')->references('id')->on('forum_questions')->onDelete('cascade');
            $table->unsignedBigInteger('forum_answer_id')->nullable();
            $table->foreign('forum_answer_id')->references('id')->on('forum_answers')->onDelete('cascade');
            $table->unsignedBigInteger('student_id');
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->string('report_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_question_answer_reports');
    }
};
