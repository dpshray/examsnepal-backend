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
        Schema::create('user_blockeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->unsignedBigInteger('blocked_id');
            $table->foreign('blocked_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->timestamps();
        });
        Schema::table('forum_answers', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blockeds');
        Schema::table('forum_answers', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
        });
    }
};
