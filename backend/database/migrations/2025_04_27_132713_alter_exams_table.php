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
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_type_id')->after('user')->nullable();
            $table->foreign('exam_type_id')->references('id')->on('exam_types')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('user_id')->after('live')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['exam_type_id','user_id']);
            $table->dropColumn(['exam_type_id','user_id']);
        });
    }
};
