<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_type_tag', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_type_id');
            $table->unsignedBigInteger('exam_tag_id');
            $table->foreign('exam_type_id')->references('id')->on('exam_types')->onDelete('cascade');
            $table->foreign('exam_tag_id')->references('id')->on('exam_tags')->onDelete('cascade');
            $table->unique(['exam_type_id', 'exam_tag_id']);
            $table->timestamps();
        });

        // Backfill: preserve today's derived behavior (tags shown for an exam
        // type because some of its exams already carry that tag) so this is a
        // behavior-preserving refactor for exam types that already work.
        // Types with zero historically-tagged exams stay empty here - that's
        // the bug being fixed, to be corrected via the new admin UI, not by
        // rewriting history.
        DB::statement(<<<SQL
            INSERT INTO exam_type_tag (exam_type_id, exam_tag_id, created_at, updated_at)
            SELECT DISTINCT e.exam_type_id, tie.tag_id, NOW(), NOW()
            FROM tag_in_exams tie
            INNER JOIN exams e ON e.id = tie.exam_id
            WHERE e.exam_type_id IS NOT NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_type_tag');
    }
};
