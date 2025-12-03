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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('organization_id')->constrained()->onDelete('cascade'); // Organization FK
            // $table->foreignId('exam_type_id')->constrained()->onDelete('set null'); // Exam Type FK
            // $table->string('name');
            // $table->string('description')->nullable();
            // $table->date('exam_date')->nullable();
            // $table->time('exam_time')->nullable();
            // $table->boolean('is_active')->default(true);
            // $table->integer('price')->nullable();

            $table->string('exam_name', 255)->nullable();
            $table->string('exam_date', 255)->nullable();
            $table->string('exam_time', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('price', 255)->nullable();
            $table->string('live', 11)->nullable();
            $table->string('user', 255)->nullable();
            $table->string('exam_type', 255)->nullable();
            $table->string('org', 255)->nullable();
            $table->string('payment_st', 11)->nullable();
            $table->string('assign', 255)->nullable();
            $table->string('end_time', 255)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('topic', 255)->nullable();
            $table->string('in_progress', 255)->nullable();
            $table->string('template', 255)->nullable();
            $table->string('remark', 255)->nullable();
            $table->boolean('is_question_bank')->default(false);
            $table->foreignId('exam_type_id')->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
