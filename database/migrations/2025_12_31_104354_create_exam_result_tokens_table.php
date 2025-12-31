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
        Schema::create('exam_result_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_exam_id');
            // For private exams
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('email')->nullable(); // For public exams
            $table->string('result_token', 32)->unique();
            $table->timestamps();

            $table->foreign('corporate_exam_id')->references('id')->on('corporate_exams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('participant_id')->references('id')->on('participants')->cascadeOnDelete()->cascadeOnUpdate();
            // Ensure one token per participant per exam
            $table->unique(['corporate_exam_id', 'participant_id'], 'unique_participant_exam');
            $table->unique(['corporate_exam_id', 'email'], 'unique_email_exam');

            $table->index('result_token');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->rememberToken()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_result_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
