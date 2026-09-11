<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            // Polymorphic author: either App\Models\User (teacher) or
            // App\Models\InstituteStudent (student) - no morph map, plain
            // Eloquent morphTo() using the fully-qualified class name.
            $table->unsignedBigInteger('author_id');
            $table->string('author_type');
            $table->text('content');
            $table->timestamps();

            $table->index(['author_type', 'author_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_discussion_posts');
    }
};
