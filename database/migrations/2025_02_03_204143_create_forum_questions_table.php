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
        Schema::create('forum_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('question');
            $table->string('stream');
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('deleted')->default(false);
        });
    }

    public function down() {
        Schema::dropIfExists('forum_questions');
    }
};
