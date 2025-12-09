<?php

use App\Models\Corporate\CorporateExam;
use App\Models\Corporate\CorporateExamSection;
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
        Schema::create('corporate_questions', function (Blueprint $table) {
            $table->id();
            $table->integer('corporate_exam_section_id');
            $table->foreign('corporate_exam_section_id')->references('id')->on('corporate_exam_sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('question');
            $table->text('description')->nullable();
            $table->boolean('is_negative_marking')->default(false);
            $table->decimal('negative_mark', 5, 2)->default(0);
            $table->decimal('full_marks', 5, 2)->default(1);
            $table->string('question_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_questions');
    }
};
