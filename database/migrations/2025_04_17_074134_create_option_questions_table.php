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
        Schema::create('option_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade')->onUpdate('cascade');
            $table->text('option');
            $table->boolean('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_questions');
    }
};
