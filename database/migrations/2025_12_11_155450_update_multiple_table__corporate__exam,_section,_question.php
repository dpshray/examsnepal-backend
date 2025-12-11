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
        //
        Schema::table('corporate_exams', function (Blueprint $table) {
            $table->string('slug')->after('title');
        });
        Schema::table('corporate_exam_sections', function (Blueprint $table) {
            $table->string('slug')->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('corporate_exams', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('corporate_exam_sections', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
