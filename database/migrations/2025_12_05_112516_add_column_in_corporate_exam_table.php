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
        Schema::table('corporate_exams', function (Blueprint $table) {
            //
            $table->date('exam_date')->nullable()->change();
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
            $table->renameColumn('about', 'description')->nullable()->after('title');
            $table->renameColumn('rules','instructions')->nullable()->after('description');
            $table->integer('duration')->nullable()->after('end_time');
            $table->boolean('is_shuffled_question')->default(false)->after('instructions');
            $table->boolean('is_shuffled_option')->default(false)->after('instructions');
            $table->integer('limit_attempts')->nullable()->after('is_published');
            $table->dropColumn('uuid');
            $table->boolean('is_published')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('corporate_exams', function (Blueprint $table) {
            //
            $table->date('exam_date')->nullable(false)->change();
            $table->time('start_time')->nullable(false)->change();
            $table->time('end_time')->nullable(false)->change();
            $table->renameColumn('description', 'about')->nullable()->after('title');
            $table->renameColumn('instructions','rules')->nullable()->after('about');
            $table->dropColumn('duration');
            $table->dropColumn('is_shuffled_question');
            $table->dropColumn('is_shuffled_option');
            $table->dropColumn('limit_attempts');
            $table->uuid();
            $table->boolean('is_published')->default(true)->change();
        });
    }
};
