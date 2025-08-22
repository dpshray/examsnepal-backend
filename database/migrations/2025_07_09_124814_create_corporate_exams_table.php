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
        Schema::create('corporate_exams', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->uuid();
            $table->unsignedBigInteger('corporate_id');
            $table->foreign('corporate_id')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('about')->nullable();
            $table->text('rules')->nullable();
            $table->boolean('is_published')->default(true); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corporate_exams');
    }
};
