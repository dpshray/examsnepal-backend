<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `student_profiles` (student accounts) was created on latin1_general_cs while
     * every other table uses utf8mb4_unicode_ci. Only integer/FK columns are
     * indexed here - no unique constraint on email/phone - so this is a safe,
     * non-blocking charset conversion. Verified locally: no rows lost, existing
     * data reads back unchanged.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `student_profiles` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `student_profiles` CONVERT TO CHARACTER SET latin1 COLLATE latin1_general_cs');
    }
};
