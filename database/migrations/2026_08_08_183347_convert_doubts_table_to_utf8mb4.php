<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `doubts` table was created on latin1_general_cs while every other table in the
     * app uses utf8mb4_unicode_ci. That mismatch is what made some repaired mojibake text
     * (containing characters like δ, ∞, ∫) impossible to write back - latin1 physically
     * can't store them. This brings the table in line with the rest of the schema.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `doubts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `doubts` CONVERT TO CHARACTER SET latin1 COLLATE latin1_general_cs');
    }
};
