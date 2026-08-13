<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institute_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institute_student_id')->constrained('institute_students')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['institute_id', 'institute_student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_reviews');
    }
};
