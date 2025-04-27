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
            $table->dropColumn(['assign']);
            $table->unsignedBigInteger('assign_id')->after('exam_type')->nullable();
            $table->foreign('assign_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
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
            $table->string('assign', 255)->nullable();
            $table->dropForeign(['assign_id','exam_type_id','user_id']);
            $table->dropColumn(['assign_id','exam_type_id','user_id']);
        });
    }
};
