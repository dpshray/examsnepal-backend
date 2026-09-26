<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which subject a single-subject exam covers (Pathology, Orthodontics, ...).
 * Filled by `php artisan subjects:infer` from exam names, or set by an admin;
 * copied onto the exam's questions. Multi-subject exams (mocks, revision
 * sets) stay NULL. Powers per-subject scores and weak-subject emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            // 'auto' = inferred from the name, 'manual' = set by an admin (never overwritten by inference).
            $table->string('subject_source', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['subject_id']);
            $table->dropColumn(['subject_id', 'subject_source']);
        });
    }
};
