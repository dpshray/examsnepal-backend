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
        Schema::table('forum_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('forum_question_id')->after('id')->nullable();
            $table->foreign('forum_question_id')->references('id')->on('forum_questions')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forum_answers', function (Blueprint $table) {
            $table->dropForeign(['forum_question_id']);
            $table->dropColumn(['forum_question_id']);
        });
    }
};
