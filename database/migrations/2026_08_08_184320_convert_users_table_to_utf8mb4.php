<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `users` (admin/teacher/corporate accounts) was created on latin1_general_cs
     * while every other table uses utf8mb4_unicode_ci. Only integer/FK columns are
     * indexed here - no unique constraint on email/username - so this is a safe,
     * non-blocking charset conversion. Verified locally: no rows lost, existing
     * data reads back unchanged.
     *
     * `about` is varchar(25500) - under utf8mb4's 4 bytes/char that exceeds MySQL's
     * 65535-byte VARCHAR limit (~16383 chars max), so it has to move to MEDIUMTEXT
     * first to keep its full original capacity before the rest of the table converts.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `about` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
        DB::statement('ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `users` CONVERT TO CHARACTER SET latin1 COLLATE latin1_general_cs');
        DB::statement('ALTER TABLE `users` MODIFY `about` VARCHAR(25500) CHARACTER SET latin1 COLLATE latin1_general_cs NULL');
    }
};
