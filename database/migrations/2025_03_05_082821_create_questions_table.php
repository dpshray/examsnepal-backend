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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->text('question'); 
            $table->string('option_1');
            $table->boolean('option_value_1')->default(0);
            $table->string('option_2');
            $table->boolean('option_value_2')->default(0);
            $table->string('option_3')->nullable();
            $table->boolean('option_value_3')->default(0);
            $table->string('option_4')->nullable();
            $table->boolean('option_value_4')->default(0);
            $table->text('explanation')->nullable();
            $table->string('subject')->nullable();
            $table->string('exam_type')->nullable();
            $table->text('remark')->nullable();
            $table->integer('serial')->nullable();
            $table->unsignedBigInteger('old_exam_id')->nullable();
            $table->foreignId('uploader')->constrained('users')->onDelete('cascade');
            $table->string('mark_type')->nullable();
            $table->fullText('question');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
