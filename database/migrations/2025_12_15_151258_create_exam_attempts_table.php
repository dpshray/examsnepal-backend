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
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_exam_id');
            $table->foreign('corporate_exam_id')->references('id')->on('corporate_exams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('corporate_exam_section_id');
            $table->foreign('corporate_exam_section_id')->references('id')->on('corporate_exam_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->foreign('participant_id')->references('id')->on('participants')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('attempt_number');
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->enum('status',['started','submitted','evaluating','evaluated']);
            $table->decimal('total_mark')->nullable();
            $table->decimal('obtained_mark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
