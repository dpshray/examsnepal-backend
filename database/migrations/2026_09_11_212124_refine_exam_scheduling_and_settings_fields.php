<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Superseded by exam_mode + exam_date/exam_time/end_time below,
            // which support a full open/scheduled access window instead of
            // a single instant.
            $table->dropColumn('scheduled_at');

            $table->string('exam_mode')->default('open')->after('exam_name'); // 'open' | 'scheduled'
            $table->text('instructions')->nullable()->after('description');
            $table->boolean('is_shuffled_question')->default(false)->after('is_negative_marking');
            $table->boolean('is_shuffled_option')->default(false)->after('is_shuffled_question');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['exam_mode', 'instructions', 'is_shuffled_question', 'is_shuffled_option']);
            $table->dateTime('scheduled_at')->nullable()->after('exam_name');
        });
    }
};
