<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('forum_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('forum_questions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('answer');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down() {
        Schema::dropIfExists('forum_answers');
    }
};
