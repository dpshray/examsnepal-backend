<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            // corporate_exams.id is a plain signed int (not bigint unsigned), so it can't use foreignId() here.
            $table->integer('corporate_exam_id');
            $table->foreign('corporate_exam_id')->references('id')->on('corporate_exams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'corporate_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exams');
    }
};
