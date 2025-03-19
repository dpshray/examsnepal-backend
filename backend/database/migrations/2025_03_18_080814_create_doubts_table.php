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
        Schema::create('doubts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('set null'); 
            $table->foreignId('student_id')->constrained('student_profiles')->onDelete('set null');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('set null');
            $table->text('doubt');
            $table->text('remarks')->nullable();
            $table->boolean('status')->default(0);
            $table->foreignId('solved_by')->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doubts');
    }
};
