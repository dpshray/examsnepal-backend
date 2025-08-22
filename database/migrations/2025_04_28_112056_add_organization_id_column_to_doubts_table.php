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
        Schema::table('doubts', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('organization_id')->after('id')->nullable();
            $table->foreign('organization_id')->references('id')->on('exam_types')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doubts', function (Blueprint $table) {

            $table->dropForeign(['organization_id','student_id','question_id']);
            $table->dropColumn(['organization_id']);
        });
    }
};
