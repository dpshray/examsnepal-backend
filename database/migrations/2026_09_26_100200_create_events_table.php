<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->string('name', 50);
            $table->json('properties')->nullable();
            $table->string('platform', 10)->nullable(); // web | android | ios
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'name', 'created_at']);
            $table->index(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
