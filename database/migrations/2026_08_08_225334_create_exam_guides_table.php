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
        Schema::create('exam_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->text('intro')->nullable();
            $table->string('conducting_body')->nullable();
            $table->text('eligibility')->nullable();
            $table->text('exam_pattern')->nullable();
            $table->string('passing_marks')->nullable();
            $table->string('application_period')->nullable();
            $table->longText('syllabus')->nullable();
            $table->json('faqs')->nullable();
            $table->string('mock_test_url')->nullable();
            $table->string('question_count_label')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->date('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_category_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_guides');
    }
};
