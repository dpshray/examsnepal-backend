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
        Schema::create('answersheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->integer('student_id')->nullable();
            $table->boolean('correct_answer_submitted')->nullable()->default(null);
            $table->integer('choosed_option_value')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('set null');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('set null');
            $table->foreign('student_id')->references('id')->on('student_profile')->onDelete('set null');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answersheets');
    }
};
